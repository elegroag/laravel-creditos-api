<?php

namespace App\Services;

use App\Models\LineaInversion;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LineaInversionService extends BaseService
{
    /**
     * Get all investment lines.
     */
    public function getAll(): array
    {
        try {
            $lineas = LineaInversion::orderBy('id', 'asc')->get();

            return [
                'lineas' => $lineas->toArray(),
                'count' => $lineas->count()
            ];

        } catch (\Exception $e) {
            $this->logError('Error getting all investment lines', ['error' => $e->getMessage()]);
            return [
                'lineas' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Get investment line by ID.
     */
    public function getById(int $id): ?LineaInversion
    {
        try {
            return LineaInversion::findById($id);
        } catch (\Exception $e) {
            $this->logError('Error getting investment line by ID', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get investment lines by category.
     */
    public function getByCategoria(string $categoria): array
    {
        try {
            $lineas = LineaInversion::where('categoria', $categoria)
                                     ->orWhere('categoria', null)
                                     ->orderBy('id', 'asc')
                                     ->get();

            return [
                'lineas' => $lineas->toArray(),
                'count' => $lineas->count(),
                'categoria' => $categoria
            ];

        } catch (\Exception $e) {
            $this->logError('Error getting investment lines by category', ['categoria' => $categoria, 'error' => $e->getMessage()]);
            return [
                'lineas' => [],
                'count' => 0,
                'categoria' => $categoria
            ];
        }
    }

    /**
     * Get active investment lines.
     */
    public function getActive(): array
    {
        try {
            $lineas = LineaInversion::active()->orderBy('id', 'asc')->get();

            return [
                'lineas' => $lineas->toArray(),
                'count' => $lineas->count()
            ];

        } catch (\Exception $e) {
            $this->logError('Error getting active investment lines', ['error' => $e->getMessage()]);
            return [
                'lineas' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Create or update investment line.
     */
    public function createOrUpdate(array $data): LineaInversion
    {
        try {
            // Validate data
            $validator = SolicitudValidators::validateLineaInversion($data);

            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }

            $validated = $validator->validated();

            // Check if line already exists
            $existing = LineaInversion::findById($validated['id']);

            if ($existing) {
                // Update existing line
                $existing->updateFromArray($validated);
                $linea = $existing->fresh();

                $this->log('Investment line updated', [
                    'linea_id' => $validated['id'],
                    'linea_credito' => $validated['linea_credito']
                ]);
            } else {
                // Create new line
                $linea = LineaInversion::createFromArray($validated);

                $this->log('Investment line created', [
                    'linea_id' => $validated['id'],
                    'linea_credito' => $validated['linea_credito']
                ]);
            }

            return $linea;

        } catch (\Exception $e) {
            $this->logError('Error creating/updating investment line', ['error' => $e->getMessage()]);
            throw new \Exception('Error al crear/actualizar línea de inversión: ' . $e->getMessage());
        }
    }

    /**
     * Delete investment line.
     */
    public function delete(int $id): bool
    {
        try {
            $linea = LineaInversion::findById($id);

            if (!$linea) {
                throw new \Exception('Línea de inversión no encontrada');
            }

            $linea->delete();

            $this->log('Investment line deleted', [
                'linea_id' => $id,
                'linea_credito' => $linea->linea_credito
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logError('Error deleting investment line', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \Exception('Error al eliminar línea de inversión: ' . $e->getMessage());
        }
    }

    /**
     * Initialize investment lines with default data.
     */
    public function initializeData(): bool
    {
        try {
            // Check if data already exists
            if (LineaInversion::count() > 0) {
                $this->log('Investment lines already initialized', ['count' => LineaInversion::count()]);
                return true;
            }

            // Default investment lines data
            $defaultLines = [
                [
                    'id' => 1,
                    'linea_credito' => 'EDUCACION SUPERIOR (20 SMLMV)',
                    'monto_maximo_pesos' => 23200000,
                    'plazo_maximo' => '60 meses',
                    'tasas_interes_anual' => [
                        'categoria_a' => '10%',
                        'categoria_b' => '11%',
                        'categoria_c' => '14%'
                    ],
                    'requisitos' => [
                        'Formulario de solicitud de crédito',
                        'Fotocopia de cédula de ciudadanía al 150% del solicitante',
                        'Desprendible de nómina de los dos últimos meses',
                        'Certificado laboral (no mayor a 30 días)',
                        'Comprobante para pago de matrícula',
                        'Copia de un recibo de servicio público'
                    ],
                    'categoria' => 'A',
                    'descripcion' => 'Línea de crédito para educación superior',
                    'estado' => 'activa'
                ],
                [
                    'id' => 2,
                    'linea_credito' => 'LIBRE INVERSION (25 SMLMV)',
                    'monto_maximo_pesos' => 29000000,
                    'plazo_maximo' => '60 meses',
                    'tasas_interes_anual' => [
                        'categoria_a' => '12%',
                        'categoria_b' => '13%',
                        'categoria_c' => '15%'
                    ],
                    'requisitos' => [
                        'Formulario de solicitud de crédito',
                        'Fotocopia de cédula de ciudadanía al 150% del solicitante',
                        'Desprendible de nómina de los dos últimos meses',
                        'Certificado laboral (no mayor a 30 días)',
                        'Copia de un recibo de servicio público'
                    ],
                    'categoria' => 'B',
                    'descripcion' => 'Línea de crédito para libre inversión',
                    'estado' => 'activa'
                ],
                [
                    'id' => 3,
                    'linea_credito' => 'VIVIENDA REMODELACIÓN (30 SMLMV)',
                    'monto_maximo_pesos' => 34800000,
                    'plazo_maximo' => '84 meses',
                    'tasas_interes_anual' => [
                        'categoria_a' => '9%',
                        'categoria_b' => '10%',
                        'categoria_c' => '11%'
                    ],
                    'requisitos' => [
                        'Formulario de solicitud de crédito',
                        'Fotocopia de cédula de ciudadanía al 150% del solicitante',
                        'Desprendible de nómina de los dos últimos meses',
                        'Certificado laboral (no mayor a 30 días)',
                        'Presupuesto de inversión en obra civil',
                        'Certificado de libertad y tradición',
                        'Certificado de riesgo no mitigable',
                        'Copia de un recibo de servicio público'
                    ],
                    'categoria' => 'A',
                    'descripcion' => 'Línea de crédito para remodelación de vivienda',
                    'estado' => 'activa'
                ],
                [
                    'id' => 4,
                    'linea_credito' => 'SALUD (15 SMLMV)',
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
                    'estado' => 'activa'
                ],
                [
                    'id' => 5,
                    'linea_credito' => 'TURISMO (10 SMLMV)',
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
                    'estado' => 'activa'
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
            $this->logError('Error initializing investment lines', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get investment line statistics.
     */
    public function getStatistics(): array
    {
        try {
            $total = LineaInversion::count();
            $byCategoria = LineaInversion::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => [
                        '_id' => '$categoria',
                        'count' => ['$sum' => 1],
                        'total_monto' => ['$sum' => '$monto_maximo_pesos']
                    ],
                    ['$sort' => ['count' => -1]]
                ]);
            });

            $byEstado = LineaInversion::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => [
                        '_id' => '$estado',
                        'count' => ['$sum' => 1]
                    ],
                    ['$sort' => ['count' => -1]]
                ]);
            });

            return [
                'total' => $total,
                'by_categoria' => $byCategoria->toArray(),
                'by_estado' => $byEstado->toArray(),
                'active_count' => LineaInversion::active()->count(),
                'inactive_count' => LineaInversion::where('estado', '!=', 'activa')->count()
            ];

        } catch (\Exception $e) {
            $this->logError('Error getting investment line statistics', ['error' => $e->getMessage()]);
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
            $linea = $this->getById($lineaId);

            if (!$linea) {
                return false;
            }

            return $linea->isMontoWithinLimit($monto);

        } catch (\Exception $e) {
            $this->logError('Error checking amount limits', ['linea_id' => $lineaId, 'monto' => $monto, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if plazo is within line limits.
     */
    public function isPlazoWithinLimit(int $lineaId, int $plazoMeses): bool
    {
        try {
            $linea = $this->getById($lineaId);

            if (!$linea) {
                return false;
            }

            return $linea->isPlazoWithinLimit($plazoMeses);

        } catch (\Exception $e) {
            $this->logError('Error checking plazo limits', ['linea_id' => $lineaId, 'plazo' => $plazoMeses, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get interest rate for category.
     */
    public function getTasaInteresByCategoria(int $lineaId, string $categoria): ?string
    {
        try {
            $linea = $this->getById($lineaId);

            if (!$linea) {
                return null;
            }

            return $linea->getTasaInteresByCategoria($categoria);

        } catch (\Exception $e) {
            $this->logError('Error getting interest rate', ['linea_id' => $lineaId, 'categoria' => $categoria, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Search investment lines.
     */
    public function search(string $term, ?string $categoria = null): array
    {
        try {
            $query = LineaInversion::where('linea_credito', 'like', "%{$term}%")
                                     ->orWhere('descripcion', 'like', "%{$term}%");

            if ($categoria) {
                $query->where('categoria', $categoria);
            }

            $lineas = $query->orderBy('id', 'asc')->get();

            return [
                'lineas' => $lineas->toArray(),
                'count' => $lineas->count(),
                'search_term' => $term,
                'categoria' => $categoria
            ];

        } catch (\Exception $e) {
            $this->logError('Error searching investment lines', ['term' => $term, 'categoria' => $categoria, 'error' => $e->getMessage()]);
            return [
                'lineas' => [],
                'count' => 0,
                'search_term' => $term,
                'categoria' => $categoria
            ];
        }
    }

    /**
     * Transform investment line for API response.
     */
    public function transformForApi($linea): array
    {
        return [
            'id' => $linea->id,
            'linea_credito' => $linea->linea_credito,
            'monto_maximo_pesos' => $linea->monto_maximo_pesos,
            'monto_maximo_formatted' => $linea->monto_maximo_formatted,
            'plazo_maximo' => $linea->plazo_maximo,
            'plazo_maximo_meses' => $linea->plazo_maximo_meses,
            'tasas_interes_anual' => $linea->tasas_interes_anual,
            'tasa_categoria_a' => $linea->tasa_categoria_a,
            'tasa_categoria_b' => $linea->tasa_categoria_b,
            'tasa_categoria_c' => $linea->tasa_categoria_c,
            'tasas_interes_array' => $linea->tasas_interes_array,
            'requisitos' => $linea->requisitos,
            'requisitos_formatted' => $linea->requisitos_formatted,
            'categoria' => $linea->categoria,
            'categoria_label' => $linea->categoria_label,
            'descripcion' => $linea->descripcion,
            'estado' => $linea->estado,
            'estado_label' => $linea->estado_label,
            'is_active' => $linea->isActive(),
            'created_at' => $linea->created_at?->toISOString(),
            'updated_at' => $linea->updated_at?->toISOString()
        ];
    }

    /**
     * Transform collection for API response.
     */
    public function transformCollectionForApi($lineas): array
    {
        return $lineas->map(fn ($linea) => $this->transformForApi($linea))->toArray();
    }
}
