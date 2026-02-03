<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

class GeneradorPdfService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.generador_pdf_api.url', 'http://localhost:8080');
        $this->username = config('services.generador_pdf_api.user', '');
        $this->password = config('services.generador_pdf_api.password', '');
        $this->timeout = config('services.generador_pdf_api.timeout', 30);
    }

    /**
     * Generar PDF usando la API Flask
     */
    public function generarPdfCreditos(array $data): array
    {
        try {
            Log::info('Enviando solicitud a API Flask para generar PDF', [
                'endpoint' => '/api/creditos/generate-pdf',
                'data_keys' => array_keys($data)
            ]);

            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->post($this->baseUrl . '/api/creditos/generate-pdf', $data);

            if ($response->successful()) {
                Log::info('PDF generado exitosamente via Flask API', [
                    'status' => $response->status(),
                    'response_size' => strlen($response->body())
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                    'status' => $response->status()
                ];
            } else {
                Log::error('Error en respuesta de Flask API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ]);

                return [
                    'success' => false,
                    'error' => 'Error en API externa',
                    'status' => $response->status(),
                    'response' => $response->body()
                ];
            }
        } catch (RequestException $e) {
            Log::error('Excepción al conectar con Flask API', [
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return [
                'success' => false,
                'error' => 'Error de conexión con API externa',
                'exception' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Error inesperado en GeneradorPdfService', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno',
                'exception' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar salud de la API Flask
     */
    public function verificarSalud(): array
    {
        try {
            $response = Http::timeout(5)
                ->get($this->baseUrl . '/api/health');

            return [
                'healthy' => $response->successful(),
                'status' => $response->status(),
                'response' => $response->json()
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener URL base de la API
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
