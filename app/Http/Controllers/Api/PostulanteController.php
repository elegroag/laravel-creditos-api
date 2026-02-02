<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PostulanteController extends Controller
{
    /**
     * Consulta cónyuges de trabajador mediante API externa
     *
     * @param Request $request
     * @return JsonResponse
     */
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

            // Obtener URL de la API externa desde configuración
            $externalApiUrl = config('services.external_api.url', 'https://api.example.com');
            $timeout = config('services.external_api.timeout', 8);

            // Realizar petición a la API externa
            $response = Http::post($externalApiUrl . '/affiliation/listar_conyuges_trabajador', $apiPayload)->timeout($timeout);

            // Verificar respuesta
            if (!$response->successful()) {
                Log::error('Error en respuesta de API externa de cónyuges', [
                    'cedtra' => $cedtra,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return ErrorResource::errorResponse('Error consultando cónyuges del trabajador', [
                    'api_status' => $response->status(),
                    'response' => $response->body()
                ])->response()->setStatusCode(502);
            }

            $responseData = $response->json();

            // Verificar si la respuesta contiene error
            if (!$responseData['success'] ?? true || isset($responseData['error'])) {
                Log::warning('API externa retornó error para cónyuges', [
                    'cedtra' => $cedtra,
                    'error' => $responseData['error'] ?? 'Error desconocido',
                    'detail' => $responseData['detail'] ?? null
                ]);

                return ErrorResource::errorResponse('Error consultando cónyuges del trabajador', [
                    'api_error' => $responseData['error'] ?? 'Error desconocido',
                    'api_detail' => $responseData['detail'] ?? null
                ])->response()->setStatusCode(502);
            }

            Log::info('Cónyuges consultados exitosamente', [
                'cedtra' => $cedtra,
                'count' => count($responseData['data'] ?? [])
            ]);

            return ApiResource::success($responseData['data'] ?? [], 'Cónyuges consultados exitosamente')->response();
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
