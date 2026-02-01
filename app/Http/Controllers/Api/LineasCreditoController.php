<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class LineasCreditoController extends Controller
{
    protected string $externalApiUrl;
    protected string $externalApiUser;
    protected string $externalApiPassword;
    protected int $timeout;

    public function __construct()
    {
        $this->externalApiUrl = config('services.external_api.url', 'https://api.example.com');
        $this->externalApiUser = config('services.external_api.user', '');
        $this->externalApiPassword = config('services.external_api.password', '');
        $this->timeout = config('services.external_api.timeout', 8);
    }

    /**
     * Obtener parámetros generales de líneas de crédito
     */
    public function obtenerParametros(): JsonResponse
    {
        try {
            Log::info('Consultando parámetros generales de líneas de crédito');

            // Preparar headers
            $headers = [
                'accept' => 'application/json',
                'X-CSRF-TOKEN' => '',
                'Content-Type' => 'application/json'
            ];

            // Realizar la consulta a la API externa
            $response = Http::post($this->externalApiUrl . '/creditos/datos_generales')->timeout($this->timeout)
                ->withBasicAuth($this->externalApiUser, $this->externalApiPassword)
                ->withHeaders($headers);

            // Verificar si la respuesta fue exitosa
            if (!$response->successful()) {
                Log::error('Error en respuesta de API externa - parámetros', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al consultar datos generales',
                    'details' => [
                        'external_error' => 'Error en el servicio externo',
                        'status_code' => $response->status()
                    ]
                ], 500);
            }

            $responseData = $response->json();

            // Verificar si la respuesta contiene error
            if (!$responseData['success'] ?? true) {
                Log::warning('API externa retornó error - parámetros', [
                    'error' => $responseData['error'] ?? 'Error desconocido',
                    'detail' => $responseData['detail'] ?? null
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al consultar datos generales',
                    'details' => [
                        'external_error' => $responseData['error'] ?? 'Error desconocido',
                        'external_detail' => $responseData['detail'] ?? null
                    ]
                ], 400);
            }

            Log::info('Parámetros generales obtenidos exitosamente', [
                'data_keys' => array_keys($responseData['data'] ?? [])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Datos generales obtenidos exitosamente',
                'data' => $responseData['data'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Error al consultar parámetros generales', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al consultar datos generales',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtener tipos de crédito disponibles
     */
    public function obtenerTiposCreditos(): JsonResponse
    {
        try {
            Log::info('Consultando tipos de crédito disponibles');

            // Preparar headers
            $headers = [
                'accept' => 'application/json',
                'X-CSRF-TOKEN' => '',
                'Content-Type' => 'application/json'
            ];

            // Realizar la consulta a la API externa
            $response = Http::post($this->externalApiUrl . '/creditos/tipo_creditos')->timeout($this->timeout)
                ->withBasicAuth($this->externalApiUser, $this->externalApiPassword)
                ->withHeaders($headers);

            // Verificar si la respuesta fue exitosa
            if (!$response->successful()) {
                Log::error('Error en respuesta de API externa - tipos crédito', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al consultar tipos de crédito',
                    'details' => [
                        'external_error' => 'Error en el servicio externo',
                        'status_code' => $response->status()
                    ]
                ], 500);
            }

            $responseData = $response->json();

            // Verificar si la respuesta contiene error
            if (!$responseData['success'] ?? true) {
                Log::warning('API externa retornó error - tipos crédito', [
                    'error' => $responseData['error'] ?? 'Error desconocido',
                    'detail' => $responseData['detail'] ?? null
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al consultar tipos de crédito',
                    'details' => [
                        'external_error' => $responseData['error'] ?? 'Error desconocido',
                        'external_detail' => $responseData['detail'] ?? null
                    ]
                ], 400);
            }

            Log::info('Tipos de crédito obtenidos exitosamente', [
                'data_keys' => array_keys($responseData['data'] ?? []),
                'count' => count($responseData['data'] ?? [])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipos de crédito obtenidos exitosamente',
                'data' => $responseData['data'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Error al consultar tipos de crédito', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al consultar tipos de crédito',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtener información completa de líneas de crédito (parámetros + tipos)
     */
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

                return response()->json([
                    'success' => false,
                    'error' => 'Error al obtener información completa',
                    'details' => $errors
                ], 500);
            }

            Log::info('Información completa de líneas de crédito obtenida', [
                'parametros_count' => count($combinedData['parametros']),
                'tipos_count' => count($combinedData['tipos_creditos'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Información de líneas de crédito obtenida exitosamente',
                'data' => $combinedData
            ]);
        } catch (\Exception $e) {
            Log::error('Error al consultar información completa de líneas de crédito', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al consultar líneas de crédito',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
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
            $response = Http::get($this->externalApiUrl . '/health')
                ->withBasicAuth($this->externalApiUser, $this->externalApiPassword)
                ->timeout(5);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $isAvailable = $response->successful();

            Log::info('Verificación de disponibilidad completada', [
                'available' => $isAvailable,
                'response_time' => $responseTime,
                'status' => $response->status()
            ]);

            return response()->json([
                'success' => true,
                'message' => $isAvailable ? 'Servicio disponible' : 'Servicio no disponible',
                'data' => [
                    'available' => $isAvailable,
                    'response_time_ms' => $responseTime,
                    'status_code' => $response->status(),
                    'timestamp' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al verificar disponibilidad del servicio', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al verificar disponibilidad del servicio',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Estadísticas obtenidas exitosamente',
                'data' => $estadisticas
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de líneas de crédito', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener estadísticas',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }
}
