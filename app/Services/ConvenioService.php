<?php

namespace App\Services;

use App\Models\EmpresaConvenio;
use App\Services\TrabajadorService;
use App\Services\ExternalApiService;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ConvenioService extends EloquentService
{
    private TrabajadorService $trabajadorService;
    private ExternalApiService $externalApiService;

    public function __construct(TrabajadorService $trabajadorService, ExternalApiService $externalApiService)
    {
        $this->trabajadorService = $trabajadorService;
        $this->externalApiService = $externalApiService;
    }

    /**
     * Get active convenios.
     */
    public function getActiveConvenios(): Collection
    {
        try {
            return EmpresaConvenio::active()
                ->orderBy('razon_social')
                ->get();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de convenios activos');
            return collect([]);
        }
    }

    /**
     * Get all active agreements.
     */
    public function getActiveAgreements(): array
    {
        try {
            $convenios = EmpresaConvenio::active()->orderBy('razon_social')->get();

            return [
                'convenios' => $this->transformCollectionForApi($convenios),
                'count' => $convenios->count()
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de convenios activos');
            return [
                'convenios' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Get agreement by NIT.
     */
    public function getByNit(int $nit): ?EmpresaConvenio
    {
        try {
            return EmpresaConvenio::where('nit', $nit)->first();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de convenio por NIT');
            return null;
        }
    }

    /**
     * Create or update agreement.
     */
    public function createOrUpdate(array $criteria, array $data): EmpresaConvenio
    {
        try {
            $convenio = EmpresaConvenio::updateOrCreate(['nit' => $data['nit']], $data);

            $this->log('Agreement created/updated', [
                'nit' => $data['nit'],
                'razon_social' => $data['razon_social'] ?? ''
            ]);

            return $convenio;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'creación/actualización de convenio');
            throw new \Exception('Error al crear/actualizar convenio: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate agreement.
     */
    public function deactivate(int $nit): bool
    {
        try {
            $convenio = $this->getByNit($nit);

            if (!$convenio) {
                throw new ValidationException('Convenio no encontrado');
            }

            $convenio->update(['active' => false]);

            $this->log('Agreement deactivated', ['nit' => $nit]);

            return true;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'desactivación de convenio');
            return false;
        }
    }

    /**
     * Get statistics.
     */
    public function getStatistics(): array
    {
        try {
            $total = EmpresaConvenio::count();
            $active = EmpresaConvenio::where('active', true)->count();
            $thisMonth = EmpresaConvenio::whereMonth('created_at', now()->month)->count();

            return [
                'total' => $total,
                'active' => $active,
                'inactive' => $total - $active,
                'this_month' => $thisMonth,
                'active_rate' => $total > 0 ? round(($active / $total) * 100, 2) : 0
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de estadísticas');
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'this_month' => 0,
                'active_rate' => 0
            ];
        }
    }

    /**
     * Search agreements.
     */
    public function search(array $criteria = []): array
    {
        try {
            $term = $criteria['term'] ?? '';
            $filters = $criteria['filters'] ?? [];

            $query = EmpresaConvenio::where('active', true);

            // Search by term
            if (!empty($term)) {
                $query->where(function ($q) use ($term) {
                    $q->where('razon_social', 'like', "%{$term}%")
                        ->orWhere('nit', 'like', "%{$term}%")
                        ->orWhere('direccion', 'like', "%{$term}%");
                });
            }

            // Apply filters
            if (!empty($filters)) {
                foreach ($filters as $field => $value) {
                    if (!empty($value)) {
                        $query->where($field, $value);
                    }
                }
            }

            $convenios = $query->orderBy('razon_social')->get();

            return [
                'convenios' => $this->transformCollectionForApi($convenios),
                'count' => $convenios->count()
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de convenios');
            return [
                'convenios' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Get paginated agreements.
     */
    public function getPaginated(int $perPage = 15, array $filters = []): array
    {
        try {
            $query = EmpresaConvenio::where('active', true);

            // Apply filters
            if (!empty($filters)) {
                foreach ($filters as $field => $value) {
                    if (!empty($value)) {
                        $query->where($field, $value);
                    }
                }
            }

            $convenios = $query->orderBy('razon_social')->paginate($perPage);

            return [
                'data' => $this->transformCollectionForApi($convenios->getCollection()),
                'pagination' => [
                    'current_page' => $convenios->currentPage(),
                    'per_page' => $convenios->perPage(),
                    'total' => $convenios->total(),
                    'last_page' => $convenios->lastPage(),
                    'from' => $convenios->firstItem(),
                    'to' => $convenios->lastItem()
                ]
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'paginación de convenios');
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => 0,
                    'to' => 0
                ]
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
                'nit' => $item->nit,
                'razon_social' => $item->razon_social,
                'direccion' => $item->direccion,
                'ciudad' => $item->ciudad,
                'departamento' => $item->departamento,
                'telefono' => $item->telefono,
                'email' => $item->email,
                'sector_economico' => $item->sector_economico,
                'tipo_empresa' => $item->tipo_empresa,
                'active' => $item->active,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at
            ];
        })->toArray();
    }

    /**
     * Validate worker eligibility for company credit.
     */
    public function validarElegibilidadConvenio(string $cedulaTrabajador, int $nitEmpresa): array
    {
        try {
            // 1. Get worker data
            $trabajadorData = $this->trabajadorService->obtenerDatosTrabajador($cedulaTrabajador);

            if (!$trabajadorData) {
                throw new ValidationException(
                    'No se encontraron datos del trabajador',
                    ['cedula' => $cedulaTrabajador]
                );
            }

            // 2. Verify worker NIT matches company NIT
            $nitTrabajador = $trabajadorData['nit'] ?? null;

            if (strval($nitTrabajador) !== strval($nitEmpresa)) {
                throw new ValidationException(
                    'El trabajador no pertenece a la empresa especificada',
                    [
                        'nit_empresa' => $nitEmpresa,
                        'nit_trabajador' => $nitTrabajador
                    ]
                );
            }

            // 3. Find active agreement for company
            $convenio = $this->buscarConvenioPorNit($nitEmpresa);

            if (!$convenio) {
                throw new ValidationException(
                    'La empresa no tiene convenio activo con Comfaca',
                    ['nit' => $nitEmpresa]
                );
            }

            // 4. Validate agreement status
            if ($convenio->estado !== 'Activo') {
                throw new ValidationException(
                    'El convenio de la empresa no está activo',
                    [
                        'nit' => $nitEmpresa,
                        'estado_convenio' => $convenio->estado
                    ]
                );
            }

            // 5. Validate agreement expiration
            if ($convenio->fecha_vencimiento && $convenio->fecha_vencimiento->isPast()) {
                throw new ValidationException(
                    'El convenio de la empresa ha vencido',
                    [
                        'nit' => $nitEmpresa,
                        'fecha_vencimiento' => $convenio->fecha_vencimiento->toISOString()
                    ]
                );
            }

            // 6. Validate worker status (must be Active = 'A')
            $estadoTrabajador = $trabajadorData['estado'] ?? null;
            if ($estadoTrabajador !== 'A') {
                throw new ValidationException(
                    'El trabajador no está activo en la empresa',
                    [
                        'cedula' => $cedulaTrabajador,
                        'estado' => $estadoTrabajador,
                        'mensaje' => 'El trabajador debe estar en estado Activo (A) para solicitar créditos'
                    ]
                );
            }

            return [
                'valido' => true,
                'convenio' => $this->transformForApi($convenio),
                'trabajador' => $trabajadorData,
                'mensaje' => 'El trabajador es elegible para crédito con convenio'
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'validación de elegibilidad de convenio');
            return [
                'valido' => false,
                'error' => 'Error al validar elegibilidad del convenio'
            ];
        }
    }

    /**
     * Get dashboard data.
     */
    public function getDashboardData(): array
    {
        try {
            $total = EmpresaConvenio::count();
            $activos = EmpresaConvenio::where('estado', 'Activo')->count();
            $vencidos = EmpresaConvenio::where('estado', 'Vencido')->count();
            $porVencer = EmpresaConvenio::where('estado', 'Activo')
                ->where('fecha_vencimiento', '>', now())
                ->where('fecha_vencimiento', '<=', now()->addDays(30))
                ->count();

            $creadosHoy = EmpresaConvenio::whereDate('created_at', today())->count();
            $creadosAyer = EmpresaConvenio::whereDate('created_at', now()->subDay())->count();

            $vencenProximos30Dias = EmpresaConvenio::where('estado', 'Activo')
                ->where('fecha_vencimiento', '>', now())
                ->where('fecha_vencimiento', '<=', now()->addDays(30))
                ->count();

            $porcentajeActivos = $total > 0 ? round(($activos / $total) * 100, 2) : 0;

            $ciudadesUnicas = EmpresaConvenio::distinct('ciudad')->count('ciudad');
            $departamentosUnicos = EmpresaConvenio::distinct('departamento')->count('departamento');
            $sectoresEconomicosUnicos = EmpresaConvenio::distinct('sector_economico')->count('sector_economico');

            return [
                'total' => $total,
                'activos' => $activos,
                'vencidos' => $vencidos,
                'por_vencer' => $porVencer,
                'creados_hoy' => $creadosHoy,
                'creados_ayer' => $creadosAyer,
                'vencen_proximos_30_dias' => $vencenProximos30Dias,
                'porcentaje_activos' => $porcentajeActivos,
                'ciudades_unicas' => $ciudadesUnicas,
                'departamentos_unicos' => $departamentosUnicos,
                'sectores_economicos_unicos' => $sectoresEconomicosUnicos
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de datos del dashboard');
            return [
                'total' => 0,
                'activos' => 0,
                'vencidos' => 0,
                'por_vencer' => 0,
                'creados_hoy' => 0,
                'creados_ayer' => 0,
                'vencen_proximos_30_dias' => 0,
                'porcentaje_activos' => 0,
                'ciudades_unicas' => 0,
                'departamentos_unicos' => 0,
                'sectores_economicos_unicos' => 0
            ];
        }
    }

    /**
     * Get unique cities.
     */
    public function getUniqueCities(): array
    {
        try {
            return EmpresaConvenio::whereNotNull('ciudad')
                ->distinct('ciudad')
                ->orderBy('ciudad')
                ->pluck('ciudad')
                ->toArray();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de ciudades únicas');
            return [];
        }
    }

    /**
     * Get unique departments.
     */
    public function getUniqueDepartments(): array
    {
        try {
            return EmpresaConvenio::whereNotNull('departamento')
                ->distinct('departamento')
                ->orderBy('departamento')
                ->pluck('departamento')
                ->toArray();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de departamentos únicos');
            return [];
        }
    }

    /**
     * Get unique economic sectors.
     */
    public function getUniqueSectoresEconomicos(): array
    {
        try {
            return EmpresaConvenio::whereNotNull('sector_economico')
                ->distinct('sector_economico')
                ->orderBy('sector_economico')
                ->pluck('sector_economico')
                ->toArray();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de sectores económicos únicos');
            return [];
        }
    }

    /**
     * Get unique company types.
     */
    public function getUniqueTiposEmpresa(): array
    {
        try {
            return EmpresaConvenio::whereNotNull('tipo_empresa')
                ->distinct('tipo_empresa')
                ->orderBy('tipo_empresa')
                ->pluck('tipo_empresa')
                ->toArray();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de tipos de empresa únicos');
            return [];
        }
    }

    /**
     * Export agreements to array format.
     */
    public function exportToArray(array $filters = []): array
    {
        try {
            $query = EmpresaConvenio::query();

            // Apply filters
            if (!empty($filters)) {
                foreach ($filters as $field => $value) {
                    if (!empty($value)) {
                        $query->where($field, $value);
                    }
                }
            }

            $convenios = $query->orderBy('razon_social')->get();

            return $convenios->map(function ($convenio) {
                return [
                    'nit' => $convenio->nit,
                    'razon_social' => $convenio->razon_social,
                    'fecha_convenio' => $convenio->fecha_convenio,
                    'fecha_vencimiento' => $convenio->fecha_vencimiento,
                    'estado' => $convenio->estado,
                    'representante_documento' => $convenio->representante_documento,
                    'representante_nombre' => $convenio->representante_nombre,
                    'telefono' => $convenio->telefono,
                    'correo' => $convenio->correo,
                    'ciudad' => $convenio->ciudad,
                    'departamento' => $convenio->departamento,
                    'sector_economico' => $convenio->sector_economico,
                    'tipo_empresa' => $convenio->tipo_empresa,
                    'created_at' => $convenio->created_at,
                    'updated_at' => $convenio->updated_at
                ];
            })->toArray();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'exportación de convenios');
            return [];
        }
    }

    /**
     * Bulk update agreements status.
     */
    public function bulkUpdateStatus(array $nits, string $status): int
    {
        try {
            return EmpresaConvenio::whereIn('nit', $nits)
                ->update(['estado' => $status]);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'actualización masiva de estado de convenios');
            return 0;
        }
    }

    /**
     * Get agreements that need renewal notification.
     */
    public function getForRenewalNotification(int $daysBefore = 30): array
    {
        try {
            return EmpresaConvenio::where('estado', 'Activo')
                ->where('fecha_vencimiento', '>', now())
                ->where('fecha_vencimiento', '<=', now()->addDays($daysBefore))
                ->orderBy('fecha_vencimiento')
                ->get()
                ->map(fn($convenio) => $this->transformForApi($convenio))
                ->toArray();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de convenios para notificación de renovación');
            return [];
        }
    }

    /**
     * Soft delete agreement.
     */
    public function softDeleteConvenio(int $nit): bool
    {
        try {
            $convenio = EmpresaConvenio::where('nit', $nit)->first();
            if (!$convenio) {
                return false;
            }
            return $convenio->delete();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'eliminación suave de convenio');
            return false;
        }
    }

    /**
     * Restore soft deleted agreement.
     */
    public function restoreConvenio(int $nit): bool
    {
        try {
            $convenio = EmpresaConvenio::withTrashed()->where('nit', $nit)->first();
            if (!$convenio) {
                return false;
            }
            return $convenio->restore();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'restauración de convenio');
            return false;
        }
    }

    /**
     * Transform single agreement for API response.
     */
    private function transformForApi(EmpresaConvenio $convenio): array
    {
        return [
            'id' => $convenio->id,
            'nit' => $convenio->nit,
            'razon_social' => $convenio->razon_social,
            'fecha_convenio' => $convenio->fecha_convenio,
            'fecha_vencimiento' => $convenio->fecha_vencimiento,
            'estado' => $convenio->estado,
            'representante_documento' => $convenio->representante_documento,
            'representante_nombre' => $convenio->representante_nombre,
            'telefono' => $convenio->telefono,
            'correo' => $convenio->correo,
            'ciudad' => $convenio->ciudad,
            'departamento' => $convenio->departamento,
            'sector_economico' => $convenio->sector_economico,
            'tipo_empresa' => $convenio->tipo_empresa,
            'created_at' => $convenio->created_at,
            'updated_at' => $convenio->updated_at
        ];
    }

    /**
     * Find convenio by NIT (legacy method).
     */
    private function buscarConvenioPorNit(int $nit): ?EmpresaConvenio
    {
        try {
            return EmpresaConvenio::where('nit', $nit)
                ->where('estado', 'Activo')
                ->first();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de convenio por NIT');
            return null;
        }
    }
}
