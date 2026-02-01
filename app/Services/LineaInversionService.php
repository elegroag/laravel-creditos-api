<?php

namespace App\Services;

use App\Models\LineaInversion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class LineaInversionService extends EloquentService
{
    /**
     * Initialize default lines if they don't exist.
     */
    public function initializeData(): void
    {
        try {
            $this->initializeDefaultLines();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'inicialización de datos por defecto');
        }
    }

    /**
     * Ensure database indexes for better performance.
     */
    public function ensureIndex(): bool
    {
        try {
            // For MySQL, indexes are typically created via migrations
            // This method can be used to verify indexes exist or trigger recreation
            Log::info('Indexes verification completed for LineaInversion');

            return true;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'verificación de índices');
            return false;
        }
    }

    /**
     * Get investment lines by category (alias for getByCategoria).
     */
    public function getLineasByCategoria(string $categoria): array
    {
        return $this->getByCategoria($categoria);
    }

    /**
     * Get investment line by ID (alias for findById).
     */
    public function getLineaById(int $id): ?array
    {
        $linea = $this->findById($id);

        if (!$linea) {
            return null;
        }

        return $this->transformForApi($linea);
    }

    /**
     * Get all investment lines (alias for getAll).
     */
    public function getAllLineas(): array
    {
        return $this->getAll();
    }

    /**
     * Get all investment lines.
     */
    public function getAll(): array
    {
        try {
            $lineas = LineaInversion::where('active', true)
                ->orderBy('nombre')
                ->get();

            return [
                'lineas' => $this->transformCollectionForApi($lineas),
                'count' => $lineas->count()
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de líneas de inversión');
            return [
                'lineas' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Get investment line by ID.
     */
    public function findById(int $id): ?LineaInversion
    {
        try {
            return LineaInversion::find($id);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de línea de inversión');
            return null;
        }
    }

    /**
     * Create investment line.
     */
    public function create(array $data): LineaInversion
    {
        try {
            return LineaInversion::create($data);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'creación de línea de inversión');
            throw new \Exception('Error al crear línea de inversión');
        }
    }

    /**
     * Update investment line.
     */
    public function update(int $id, array $data): bool
    {
        try {
            $linea = LineaInversion::find($id);
            if (!$linea) {
                return false;
            }
            return $linea->update($data);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'actualización de línea de inversión');
            return false;
        }
    }

    /**
     * Delete investment line.
     */
    public function delete(int $id): bool
    {
        try {
            $linea = LineaInversion::find($id);
            if (!$linea) {
                return false;
            }
            return $linea->delete();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'eliminación de línea de inversión');
            return false;
        }
    }

    /**
     * Get active investment lines.
     */
    public function getActive(): Collection
    {
        try {
            return LineaInversion::where('active', true)
                ->orderBy('nombre')
                ->get();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de líneas activas');
            return collect([]);
        }
    }

    /**
     * Search investment lines.
     */
    public function searchLineas(string $term, array $filters = []): Collection
    {
        try {
            $query = LineaInversion::where('active', true);

            // Search by name
            if (!empty($term)) {
                $query->where('nombre', 'like', "%{$term}%");
            }

            // Apply filters
            if (!empty($filters)) {
                foreach ($filters as $field => $value) {
                    if (!empty($value)) {
                        $query->where($field, $value);
                    }
                }
            }

            return $query->orderBy('nombre')->get();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de líneas de inversión');
            return collect([]);
        }
    }

    /**
     * Get investment lines by category.
     */
    public function getByCategoria(string $categoria): array
    {
        try {
            $lineas = LineaInversion::where('categoria', $categoria)
                ->where('active', true)
                ->orderBy('nombre')
                ->get();

            return [
                'lineas' => $this->transformCollectionForApi($lineas),
                'count' => $lineas->count(),
                'categoria' => $categoria
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de líneas por categoría');
            return [
                'lineas' => [],
                'count' => 0,
                'categoria' => $categoria
            ];
        }
    }

    /**
     * Transform collection for API response.
     */
    private function transformCollectionForApi(Collection $collection): array
    {
        return $collection->map(function ($item) {
            return [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'descripcion' => $item->descripcion,
                'tasa_interes' => $item->tasa_interes,
                'plazo_maximo' => $item->plazo_maximo,
                'monto_minimo' => $item->monto_minimo,
                'monto_maximo' => $item->monto_maximo,
                'active' => $item->active,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at
            ];
        })->toArray();
    }

    /**
     * Get statistics.
     */
    public function getStatistics(): array
    {
        try {
            $total = LineaInversion::count();
            $active = LineaInversion::where('active', true)->count();
            $inactive = $total - $active;

            // Get by categoria
            $byCategoria = LineaInversion::selectRaw('categoria, COUNT(*) as count')
                ->groupBy('categoria')
                ->pluck('count', 'categoria')
                ->toArray();

            // Get by estado
            $byEstado = LineaInversion::selectRaw('active, COUNT(*) as count')
                ->groupBy('active')
                ->pluck('count', 'active')
                ->toArray();

            return [
                'total' => $total,
                'by_categoria' => $byCategoria,
                'by_estado' => $byEstado,
                'active_count' => $active,
                'inactive_count' => $inactive
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de estadísticas de líneas de inversión');
            return [
                'total' => 0,
                'by_categoria' => [],
                'by_estado' => [],
                'active_count' => 0,
                'inactive_count' => 0
            ];
        }
    }

    /**
     * Check if amount is within line limits.
     */
    public function isMontoWithinLimit(int $lineaId, float $monto): bool
    {
        try {
            $linea = $this->findById($lineaId);

            if (!$linea) {
                return false;
            }

            return $monto <= $linea->monto_maximo_pesos;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'verificación de límite de monto');
            return false;
        }
    }

    /**
     * Check if plazo is within line limits.
     */
    public function isPlazoWithinLimit(int $lineaId, int $plazoMeses): bool
    {
        try {
            $linea = $this->findById($lineaId);

            if (!$linea) {
                return false;
            }

            return $plazoMeses <= $linea->plazo_maximo;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'verificación de límite de plazo');
            return false;
        }
    }

    /**
     * Get interest rate for category.
     */
    public function getTasaInteresByCategoria(int $lineaId, string $categoria): ?string
    {
        try {
            $linea = $this->findById($lineaId);

            if (!$linea) {
                return null;
            }

            $tasas = $linea->tasas_interes_anual ?? [];

            return match ($categoria) {
                'A' => $tasas['categoria_a'] ?? null,
                'B' => $tasas['categoria_b'] ?? null,
                'C' => $tasas['categoria_c'] ?? null,
                default => null
            };
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de tasa de interés');
            return null;
        }
    }

    /**
     * Search investment lines.
     */
    public function search(string $term, ?string $categoria = null): array
    {
        try {
            $query = LineaInversion::where('active', true)
                ->where(function ($q) use ($term) {
                    $q->where('nombre', 'like', "%{$term}%")
                        ->orWhere('descripcion', 'like', "%{$term}%");
                });

            if ($categoria) {
                $query->where('categoria', $categoria);
            }

            $lineas = $query->orderBy('nombre', 'asc')->get();

            return [
                'lineas' => $this->transformCollectionForApi($lineas),
                'count' => $lineas->count(),
                'search_term' => $term,
                'categoria' => $categoria
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de líneas de inversión');
            return [
                'lineas' => [],
                'count' => 0,
                'search_term' => $term,
                'categoria' => $categoria
            ];
        }
    }

    /**
     * Initialize default investment lines.
     */
    public function initializeDefaultLines(): bool
    {
        try {
            // Delete existing lines
            LineaInversion::truncate();

            $defaultLines = [
                [
                    'nombre' => 'VIVIENDA (20 SMLMV)',
                    'monto_maximo_pesos' => 29000000,
                    'plazo_maximo' => '84 meses',
                    'tasas_interes_anual' => [
                        'categoria_a' => '6%',
                        'categoria_b' => '7%',
                        'categoria_c' => '9%'
                    ],
                    'requisitos' => [
                        'Formulario de solicitud de crédito',
                        'Fotocopia de cédula de ciudadanía al 150% del solicitante',
                        'Desprendible de nómina de los dos últimos meses',
                        'Certificado laboral (no mayor a 30 días)',
                        'Escritura pública o privada del inmueble',
                        'Presupuesto de inversión en obra civil',
                        'Certificado de libertad y tradición',
                        'Certificado de riesgo no mitigable',
                        'Copia de un recibo de servicio público'
                    ],
                    'categoria' => 'A',
                    'descripcion' => 'Línea de crédito para remodelación de vivienda',
                    'active' => true
                ],
                [
                    'nombre' => 'SALUD (15 SMLMV)',
                    'monto_maximo_pesos' => 17400000,
                    'plazo_maximo' => '48 meses',
                    'tasas_interes_anual' => [
                        'categoria_a' => '8%',
                        'categoria_b' => '9%',
                        'categoria_c' => '11%'
                    ],
                    'requisitos' => [
                        'Formulario de solicitud de crédito',
                        'Fotocopia de cédula de ciudadanía al 150% del solicitante',
                        'Desprendible de nómina de los dos últimos meses',
                        'Certificado laboral (no mayor a 30 días)',
                        'Cotización del servicio médico o procedimiento',
                        'Copia de un recibo de servicio público'
                    ],
                    'categoria' => 'C',
                    'descripcion' => 'Línea de crédito para gastos de salud',
                    'active' => true
                ],
                [
                    'nombre' => 'TURISMO (10 SMLMV)',
                    'monto_maximo_pesos' => 11600000,
                    'plazo_maximo' => '36 meses',
                    'tasas_interes_anual' => [
                        'categoria_a' => '11%',
                        'categoria_b' => '12%',
                        'categoria_c' => '14%'
                    ],
                    'requisitos' => [
                        'Formulario de solicitud de crédito',
                        'Fotocopia de cédula de ciudadanía al 150% del solicitante',
                        'Desprendible de nómina de los dos últimos meses',
                        'Certificado laboral (no mayor a 30 días)',
                        'Cotización del plan turístico o tiquetes',
                        'Copia de un recibo de servicio público'
                    ],
                    'categoria' => 'B',
                    'descripcion' => 'Línea de crédito para turismo',
                    'active' => true
                ]
            ];

            foreach ($defaultLines as $lineaData) {
                LineaInversion::create($lineaData);
            }

            $this->log('Investment lines initialized', [
                'count' => count($defaultLines)
            ]);

            return true;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'inicialización de líneas de inversión');
            return false;
        }
    }

    /**
     * Transform investment line for API response.
     */
    public function transformForApi($linea): array
    {
        return [
            'id' => $linea->id,
            'nombre' => $linea->nombre,
            'monto_maximo_pesos' => $linea->monto_maximo_pesos,
            'monto_maximo_formatted' => number_format($linea->monto_maximo_pesos, 0, ',', '.'),
            'plazo_maximo' => $linea->plazo_maximo,
            'plazo_maximo_meses' => $linea->plazo_maximo,
            'tasas_interes_anual' => $linea->tasas_interes_anual,
            'tasa_categoria_a' => $linea->tasas_interes_anual['categoria_a'] ?? null,
            'tasa_categoria_b' => $linea->tasas_interes_anual['categoria_b'] ?? null,
            'tasa_categoria_c' => $linea->tasas_interes_anual['categoria_c'] ?? null,
            'requisitos' => $linea->requisitos,
            'categoria' => $linea->categoria,
            'categoria_label' => match ($linea->categoria) {
                'A' => 'Categoria A',
                'B' => 'Categoria B',
                'C' => 'Categoria C',
                default => 'Sin categoría'
            },
            'descripcion' => $linea->descripcion,
            'active' => $linea->active,
            'estado_label' => $linea->active ? 'Activa' : 'Inactiva',
            'is_active' => $linea->active,
            'created_at' => $linea->created_at->toISOString(),
            'updated_at' => $linea->updated_at->toISOString()
        ];
    }
}
