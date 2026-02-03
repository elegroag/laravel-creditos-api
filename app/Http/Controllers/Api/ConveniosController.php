<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Services\ConvenioValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConveniosController extends Controller
{
    protected ConvenioValidationService $convenioService;

    public function __construct(ConvenioValidationService $convenioService)
    {
        $this->convenioService = $convenioService;
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
}
