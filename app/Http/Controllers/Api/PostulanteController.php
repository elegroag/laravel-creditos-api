<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Services\ExternalApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

            Log::info('Consultando cónyuges de trabajador', [
                'cedtra' => $cedtra,
                'estado' => $estado
            ]);

            // Preparar payload para la API externa
            $apiPayload = [
                'cedtra' => $cedtra,
                'estado' => $estado
            ];

            // Realizar petición a la API externa usando ExternalApiService
            $response = $this->externalApiService->post('/affiliation/listar_conyuges_trabajador', $apiPayload);

            // Verificar si la respuesta contiene error
            if (!$response['success'] ?? true || isset($response['error'])) {
                Log::warning('API externa retornó error para cónyuges', [
                    'cedtra' => $cedtra,
                    'error' => $response['error'] ?? 'Error desconocido',
                    'status_code' => $response['status_code'] ?? null
                ]);

                return ErrorResource::errorResponse('Error consultando cónyuges del trabajador', [
                    'api_error' => $response['error'] ?? 'Error desconocido',
                    'api_status_code' => $response['status_code'] ?? null
                ])->response()->setStatusCode(502);
            }

            Log::info('Cónyuges consultados exitosamente', [
                'cedtra' => $cedtra,
                'count' => count($response['data'] ?? [])
            ]);

            return ApiResource::success($response['data'] ?? [], 'Cónyuges consultados exitosamente')->response();
        } catch (ValidationException $e) {
            Log::warning('Error de validación en buscarConyugeTrabajador', [
                'errors' => $e->errors()
            ]);

            return ErrorResource::validationError($e->errors(), 'Datos inválidos')
                ->response()
                ->setStatusCode(422);
        } catch (\Exception $e) {
            Log::error('Error inesperado en buscarConyugeTrabajador', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al consultar cónyuges', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }
}
