<?php

namespace App\Services;

use App\Models\EmpresaConvenio;
use App\Repositories\BaseRepository;
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

    public function __construct(BaseRepository $convenioRepository, TrabajadorService $trabajadorService)
    {
        parent::__construct($convenioRepository);
        $this->trabajadorService = $trabajadorService ?: new TrabajadorService();
        $this->externalApiUrl = config('services.external_api.url', 'https://api.example.com');
        $this->timeout = config('services.external_api.timeout', 8);
    }

    /**
     * Validate worker eligibility for company credit.
     */
    public function validarElegibilidadConvenio(string $cedulaTrabajador, string $nitEmpresa): array
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
    private function buscarConvenioPorNit(string $nit): ?EmpresaConvenio
    {
        try {
            $convenio = EmpresaConvenio::where('nit', $nit)
                ->where('estado', 'Activo')
                ->first();

            return $convenio;
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
            $convenios = EmpresaConvenio::where('estado', 'Activo')
                ->orderBy('razon_social')
                ->get();

            return [
                'convenios' => $convenios->toArray(),
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
    public function getByNit(string $nit): ?EmpresaConvenio
    {
        try {
            return EmpresaConvenio::where('nit', $nit)->first();
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
            $convenio = EmpresaConvenio::updateOrCreate(
                ['nit' => $data['nit']],
                $data
            );

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
    public function deactivate(string $nit): bool
    {
        try {
            $convenio = $this->getByNit($nit);

            if (!$convenio) {
                throw new ValidationException('Convenio no encontrado');
            }

            $convenio->update(['estado' => 'Inactivo']);

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
            $total = EmpresaConvenio::count();
            $activos = EmpresaConvenio::where('estado', 'Activo')->count();
            $inactivos = $total - $activos;

            $porVencer = EmpresaConvenio::where('estado', 'Activo')
                ->where('fecha_vencimiento', '>', now())
                ->where('fecha_vencimiento', '<=', now()->addDays(30))
                ->count();

            $vencidos = EmpresaConvenio::where('estado', 'Activo')
                ->where('fecha_vencimiento', '<', now())
                ->count();

            return [
                'total' => $total,
                'activos' => $activos,
                'inactivos' => $inactivos,
                'por_vencer' => $porVencer,
                'vencidos' => $vencidos,
                'tasa_activacion' => $total > 0 ? round(($activos / $total) * 100, 2) : 0
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting agreement statistics', ['error' => $e->getMessage()]);
            return [
                'total' => 0,
                'activos' => 0,
                'inactivos' => 0,
                'por_vencer' => 0,
                'vencidos' => 0,
                'tasa_activacion' => 0
            ];
        }
    }

    /**
     * Search agreements by criteria.
     */
    public function search(array $criteria): array
    {
        try {
            $query = EmpresaConvenio::query();

            if (isset($criteria['razon_social'])) {
                $query->where('razon_social', 'like', '%' . $criteria['razon_social'] . '%');
            }

            if (isset($criteria['nit'])) {
                $query->where('nit', 'like', '%' . $criteria['nit'] . '%');
            }

            if (isset($criteria['estado'])) {
                $query->where('estado', $criteria['estado']);
            }

            if (isset($criteria['representante_nombre'])) {
                $query->where('representante_nombre', 'like', '%' . $criteria['representante_nombre'] . '%');
            }

            $convenios = $query->orderBy('razon_social')->get();

            return [
                'convenios' => $convenios->toArray(),
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
}
