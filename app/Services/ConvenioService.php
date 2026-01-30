<?php

namespace App\Services;

use App\Models\EmpresaConvenio;
use App\Repositories\ConvenioRepository;
use App\Services\TrabajadorService;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ConvenioService extends BaseService
{
    private TrabajadorService $trabajadorService;
    private string $externalApiUrl;
    private int $timeout;

    public function __construct(ConvenioRepository $convenioRepository, ?TrabajadorService $trabajadorService = null)
    {
        parent::__construct($convenioRepository);
        $this->trabajadorService = $trabajadorService ?: new TrabajadorService();
        $this->externalApiUrl = config('services.external_api.url', 'https://api.example.com');
        $this->timeout = config('services.external_api.timeout', 8);
    }

    /**
     * Validate worker eligibility for company credit.
     */
    public function validarElegibilidadConvenio(string $cedulaTrabajador, int $nitEmpresa): array
    {
        try {
            // 1. Get worker data
            $trabajadorData = $this->obtenerDatosTrabajador($cedulaTrabajador);

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

            // 7. Validate service time (minimum 6 months)
            $fechaAfiliacion = $trabajadorData['fecha_afiliacion'] ?? $trabajadorData['fecafi'] ?? null;

            if (!$fechaAfiliacion) {
                throw new ValidationException(
                    'No se pudo determinar la fecha de afiliación del trabajador',
                    ['cedula' => $cedulaTrabajador]
                );
            }

            $mesesServicio = $this->calcularMesesServicio($fechaAfiliacion);

            if ($mesesServicio < 6) {
                throw new ValidationException(
                    'El trabajador no cumple con el tiempo mínimo de servicio',
                    [
                        'cedula' => $cedulaTrabajador,
                        'meses_servicio' => $mesesServicio,
                        'minimo_requerido' => 6,
                        'mensaje' => 'Se requieren al menos 6 meses de servicio en la empresa'
                    ]
                );
            }

            // 8. Build successful response
            $this->log('Convenio validation successful', [
                'nit_empresa' => $nitEmpresa,
                'cedula_trabajador' => $cedulaTrabajador,
                'meses_servicio' => $mesesServicio
            ]);

            return [
                'elegible' => true,
                'convenio' => [
                    'id' => $convenio->id,
                    'nit' => $convenio->nit,
                    'razon_social' => $convenio->razon_social,
                    'fecha_convenio' => $convenio->fecha_convenio?->toISOString(),
                    'fecha_vencimiento' => $convenio->fecha_vencimiento?->toISOString(),
                    'estado' => $convenio->estado,
                    'representante_nombre' => $convenio->representante_nombre,
                    'representante_documento' => $convenio->representante_documento,
                    'correo' => $convenio->correo,
                    'telefono' => $convenio->telefono
                ],
                'trabajador' => [
                    'cedula' => $trabajadorData['cedtra'] ?? $trabajadorData['cedula'],
                    'nombre_completo' => $this->construirNombreCompleto($trabajadorData),
                    'estado' => $trabajadorData['estado'],
                    'meses_servicio' => $mesesServicio,
                    'fecha_afiliacion' => $fechaAfiliacion,
                    'salario' => $trabajadorData['salario'],
                    'cargo' => $trabajadorData['cargo'],
                    'email' => $trabajadorData['email']
                ],
                'mensaje' => 'El trabajador es elegible para solicitar crédito bajo convenio empresarial'
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error validating convenio eligibility', [
                'cedula_trabajador' => $cedulaTrabajador,
                'nit_empresa' => $nitEmpresa,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Error en validación de convenio: ' . $e->getMessage());
        }
    }

    /**
     * Get worker data from external API.
     */
    private function obtenerDatosTrabajador(string $cedula): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->externalApiUrl}/company/informacion_trabajador", [
                    'cedtra' => $cedula
                ]);

            $data = $response->json();

            if (!$data['success'] ?? false) {
                $this->logError('External API returned error for worker', [
                    'cedula' => $cedula,
                    'error' => $data['error'] ?? 'Unknown error'
                ]);
                return null;
            }

            return $data['data'] ?? null;
        } catch (\Exception $e) {
            $this->logError('Exception getting worker data', [
                'cedula' => $cedula,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find active agreement by company NIT.
     */
    private function buscarConvenioPorNit(int $nit): ?EmpresaConvenio
    {
        try {
            $convenios = $this->repository->getActive();

            foreach ($convenios as $convenio) {
                if ($convenio->nit === $nit) {
                    return $convenio;
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->logError('Error finding agreement by NIT', [
                'nit' => $nit,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Calculate service months from affiliation date.
     */
    private function calcularMesesServicio(string $fechaAfiliacion): int
    {
        try {
            $fechaAfi = $this->parseDate($fechaAfiliacion);

            if (!$fechaAfi) {
                return 0;
            }

            $diferencia = Carbon::now()->diffInMonths($fechaAfi);

            return max(0, $diferencia);
        } catch (\Exception $e) {
            $this->logError('Error calculating service months', [
                'fecha_afiliacion' => $fechaAfiliacion,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Build full name from worker data.
     */
    private function construirNombreCompleto(array $trabajadorData): string
    {
        $names = [
            $trabajadorData['prinom'] ?? $trabajadorData['primer_nombre'] ?? '',
            $trabajadorData['segnom'] ?? $trabajadorData['segundo_nombre'] ?? '',
            $trabajadorData['priape'] ?? $trabajadorData['primer_apellido'] ?? '',
            $trabajadorData['segape'] ?? $trabajadorData['segundo_apellido'] ?? ''
        ];

        return trim(implode(' ', array_filter($names)));
    }

    /**
     * Parse date from various formats.
     */
    private function parseDate(string $date): ?Carbon
    {
        $formats = [
            'Y-m-d',
            'Y/m/d',
            'd/m/Y',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:sZ'
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $date);
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get all active agreements.
     */
    public function getActiveAgreements(): array
    {
        try {
            $convenios = $this->repository->getActive();

            return [
                'convenios' => $this->transformCollectionForApi($convenios),
                'count' => $convenios->count()
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting active agreements', ['error' => $e->getMessage()]);
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
            return $this->repository->findByNit($nit);
        } catch (\Exception $e) {
            $this->logError('Error getting agreement by NIT', [
                'nit' => $nit,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create or update agreement.
     */
    public function createOrUpdate(array $data): EmpresaConvenio
    {
        try {
            $convenio = $this->repository->createConvenio($data);

            $this->log('Agreement created/updated', [
                'nit' => $data['nit'],
                'razon_social' => $data['razon_social'] ?? ''
            ]);

            return $convenio;
        } catch (\Exception $e) {
            $this->logError('Error creating/updating agreement', [
                'nit' => $data['nit'] ?? '',
                'error' => $e->getMessage()
            ]);
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

            $this->repository->deactivate($nit);

            $this->log('Agreement deactivated', ['nit' => $nit]);

            return true;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error deactivating agreement', [
                'nit' => $nit,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get agreement statistics.
     */
    public function getStatistics(): array
    {
        try {
            return $this->repository->getStatistics();
        } catch (\Exception $e) {
            $this->logError('Error getting agreement statistics', ['error' => $e->getMessage()]);
            return [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'expiring_soon' => 0,
                'by_status' => [],
                'by_city' => [],
                'by_department' => [],
                'by_sector' => [],
                'active_percentage' => 0,
                'expired_percentage' => 0
            ];
        }
    }

    /**
     * Search agreements by criteria.
     */
    public function search(array $criteria): array
    {
        try {
            $term = $criteria['term'] ?? '';
            $filters = $criteria['filters'] ?? [];

            $convenios = $this->repository->searchConvenios($term, $filters);

            return [
                'convenios' => $this->transformCollectionForApi($convenios),
                'count' => $convenios->count(),
                'criteria' => $criteria
            ];
        } catch (\Exception $e) {
            $this->logError('Error searching agreements', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return [
                'convenios' => [],
                'count' => 0,
                'criteria' => $criteria
            ];
        }
    }

    /**
     * Get agreements with pagination.
     */
    public function getPaginated(int $perPage = 15, array $filters = []): array
    {
        try {
            return $this->repository->getPaginated($perPage, $filters);
        } catch (\Exception $e) {
            $this->logError('Error getting paginated agreements', [
                'per_page' => $perPage,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [
                'data' => [],
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1
            ];
        }
    }

    /**
     * Get dashboard data.
     */
    public function getDashboardData(): array
    {
        try {
            return $this->repository->getDashboardData();
        } catch (\Exception $e) {
            $this->logError('Error getting dashboard data', ['error' => $e->getMessage()]);
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
            return $this->repository->getUniqueCities()->toArray();
        } catch (\Exception $e) {
            $this->logError('Error getting unique cities', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get unique departments.
     */
    public function getUniqueDepartments(): array
    {
        try {
            return $this->repository->getUniqueDepartments()->toArray();
        } catch (\Exception $e) {
            $this->logError('Error getting unique departments', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get unique economic sectors.
     */
    public function getUniqueSectoresEconomicos(): array
    {
        try {
            return $this->repository->getUniqueSectoresEconomicos()->toArray();
        } catch (\Exception $e) {
            $this->logError('Error getting unique economic sectors', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get unique company types.
     */
    public function getUniqueTiposEmpresa(): array
    {
        try {
            return $this->repository->getUniqueTiposEmpresa()->toArray();
        } catch (\Exception $e) {
            $this->logError('Error getting unique company types', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Export agreements to array format.
     */
    public function exportToArray(array $filters = []): array
    {
        try {
            return $this->repository->exportToArray($filters);
        } catch (\Exception $e) {
            $this->logError('Error exporting agreements', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Bulk update agreements status.
     */
    public function bulkUpdateStatus(array $nits, string $status): int
    {
        try {
            return $this->repository->bulkUpdateStatus($nits, $status);
        } catch (\Exception $e) {
            $this->logError('Error bulk updating agreements status', [
                'nits' => $nits,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get agreements that need renewal notification.
     */
    public function getForRenewalNotification(int $daysBefore = 30): array
    {
        try {
            return $this->repository->getForRenewalNotification($daysBefore)
                ->map(fn($convenio) => $this->transformForApi($convenio))
                ->toArray();
        } catch (\Exception $e) {
            $this->logError('Error getting agreements for renewal notification', [
                'days_before' => $daysBefore,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Soft delete agreement.
     */
    public function softDeleteConvenio(int $nit): bool
    {
        try {
            return $this->repository->softDeleteConvenio($nit);
        } catch (\Exception $e) {
            $this->logError('Error soft deleting agreement', [
                'nit' => $nit,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Restore soft deleted agreement.
     */
    public function restoreConvenio(int $nit): bool
    {
        try {
            return $this->repository->restoreConvenio($nit);
        } catch (\Exception $e) {
            $this->logError('Error restoring agreement', [
                'nit' => $nit,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
