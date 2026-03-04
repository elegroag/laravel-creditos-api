<?php

namespace App\Services;

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
            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

            $httpRequest = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]);

            if (!empty($params)) {
                $response = $httpRequest->get($url, $params);
            } else {
                $response = $httpRequest->get($url);
            }

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Error en el servicio externo',
                    'status_code' => $response->status()
                ];
            }

            $responseData = $response->json();

            return $responseData;
        } catch (\Exception $e) {
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
            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->post($url, $data);


            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Error en el servicio externo',
                    'status_code' => $response->status()
                ];
            }

            $responseData = $response->json();

            return $responseData;
        } catch (\Exception $e) {
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
            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

            $response = Http::post($url, $data)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Error en el servicio externo',
                    'status_code' => $response->status()
                ];
            }

            $responseData = $response->json();

            return $responseData;
        } catch (\Exception $e) {
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
            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

            $response = Http::delete($url)
                ->withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Error en el servicio externo',
                    'status_code' => $response->status()
                ];
            }

            $responseData = $response->json();

            return $responseData;
        } catch (\Exception $e) {
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
            $response = Http::get($this->baseUrl . '/health')
                ->withBasicAuth($this->username, $this->password);

            return $response->successful();
        } catch (\Exception $e) {
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
            return [];
        }
    }
}
