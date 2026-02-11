<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\EmpresaConvenio;
use App\Services\ConvenioValidationService;
use App\Services\TrabajadorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConveniosController extends Controller
{
    protected ConvenioValidationService $convenioService;
    protected TrabajadorService $trabajadorService;

    public function __construct(ConvenioValidationService $convenioService, TrabajadorService $trabajadorService)
    {
        $this->convenioService = $convenioService;
        $this->trabajadorService = $trabajadorService;
    }

    /**
     * Valida si un trabajador es elegible para solicitar crédito bajo convenio empresarial.
     *
     * Valida:
     * - Existencia de convenio activo y vigente para la empresa
     * - Estado del trabajador (debe estar Activo = 'A')
     * - Tiempo de servicio (mínimo 6 meses)
     *
     * @param string $nit_empresa NIT de la empresa
     * @param string $cedula_trabajador Cédula del trabajador
     * @return JsonResponse
     */
    public function validarConvenioTrabajador(string $nit_empresa, string $cedula_trabajador): JsonResponse
    {
        try {

            // Instanciar servicio y validar
            $resultado = $this->convenioService->validarConvenioTrabajador($nit_empresa, $cedula_trabajador);

            return ApiResource::success($resultado, 'Validación exitosa: el trabajador es elegible para solicitar crédito bajo convenio')->response();
        } catch (\Exception $e) {

            // Determinar el código de estado según el tipo de error
            $statusCode = 500;
            $message = 'Error interno al validar convenio';

            // Si el error es de validación, retornar 400
            if (
                strpos($e->getMessage(), 'No se encontraron datos') !== false ||
                strpos($e->getMessage(), 'no pertenece a la empresa') !== false ||
                strpos($e->getMessage(), 'no está activo') !== false ||
                strpos($e->getMessage(), 'no cumple con el tiempo mínimo') !== false ||
                strpos($e->getMessage(), 'no tiene convenio activo') !== false ||
                strpos($e->getMessage(), 'ha vencido') !== false
            ) {
                $statusCode = 400;
                $message = $e->getMessage();
            }

            // Si es de no encontrado, retornar 404
            if (
                strpos($e->getMessage(), 'No se encontraron datos') !== false ||
                strpos($e->getMessage(), 'no tiene convenio activo') !== false
            ) {
                $statusCode = 404;
            }

            // Usar ErrorResource según el tipo de error
            if ($statusCode === 404) {
                return ErrorResource::notFound($message)->response();
            } elseif ($statusCode === 400) {
                return ErrorResource::errorResponse($message)->response()->setStatusCode(400);
            } else {
                return ErrorResource::serverError($message, [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getMessage()
                ])->response();
            }
        }
    }

    /**
     * Valida convenio mediante POST (alternativa para formularios).
     *
     * Body:
     * {
     *     "nit_empresa": "string",
     *     "cedula_trabajador": "string"
     * }
     *
     * @return JsonResponse
     */
    public function validarConvenioPost(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nit_empresa' => 'required|string',
                'cedula_trabajador' => 'required|string'
            ], [
                'nit_empresa.required' => 'Se requiere el NIT de la empresa',
                'cedula_trabajador.required' => 'Se requiere la cédula del trabajador'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();
            $nit_empresa = $data['nit_empresa'];
            $cedula_trabajador = $data['cedula_trabajador'];

            // Reutilizar la lógica del GET
            $resultado = $this->convenioService->validarConvenioTrabajador($nit_empresa, $cedula_trabajador);

            return ApiResource::success($resultado, 'Validación exitosa: el trabajador es elegible para solicitar crédito bajo convenio')->response();
        } catch (\Exception $e) {
            Log::error('Error en validar_convenio_post', [
                'error' => $e->getMessage()
            ]);

            // Determinar el código de estado según el tipo de error
            $statusCode = 500;
            $message = 'Error interno al validar convenio';

            // Si el error es de validación, retornar 400
            if (
                strpos($e->getMessage(), 'No se encontraron datos') !== false ||
                strpos($e->getMessage(), 'no pertenece a la empresa') !== false ||
                strpos($e->getMessage(), 'no está activo') !== false ||
                strpos($e->getMessage(), 'no cumple con el tiempo mínimo') !== false ||
                strpos($e->getMessage(), 'no tiene convenio activo') !== false ||
                strpos($e->getMessage(), 'ha vencido') !== false
            ) {
                $statusCode = 400;
                $message = $e->getMessage();
            }

            // Si es de no encontrado, retornar 404
            if (
                strpos($e->getMessage(), 'No se encontraron datos') !== false ||
                strpos($e->getMessage(), 'no tiene convenio activo') !== false
            ) {
                $statusCode = 404;
            }

            // Usar ErrorResource según el tipo de error
            if ($statusCode === 404) {
                return ErrorResource::notFound($message)->response();
            } elseif ($statusCode === 400) {
                return ErrorResource::errorResponse($message)->response()->setStatusCode(400);
            } else {
                return ErrorResource::serverError($message, [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getMessage()
                ])->response();
            }
        }
    }

    /**
     * Obtiene el convenio activo del trabajador autenticado.
     */
    public function obtenerConvenioActivo(Request $request): JsonResponse
    {
        try {
            $authUser = Auth::user();

            $currentUser = $request->get('current_user');
            $authenticatedUser = $request->get('authenticated_user');

            $numeroDocumento = null;
            if ($authUser && isset($authUser->numero_documento) && $authUser->numero_documento) {
                $numeroDocumento = (string) $authUser->numero_documento;
            }

            if (!$numeroDocumento && is_array($currentUser)) {
                $numeroDocumento = $currentUser['numero_documento'] ?? null;
            }

            if (!$numeroDocumento && is_array($authenticatedUser)) {
                $numeroDocumento = $authenticatedUser['user']['numero_documento'] ?? null;
            }

            if (!$numeroDocumento) {
                return ErrorResource::errorResponse('No fue posible identificar el documento del usuario')->response()
                    ->setStatusCode(400);
            }

            $trabajador = $this->trabajadorService->obtenerDatosTrabajador((string) $numeroDocumento);
            $nitEmpresa = $trabajador['empresa']['nit'] ?? null;

            if (!$nitEmpresa) {
                return ErrorResource::notFound('No fue posible identificar la empresa del trabajador')->response();
            }

            $convenio = EmpresaConvenio::where('nit', $nitEmpresa)
                ->where('estado', 'Activo')
                ->first();

            if (!$convenio) {
                return ErrorResource::notFound('La empresa no tiene convenio activo con Comfaca')->response();
            }

            return ApiResource::success([
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
                    'telefono' => $convenio->telefono,
                    'direccion' => $convenio->direccion,
                    'ciudad' => $convenio->ciudad,
                    'departamento' => $convenio->departamento,
                    'sector_economico' => $convenio->sector_economico,
                    'tipo_empresa' => $convenio->tipo_empresa,
                ],
                'trabajador' => $trabajador,
            ], 'Convenio activo obtenido exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener convenio activo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ErrorResource::serverError('Error interno al obtener convenio activo', [
                'trace' => $e->getMessage()
            ])->response();
        }
    }
}
