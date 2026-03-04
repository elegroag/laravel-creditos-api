<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\SolicitudCredito;
use App\Services\ExternalApiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PostulanteController extends Controller
{
    private ExternalApiService $externalApiService;

    public function __construct(ExternalApiService $externalApiService)
    {
        $this->externalApiService = $externalApiService;
    }
    /**
     * Consulta cónyuges de trabajador mediante API externa
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[OA\Post(
        path: '/conyuge-trabajador',
        tags: ['Postulante'],
        summary: 'Buscar cónyuges de trabajador',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['cedtra', 'estado'],
                properties: [
                    new OA\Property(property: 'cedtra', type: 'integer', example: 123456789, description: 'Cédula del trabajador'),
                    new OA\Property(property: 'estado', type: 'string', example: 'A', description: 'Estado del trabajador')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cónyuges encontrados'),
            new OA\Response(response: 422, description: 'Datos inválidos'),
            new OA\Response(response: 502, description: 'Error en API externa')
        ]
    )]
    public function buscarConyugeTrabajador(Request $request): JsonResponse
    {
        try {
            // Validar payload
            $validator = Validator::make($request->all(), [
                'cedtra' => 'required|integer|min:1',
                'estado' => 'required|string|max:1'
            ], [
                'cedtra.required' => 'El campo cedtra es requerido',
                'cedtra.integer' => 'El campo cedtra debe ser un número entero',
                'cedtra.min' => 'El campo cedtra debe ser un número positivo',
                'estado.required' => 'El campo estado es requerido',
                'estado.string' => 'El campo estado debe ser texto',
                'estado.max' => 'El campo estado debe tener máximo 1 carácter'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();
            $cedtra = $data['cedtra'];
            $estado = strtoupper(trim($data['estado']));

            // Preparar payload para la API externa
            $apiPayload = [
                'cedtra' => $cedtra,
                'estado' => $estado
            ];

            // Realizar petición a la API externa usando ExternalApiService
            $response = $this->externalApiService->post('/affiliation/listar_conyuges_trabajador', $apiPayload);

            // Verificar si la respuesta contiene error
            if (!$response['success'] ?? true || isset($response['error'])) {
                return ErrorResource::errorResponse('Error consultando cónyuges del trabajador', [
                    'api_error' => $response['error'] ?? 'Error desconocido',
                    'api_status_code' => $response['status_code'] ?? null
                ])->response()->setStatusCode(502);
            }

            return ApiResource::success($response['data'] ?? [], 'Cónyuges consultados exitosamente')->response();
        } catch (ValidationException $e) {
            return ErrorResource::validationError($e->errors(), 'Datos inválidos')
                ->response()
                ->setStatusCode(422);
        } catch (\Exception $e) {
            return ErrorResource::serverError('Error interno al consultar cónyuges', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    public function crearTerceroUseApi(string $solicitud_id): JsonResponse
    {
        try {
            if (empty($solicitud_id)) {
                return ErrorResource::errorResponse('ID de solicitud es requerido', [
                    'solicitud_id' => $solicitud_id
                ])->response()->setStatusCode(400);
            }

            $data = SolicitudCredito::where('numero_solicitud', $solicitud_id)->first();
            if (!$data) {
                return ErrorResource::errorResponse('Solicitud no encontrada', [
                    'solicitud_id' => $solicitud_id
                ])->response()->setStatusCode(404);
            }
            $solicitante = $data->solicitante;

            // Separar nombres y apellidos
            $nombres = explode(' ', trim($solicitante->nombres ?? ''));
            $apellidos = explode(' ', trim($solicitante->apellidos ?? ''));
            $primerNombre = $nombres[0] ?? '';
            $segundoNombre = $nombres[1] ?? '';
            $primerApellido = $apellidos[0] ?? '';
            $segundoApellido = $apellidos[1] ?? '';

            $tipoPersona = $solicitante->tipo_persona ?? 'N'; // 'N' o 'J'
            $telefono = $solicitante->telefono_movil ?? ($solicitante->telefono_fijo ?? '');

            $payload = [
                'numdoc'   => $solicitante->numero_documento,
                'coddoc'   => $solicitante->tipo_documento ?? '1',
                'digver'   => '',
                'tipper'   => $tipoPersona,
                'cedres'   => $solicitante->numero_documento,
                'priape'   => $primerApellido,
                'segape'   => $segundoApellido,
                'prinom'   => $primerNombre,
                'segnom'   => $segundoNombre,
                'razsoc'   => $solicitante->razon_social ?? '',
                'sexo'     => $solicitante->genero ?? '',
                'codcat'   => $solicitante->codigo_categoria ?? '',
                'direccion' => $solicitante->direccion,
                'telefono' => $telefono,
                'fax'      => $solicitante->telefono_fijo ?? '',
                'email'    => $solicitante->email,
                'codzon'   => $solicitante->ciudad ?? '',
                'tipter'   => 'T',
                'tipcon'   => $solicitante->tipo_contrato ?? '',
                'rut'      => $solicitante->rut ?? '',
                'nitemp'   => $solicitante->nit ?? '',
                'razemp'   => $solicitante->razon_social ?? '',
                'nota'     => '',
                'fecsis'   => date('Y-m-d')
            ];

            // Realizar petición a la API externa usando ExternalApiService
            $response = $this->externalApiService->post('/creditos/crear-tercero', $payload);

            // Verificar si la respuesta contiene error
            if (!$response['success'] ?? true || isset($response['error'])) {
                return ErrorResource::errorResponse('Error creando tercero', [
                    'api_error' => $response['error'] ?? 'Error desconocido',
                    'api_status_code' => $response['status_code'] ?? null
                ])->response()->setStatusCode(502);
            }

            return ApiResource::success($response['data'] ?? [], 'Tercero creado exitosamente')->response();
        } catch (\Exception $e) {
            return ErrorResource::errorResponse('Error creando tercero', [
                'error' => $e->getMessage()
            ])->response()->setStatusCode(500);
        }
    }

    public function buscarTerceroUseApi($tipdoc, $cedula): JsonResponse
    {
        try {
            if (!$tipdoc || !$cedula) {
                return ErrorResource::errorResponse('Tipdoc y cedula son requeridos')->response()->setStatusCode(400);
            }

            // Realizar petición a la API externa usando ExternalApiService
            $response = $this->externalApiService->get("/creditos/buscar-tercero/{$tipdoc}/{$cedula}");

            // Verificar si la respuesta contiene error
            if (!$response['success'] ?? true || isset($response['error'])) {
                return ErrorResource::errorResponse('Error buscando tercero', [
                    'api_error' => $response['error'] ?? 'Error desconocido',
                    'api_status_code' => $response['status_code'] ?? null
                ])->response()->setStatusCode(502);
            }

            return ApiResource::success($response['data'] ?? [], 'Tercero buscado exitosamente')->response();
        } catch (\Throwable $th) {
            return ErrorResource::errorResponse('Error buscando tercero', [
                'error' => $th->getMessage()
            ])->response()->setStatusCode(500);
        }
    }

    /**
     * Calcular antigüedad en meses desde la fecha de vinculación
     */
    private function calcularAntiguedadMeses(?string $fechaVinculacion): int
    {
        if (!$fechaVinculacion) {
            return 1; // Por defecto 1 mes si no hay fecha
        }

        try {
            $fechaVinculacion = Carbon::createFromFormat('Y-m-d', $fechaVinculacion);
            $fechaActual = Carbon::now();

            return $fechaVinculacion->diffInMonths($fechaActual);
        } catch (\Exception $e) {
            return 1; // Por defecto 1 mes si hay error en el cálculo
        }
    }
}
