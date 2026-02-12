<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Services\ExternalApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use OpenApi\Attributes as OA;

class LineasCreditoController extends Controller
{
    protected ExternalApiService $externalApiService;

    public function __construct(ExternalApiService $externalApiService)
    {
        $this->externalApiService = $externalApiService;
    }

    /**
     * Obtener parámetros generales de líneas de crédito
     */
    #[OA\Get(
        path: '/lineas_credito/parametros',
        tags: ['LineasCredito'],
        summary: 'Obtener parámetros generales',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Parámetros obtenidos'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function obtenerParametros(): JsonResponse
    {
        try {
            // Realizar la consulta a la API externa
            $response = $this->externalApiService->post('/creditos/datos_generales');

            // Verificar si la respuesta fue exitosa
            $isSuccess = ($response['success'] ?? true) && !isset($response['error']);

            if (!$isSuccess) {
                return ErrorResource::errorResponse('Error al consultar parámetros generales', [
                    'external_error' => $response['error'] ?? 'Error en el servicio externo',
                    'status_code' => $response['status_code'] ?? 500
                ])->response()->setStatusCode(500);
            }

            $responseData = $response;

            // Verificar si la respuesta contiene error (para respuestas directas de API externa)
            if (isset($responseData['error']) && $responseData['error']) {
                Log::warning('API externa retornó error - parámetros', [
                    'error' => $responseData['error'] ?? 'Error desconocido',
                    'detail' => $responseData['detail'] ?? null
                ]);

                return ErrorResource::errorResponse('Error al consultar datos generales', [
                    'external_error' => $responseData['error'] ?? 'Error desconocido',
                    'external_detail' => $responseData['detail'] ?? null
                ])->response()->setStatusCode(400);
            }

            Log::info('Parámetros generales obtenidos exitosamente', [
                'data_keys' => array_keys($responseData['data'] ?? [])
            ]);

            return ApiResource::success(
                $responseData['data'] ?? [],
                'Datos generales obtenidos exitosamente'
            )->response();
        } catch (\Exception $e) {
            Log::error('Error al consultar parámetros generales', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al consultar datos generales', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener tipos de crédito disponibles
     */
    #[OA\Get(
        path: '/lineas_credito/tipo_creditos',
        tags: ['LineasCredito'],
        summary: 'Obtener tipos de crédito',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Tipos de crédito obtenidos'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function obtenerTiposCreditos(): JsonResponse
    {
        try {
            // Realizar la consulta a la API externa
            $response = $this->externalApiService->post('/creditos/tipo_creditos');

            Log::info('tip creditos', array_keys($response));
            // Verificar si la respuesta fue exitosa

            //aqui se valida con status no con success
            $isSuccess = ($response['status'] ?? true) && !isset($response['error']);

            if (!$isSuccess) {
                return ErrorResource::errorResponse('Error al consultar tipos de crédito', [
                    'trace' => [
                        'external_error' => $response['error'] ?? 'Error en el servicio externo',
                        'status_code' => $response['status_code'] ?? 500
                    ]
                ])->response()->setStatusCode(500);
            }

            $responseData = $response;

            // Verificar si la respuesta contiene error (para respuestas directas de API externa)
            if (isset($responseData['error']) && $responseData['error']) {
                Log::warning('API externa retornó error - tipos crédito', [
                    'error' => $responseData['error'] ?? 'Error desconocido',
                    'detail' => $responseData['detail'] ?? null
                ]);

                return ErrorResource::errorResponse('Error al consultar tipos de crédito', [
                    'external_error' => $responseData['error'] ?? 'Error desconocido',
                    'external_detail' => $responseData['detail'] ?? null
                ])->response()->setStatusCode(400);
            }

            Log::info('Tipos de crédito obtenidos exitosamente', ['count' => count($responseData['data'] ?? [])]);

            return ApiResource::success(
                $responseData['data'] ?? [],
                'Tipos de crédito obtenidos exitosamente'
            )
                ->response();
        } catch (\Exception $e) {
            return ErrorResource::errorResponse('Error interno al consultar tipos de crédito', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response()->setStatusCode(400);
        }
    }

    /**
     * Obtener información completa de líneas de crédito (parámetros + tipos)
     */
    #[OA\Get(
        path: '/lineas_credito/completo',
        tags: ['LineasCredito'],
        summary: 'Obtener líneas de crédito completas',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Líneas completas obtenidas'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function obtenerLineasCredito(): JsonResponse
    {
        try {
            Log::info('Consultando información completa de líneas de crédito');

            // Obtener parámetros generales
            $parametrosResponse = $this->obtenerParametros();
            $parametrosData = $parametrosResponse->getData(true);

            // Obtener tipos de crédito
            $tiposResponse = $this->obtenerTiposCreditos();
            $tiposData = $tiposResponse->getData(true);

            // Combinar respuestas
            $combinedData = [
                'parametros' => $parametrosData['data'] ?? [],
                'tipos_creditos' => $tiposData['data'] ?? [],
                'fecha_consulta' => now()->toISOString()
            ];

            // Verificar si ambas consultas fueron exitosas
            if (!$parametrosData['success'] || !$tiposData['success']) {
                $errors = [];

                if (!$parametrosData['success']) {
                    $errors['parametros'] = $parametrosData['error'] ?? 'Error desconocido';
                }

                if (!$tiposData['success']) {
                    $errors['tipos_creditos'] = $tiposData['error'] ?? 'Error desconocido';
                }

                return ErrorResource::errorResponse('Error al obtener información completa', $errors)
                    ->response()
                    ->setStatusCode(500);
            }

            Log::info('Información completa de líneas de crédito obtenida', [
                'parametros_count' => count($combinedData['parametros']),
                'tipos_count' => count($combinedData['tipos_creditos'])
            ]);

            return ApiResource::success($combinedData, 'Información de líneas de crédito obtenida exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al consultar información completa de líneas de crédito', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al consultar líneas de crédito', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Verificar disponibilidad del servicio externo
     */
    public function verificarDisponibilidad(): JsonResponse
    {
        try {
            Log::info('Verificando disponibilidad del servicio de líneas de crédito');

            $startTime = microtime(true);

            // Intentar una consulta simple
            $response = $this->externalApiService->get('/health');

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $isAvailable = $response['success'] ?? true;

            Log::info('Verificación de disponibilidad completada', [
                'available' => $isAvailable,
                'response_time' => $responseTime,
                'status' => $response['status_code'] ?? 500
            ]);

            return ApiResource::success([
                'available' => $isAvailable,
                'response_time_ms' => $responseTime,
                'status_code' => $response['status_code'] ?? 500,
                'timestamp' => now()->toISOString()
            ], $isAvailable ? 'Servicio disponible' : 'Servicio no disponible')->response();
        } catch (\Exception $e) {
            Log::error('Error al verificar disponibilidad del servicio', [
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error al verificar disponibilidad del servicio', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener estadísticas de líneas de crédito
     */
    public function obtenerEstadisticas(): JsonResponse
    {
        try {
            Log::info('Consultando estadísticas de líneas de crédito');

            // Obtener datos básicos
            $parametrosResponse = $this->obtenerParametros();
            $parametrosData = $parametrosResponse->getData(true);

            $tiposResponse = $this->obtenerTiposCreditos();
            $tiposData = $tiposResponse->getData(true);

            // Calcular estadísticas
            $estadisticas = [
                'total_parametros' => count($parametrosData['data'] ?? []),
                'total_tipos_credito' => count($tiposData['data'] ?? []),
                'fecha_consulta' => now()->toISOString(),
                'servicio_activo' => $parametrosData['success'] && $tiposData['success']
            ];

            // Agregar estadísticas adicionales si hay datos
            if (!empty($tiposData['data'])) {
                $tipos = $tiposData['data'];
                $estadisticas['tipos_activos'] = count(array_filter($tipos, function ($tipo) {
                    return ($tipo['estado'] ?? '') === 'activo';
                }));
            }

            Log::info('Estadísticas de líneas de crédito calculadas', $estadisticas);

            return ApiResource::success($estadisticas, 'Estadísticas obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de líneas de crédito', [
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error interno al obtener estadísticas', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }
}
