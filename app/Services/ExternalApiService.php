<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class ExternalApiService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.external_api.url', 'https://api.example.com');
        $this->username = config('services.external_api.user', '');
        $this->password = config('services.external_api.password', '');
        $this->timeout = config('services.external_api.timeout', 30);
    }

    /**
     * Realizar petición GET a la API externa
     */
    public function get(string $endpoint, array $params = []): array
    {
        try {
            Log::info('Realizando petición GET a API externa', [
                'endpoint' => $endpoint,
                'params' => $params
            ]);

            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]);

            if (!empty($params)) {
                $response = $response->get($url, $params);
            } else {
                $response = $response->get($url);
            }

            if (!$response->successful()) {
                Log::error('Error en respuesta GET de API externa', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Error en el servicio externo',
                    'status_code' => $response->status()
                ];
            }

            $responseData = $response->json();

            Log::info('Petición GET exitosa', [
                'endpoint' => $endpoint,
                'status' => $response->status()
            ]);

            return $responseData;

        } catch (\Exception $e) {
            Log::error('Error en petición GET a API externa', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error de conexión con el servicio externo',
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Realizar petición POST a la API externa
     */
    public function post(string $endpoint, array $data = []): array
    {
        try {
            Log::info('Realizando petición POST a API externa', [
                'endpoint' => $endpoint,
                'data_keys' => array_keys($data)
            ]);

            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->post($url, $data);

            if (!$response->successful()) {
                Log::error('Error en respuesta POST de API externa', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Error en el servicio externo',
                    'status_code' => $response->status()
                ];
            }

            $responseData = $response->json();

            Log::info('Petición POST exitosa', [
                'endpoint' => $endpoint,
                'status' => $response->status()
            ]);

            return $responseData;

        } catch (\Exception $e) {
            Log::error('Error en petición POST a API externa', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error de conexión con el servicio externo',
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Realizar petición PUT a la API externa
     */
    public function put(string $endpoint, array $data = []): array
    {
        try {
            Log::info('Realizando petición PUT a API externa', [
                'endpoint' => $endpoint,
                'data_keys' => array_keys($data)
            ]);

            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->put($url, $data);

            if (!$response->successful()) {
                Log::error('Error en respuesta PUT de API externa', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Error en el servicio externo',
                    'status_code' => $response->status()
                ];
            }

            $responseData = $response->json();

            Log::info('Petición PUT exitosa', [
                'endpoint' => $endpoint,
                'status' => $response->status()
            ]);

            return $responseData;

        } catch (\Exception $e) {
            Log::error('Error en petición PUT a API externa', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error de conexión con el servicio externo',
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Realizar petición DELETE a la API externa
     */
    public function delete(string $endpoint): array
    {
        try {
            Log::info('Realizando petición DELETE a API externa', [
                'endpoint' => $endpoint
            ]);

            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->delete($url);

            if (!$response->successful()) {
                Log::error('Error en respuesta DELETE de API externa', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Error en el servicio externo',
                    'status_code' => $response->status()
                ];
            }

            $responseData = $response->json();

            Log::info('Petición DELETE exitosa', [
                'endpoint' => $endpoint,
                'status' => $response->status()
            ]);

            return $responseData;

        } catch (\Exception $e) {
            Log::error('Error en petición DELETE a API externa', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error de conexión con el servicio externo',
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Verificar disponibilidad del servicio externo
     */
    public function verificarDisponibilidad(): bool
    {
        try {
            $response = Http::timeout(5)
                ->withBasicAuth($this->username, $this->password)
                ->get($this->baseUrl . '/health');

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Error verificando disponibilidad del servicio externo', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Obtener información del trabajador
     */
    public function obtenerInformacionTrabajador(string $cedtra): ?array
    {
        try {
            $response = $this->post('company/informacion_trabajador', [
                'cedtra' => $cedtra
            ]);

            if ($response['success'] && $response['data']) {
                return $response['data'];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error obteniendo información del trabajador', [
                'cedtra' => $cedtra,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Obtener usuarios de créditos
     */
    public function obtenerUsuariosCreditos(): array
    {
        try {
            $response = $this->get('creditos/usuarios_creditos');

            if ($response['success'] && $response['data']) {
                return $response['data'];
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Error obteniendo usuarios de créditos', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Obtener datos generales de créditos
     */
    public function obtenerDatosGeneralesCreditos(): array
    {
        try {
            $response = $this->post('creditos/datos_generales', [
                'headers' => [
                    'accept' => 'application/json',
                    'X-CSRF-TOKEN' => ''
                ]
            ]);

            if ($response['success'] && $response['data']) {
                return $response['data'];
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Error obteniendo datos generales de créditos', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Obtener tipos de crédito
     */
    public function obtenerTiposCredito(): array
    {
        try {
            $response = $this->post('creditos/tipo_creditos', [
                'headers' => [
                    'accept' => 'application/json',
                    'X-CSRF-TOKEN' => ''
                ]
            ]);

            if ($response['success'] && $response['data']) {
                return $response['data'];
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Error obteniendo tipos de crédito', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }
}
