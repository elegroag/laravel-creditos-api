<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

                return $response->json();
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

    /**
     * Verificar si un PDF existe en la API Flask y guardarlo localmente
     */
    public function downloadPdfApi(string $filepath): array
    {
        try {
            Log::info('Verificando PDF en API Flask', [
                'filepath' => $filepath,
                'api_url' => $this->baseUrl
            ]);

            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->get($this->baseUrl . '/api/download-pdf', [
                    'filepath' => $filepath
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('PDF encontrado en API Flask', [
                    'filepath' => $filepath,
                    'filename' => $data['filename'] ?? null,
                    'size_bytes' => $data['size_bytes'] ?? null
                ]);

                // Guardar PDF en storage local
                $localPath = null;
                $guardadoExitoso = false;

                if (!empty($data['base64_content'])) {
                    try {
                        $localPath = $this->guardarPdfDesdeBase64($data['filename'], $data['base64_content']);
                        $guardadoExitoso = true;

                        Log::info('PDF guardado exitosamente en storage local', [
                            'filename' => $data['filename'],
                            'local_path' => $localPath,
                            'size_bytes' => $data['size_bytes'] ?? null
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error al guardar PDF en storage local', [
                            'filename' => $data['filename'],
                            'error' => $e->getMessage()
                        ]);

                        // Continuar aunque falle el guardado local
                        $localPath = null;
                        $guardadoExitoso = false;
                    }
                }

                return [
                    'success' => true,
                    'status' => $response->status(),
                    'data' => $data,
                    'filename' => $data['filename'] ?? null,
                    'size_bytes' => $data['size_bytes'] ?? null,
                    'base64_content' => $data['base64_content'] ?? null,
                    'existe' => true,
                    'local_path' => $localPath,
                    'guardado_local' => $guardadoExitoso
                ];
            } else {
                Log::warning('PDF no encontrado en API Flask', [
                    'filepath' => $filepath,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                return [
                    'success' => false,
                    'status' => $response->status(),
                    'error' => $response->json('error', 'Error desconocido'),
                    'existe' => false,
                    'local_path' => null,
                    'guardado_local' => false
                ];
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Error de conexión al verificar PDF en API Flask', [
                'filepath' => $filepath,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error de conexión con el servicio de PDF',
                'existe' => false,
                'local_path' => null,
                'guardado_local' => false
            ];
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Error de request al verificar PDF en API Flask', [
                'filepath' => $filepath,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error en la solicitud al servicio de PDF',
                'existe' => false,
                'local_path' => null,
                'guardado_local' => false
            ];
        } catch (\Exception $e) {
            Log::error('Error inesperado al verificar PDF en API Flask', [
                'filepath' => $filepath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno al verificar PDF',
                'existe' => false,
                'local_path' => null,
                'guardado_local' => false
            ];
        }
    }

    /**
     * Guardar PDF desde base64 en storage local
     */
    private function guardarPdfDesdeBase64(string $filename, string $base64Content): string
    {
        try {
            // Decodificar base64
            $pdfContent = base64_decode($base64Content);

            if ($pdfContent === false) {
                throw new \Exception('Error al decodificar contenido base64');
            }

            // Crear directorio si no existe
            $directory = 'pdfs/solicitudes';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            // Generar nombre de archivo único
            $timestamp = now()->format('YmdHis');
            $safeFilename = pathinfo($filename, PATHINFO_FILENAME);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $uniqueFilename = "{$safeFilename}_{$timestamp}.{$extension}";

            // Ruta completa del archivo
            $fullPath = "{$directory}/{$uniqueFilename}";

            // Guardar archivo en storage
            $saved = Storage::disk('public')->put($fullPath, $pdfContent);

            if (!$saved) {
                throw new \Exception('Error al guardar archivo en storage');
            }

            Log::info('PDF guardado exitosamente', [
                'original_filename' => $filename,
                'saved_path' => $fullPath,
                'size_bytes' => strlen($pdfContent)
            ]);

            return $fullPath;
        } catch (\Exception $e) {
            Log::error('Error al guardar PDF desde base64', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
