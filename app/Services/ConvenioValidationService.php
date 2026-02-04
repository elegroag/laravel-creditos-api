<?php

namespace App\Services;

use App\Models\EmpresaConvenio;
use App\Services\TrabajadorService;
use App\Services\ExternalApiService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ConvenioValidationService
{
    private TrabajadorService $trabajadorService;
    private ExternalApiService $externalApiService;

    public function __construct(?TrabajadorService $trabajadorService = null, ?ExternalApiService $externalApiService = null)
    {
        $this->trabajadorService = $trabajadorService ?? app(TrabajadorService::class);
        $this->externalApiService = $externalApiService ?? app(ExternalApiService::class);
    }

    /**
     * Valida si un trabajador es elegible para solicitar crédito bajo convenio empresarial.
     *
     * @param string $nitEmpresa NIT de la empresa del trabajador
     * @param string $cedulaTrabajador Cédula del trabajador
     * @return array Resultado de validación con detalles del convenio
     * @throws \Exception Si no se encuentra convenio o trabajador
     * @throws \Exception Si no cumple requisitos mínimos
     */
    public function validarConvenioTrabajador(string $nitEmpresa, string $cedulaTrabajador): array
    {

        // 1. Obtener datos del trabajador desde API externa
        $trabajadorData = $this->obtenerDatosTrabajador($cedulaTrabajador);

        if (!$trabajadorData) {
            throw new \Exception(
                'No se encontraron datos del trabajador'
            );
        }

        // 2. Verificar que el NIT del trabajador coincida con el proporcionado
        $nitTrabajador = $trabajadorData['empresa']['nit'] ?? null;

        if ((string)$nitTrabajador !== (string)$nitEmpresa) {
            throw new \Exception(
                'El trabajador no pertenece a la empresa especificada NIT ' . $nitTrabajador
            );
        }

        // 3. Buscar convenio activo para la empresa
        $convenio = $this->buscarConvenioPorNit($nitEmpresa);

        if (!$convenio) {
            throw new \Exception(
                'La empresa no tiene convenio activo con Comfaca'
            );
        }

        // 4. Validar estado del convenio
        if ($convenio->estado !== 'Activo') {
            throw new \Exception(
                'El convenio de la empresa no está activo'
            );
        }

        // 5. Validar fecha de vencimiento del convenio
        if ($convenio->fecha_vencimiento) {
            $fechaVenc = Carbon::parse($convenio->fecha_vencimiento);

            if ($fechaVenc->isPast()) {
                throw new \Exception(
                    'El convenio de la empresa ha vencido'
                );
            }
        }

        // 6. Validar estado del trabajador (debe estar Activo = 'A')
        $estadoTrabajador = $trabajadorData['estado'] ?? null;
        if ($estadoTrabajador !== 'A') {
            throw new \Exception(
                'El trabajador no está activo en la empresa'
            );
        }

        // 7. Validar tiempo de servicio (mínimo 6 meses)
        // El campo puede venir como 'fecha_afiliacion' o 'fecafi' según la fuente
        $fechaAfiliacion = $trabajadorData['fecha_afiliacion'] ?? $trabajadorData['fecafi'] ?? null;

        if (!$fechaAfiliacion) {
            throw new \Exception(
                'No se pudo determinar la fecha de afiliación del trabajador'
            );
        }

        $mesesServicio = $this->calcularMesesServicio($fechaAfiliacion);

        if ($mesesServicio < 6) {
            throw new \Exception(
                'El trabajador no cumple con el tiempo mínimo de servicio'
            );
        }

        // 8. Construir respuesta exitosa
        Log::info('Validación de convenio exitosa', [
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
                'fecha_convenio' => $convenio->fecha_convenio,
                'fecha_vencimiento' => $convenio->fecha_vencimiento,
                'estado' => $convenio->estado,
                'representante_nombre' => $convenio->representante_nombre,
                'representante_documento' => $convenio->representante_documento,
                'correo' => $convenio->correo,
                'telefono' => $convenio->telefono
            ],
            'trabajador' => [
                'cedula' => $trabajadorData['cedtra'] ?? $trabajadorData['cedula'] ?? null,
                'nombre_completo' => $this->construirNombreCompleto($trabajadorData),
                'estado' => $trabajadorData['estado'],
                'meses_servicio' => $mesesServicio,
                'fecha_afiliacion' => $fechaAfiliacion,
                'salario' => $trabajadorData['salario'] ?? null,
                'cargo' => $trabajadorData['cargo'] ?? null,
                'email' => $trabajadorData['email'] ?? null
            ],
            'mensaje' => 'El trabajador es elegible para solicitar crédito bajo convenio empresarial'
        ];
    }

    /**
     * Obtiene datos del trabajador desde la API externa usando ExternalApiService.
     *
     * @param string $cedula Cédula del trabajador
     * @return array|null Datos del trabajador o null si no se encuentra
     */
    private function obtenerDatosTrabajador(string $cedula): ?array
    {
        try {
            // Usar TrabajadorService si está disponible
            if ($this->trabajadorService) {
                return $this->trabajadorService->obtenerDatosTrabajador($cedula);
            }

            // Usar ExternalApiService para obtener datos del trabajador
            $data = $this->externalApiService->obtenerInformacionTrabajador($cedula);

            if (!$data) {
                Log::error('No se obtuvieron datos del trabajador desde ExternalApiService', [
                    'cedula' => $cedula
                ]);
                return null;
            }

            // Validar estructura mínima de datos
            if (!isset($data['cedtra']) && !isset($data['cedula'])) {
                Log::error('Respuesta sin datos de cédula', [
                    'cedula' => $cedula,
                    'response_keys' => array_keys($data)
                ]);
                return null;
            }

            Log::info('Datos del trabajador obtenidos exitosamente via ExternalApiService', [
                'cedula' => $cedula,
                'nit' => $data['nit'] ?? 'N/A'
            ]);

            return $data;
        } catch (\Exception $e) {
            Log::error('Error al obtener datos del trabajador via ExternalApiService', [
                'cedula' => $cedula,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Busca convenio activo por NIT de empresa.
     *
     * @param string $nit NIT de la empresa
     * @return EmpresaConvenio|null Convenio encontrado o null
     */
    private function buscarConvenioPorNit(string $nit): ?EmpresaConvenio
    {
        try {
            return EmpresaConvenio::where('nit', $nit)
                ->where('estado', 'Activo')
                ->first();
        } catch (\Exception $e) {
            Log::error('Error al buscar convenio por NIT', [
                'nit' => $nit,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Calcula los meses de servicio basado en la fecha de afiliación.
     *
     * @param string $fechaAfiliacion Fecha de afiliación del trabajador
     * @return int Meses de servicio
     */
    private function calcularMesesServicio(string $fechaAfiliacion): int
    {
        try {
            $fecha = Carbon::parse($fechaAfiliacion);
            $ahora = Carbon::now();

            return $fecha->diffInMonths($ahora);
        } catch (\Exception $e) {
            Log::error('Error al calcular meses de servicio', [
                'fecha_afiliacion' => $fechaAfiliacion,
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }

    /**
     * Construye el nombre completo del trabajador.
     *
     * @param array $trabajadorData Datos del trabajador
     * @return string Nombre completo
     */
    private function construirNombreCompleto(array $trabajadorData): string
    {
        $nombre = $trabajadorData['nombre'] ?? '';
        $apellido1 = $trabajadorData['apellido1'] ?? $trabajadorData['apellido'] ?? '';
        $apellido2 = $trabajadorData['apellido2'] ?? '';

        return trim("{$nombre} {$apellido1} {$apellido2}");
    }
}
