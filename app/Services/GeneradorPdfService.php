<?php

namespace App\Services;

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
            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->post($this->baseUrl . '/api/creditos/generate-pdf', $data);

            if ($response->successful()) {
                return $response->json();
            } else {
                return [
                    'success' => false,
                    'error' => 'Error en API externa',
                    'status' => $response->status(),
                    'response' => $response->body()
                ];
            }
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => 'Error de conexión con API externa',
                'exception' => $e->getMessage()
            ];
        } catch (\Exception $e) {
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
            $response = Http::timeout($this->timeout)
                ->withBasicAuth($this->username, $this->password)
                ->get($this->baseUrl . '/api/download-pdf', [
                    'filepath' => $filepath
                ]);

            if ($response->successful()) {
                $data = $response->json();

                $localPath = null;
                $guardadoExitoso = false;

                if (!empty($data['base64_content'])) {
                    try {
                        $localPath = $this->guardarPdfDesdeBase64($data['filename'], $data['base64_content']);
                        $guardadoExitoso = true;
                    } catch (\Exception $e) {
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
            return [
                'success' => false,
                'error' => 'Error de conexión con el servicio de PDF',
                'existe' => false,
                'local_path' => null,
                'guardado_local' => false
            ];
        } catch (\Illuminate\Http\Client\RequestException $e) {
            return [
                'success' => false,
                'error' => 'Error en la solicitud al servicio de PDF',
                'existe' => false,
                'local_path' => null,
                'guardado_local' => false
            ];
        } catch (\Exception $e) {
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
            $pdfContent = base64_decode($base64Content);

            if ($pdfContent === false) {
                throw new \Exception('Error al decodificar contenido base64');
            }

            $directory = 'pdfs/solicitudes';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $timestamp = now()->format('YmdHis');
            $safeFilename = pathinfo($filename, PATHINFO_FILENAME);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $uniqueFilename = "{$safeFilename}_{$timestamp}.{$extension}";

            $fullPath = "{$directory}/{$uniqueFilename}";

            $saved = Storage::disk('public')->put($fullPath, $pdfContent);

            if (!$saved) {
                throw new \Exception('Error al guardar archivo en storage');
            }

            return $fullPath;
        } catch (\Exception $e) {
            throw new \Exception('Error al guardar PDF: ' . $e->getMessage());
        }
    }
}
