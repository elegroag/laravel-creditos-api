<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class FirmaPlusService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $this->apiUrl = config('services.firma_plus.url', 'https://api.firmaplus.com');
        $this->apiKey = config('services.firma_plus.api_key', '');
        $this->timeout = config('services.firma_plus.timeout', 30);
    }

    /**
     * Enviar documento a FirmaPlus para firma digital
     */
    public function enviarDocumentoParaFirma(string $documentoPath, array $firmantes, array $metadata): array
    {
        try {
            Log::info('Enviando documento a FirmaPlus', [
                'documento_path' => $documentoPath,
                'num_firmantes' => count($firmantes),
                'metadata' => $metadata
            ]);

            // Preparar payload para FirmaPlus
            $payload = [
                'documento' => base64_encode(file_get_contents($documentoPath)),
                'nombre_documento' => basename($documentoPath),
                'firmantes' => $firmantes,
                'metadata' => $metadata,
                'callback_url' => config('app.url') . '/api/firmas/webhook'
            ];

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->post($this->apiUrl . '/documentos/enviar', $payload);

            if (!$response->successful()) {
                throw new \Exception('Error al enviar documento a FirmaPlus: ' . $response->body());
            }

            $resultado = $response->json();

            Log::info('Documento enviado a FirmaPlus exitosamente', [
                'transaccion_id' => $resultado['transaccion_id'] ?? null
            ]);

            return $resultado;

        } catch (\Exception $e) {
            Log::error('Error en FirmaPlusService::enviarDocumentoParaFirma', [
                'error' => $e->getMessage(),
                'documento_path' => $documentoPath
            ]);

            throw $e;
        }
    }

    /**
     * Consultar estado de documento en FirmaPlus
     */
    public function consultarEstadoDocumento(string $transaccionId): array
    {
        try {
            Log::info('Consultando estado en FirmaPlus', [
                'transaccion_id' => $transaccionId
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->get($this->apiUrl . '/documentos/' . $transaccionId . '/estado');

            if (!$response->successful()) {
                throw new \Exception('Error al consultar estado en FirmaPlus: ' . $response->body());
            }

            $estado = $response->json();

            Log::info('Estado consultado en FirmaPlus', [
                'transaccion_id' => $transaccionId,
                'estado' => $estado['estado'] ?? null
            ]);

            return $estado;

        } catch (\Exception $e) {
            Log::error('Error en FirmaPlusService::consultarEstadoDocumento', [
                'error' => $e->getMessage(),
                'transaccion_id' => $transaccionId
            ]);

            throw $e;
        }
    }

    /**
     * Descargar documento firmado desde FirmaPlus
     */
    public function descargarDocumentoFirmado(string $transaccionId, string $rutaDestino): void
    {
        try {
            Log::info('Descargando documento firmado desde FirmaPlus', [
                'transaccion_id' => $transaccionId,
                'ruta_destino' => $rutaDestino
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/pdf'
                ])
                ->get($this->apiUrl . '/documentos/' . $transaccionId . '/descargar');

            if (!$response->successful()) {
                throw new \Exception('Error al descargar documento firmado: ' . $response->body());
            }

            // Guardar el archivo
            file_put_contents($rutaDestino, $response->body());

            Log::info('Documento firmado descargado exitosamente', [
                'transaccion_id' => $transaccionId,
                'ruta_destino' => $rutaDestino,
                'size' => filesize($rutaDestino)
            ]);

        } catch (\Exception $e) {
            Log::error('Error en FirmaPlusService::descargarDocumentoFirmado', [
                'error' => $e->getMessage(),
                'transaccion_id' => $transaccionId,
                'ruta_destino' => $rutaDestino
            ]);

            throw $e;
        }
    }

    /**
     * Cancelar proceso de firmado
     */
    public function cancelarProcesoFirmado(string $transaccionId): array
    {
        try {
            Log::info('Cancelando proceso de firmado en FirmaPlus', [
                'transaccion_id' => $transaccionId
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->post($this->apiUrl . '/documentos/' . $transaccionId . '/cancelar');

            if (!$response->successful()) {
                throw new \Exception('Error al cancelar proceso en FirmaPlus: ' . $response->body());
            }

            $resultado = $response->json();

            Log::info('Proceso de firmado cancelado en FirmaPlus', [
                'transaccion_id' => $transaccionId
            ]);

            return $resultado;

        } catch (\Exception $e) {
            Log::error('Error en FirmaPlusService::cancelarProcesoFirmado', [
                'error' => $e->getMessage(),
                'transaccion_id' => $transaccionId
            ]);

            throw $e;
        }
    }

    /**
     * Obtener URL de firma para un firmante específico
     */
    public function obtenerUrlFirma(string $transaccionId, string $firmanteId): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->get($this->apiUrl . '/documentos/' . $transaccionId . '/firmantes/' . $firmanteId . '/url');

            if (!$response->successful()) {
                throw new \Exception('Error al obtener URL de firma: ' . $response->body());
            }

            $resultado = $response->json();

            return $resultado['url_firma'] ?? '';

        } catch (\Exception $e) {
            Log::error('Error en FirmaPlusService::obtenerUrlFirma', [
                'error' => $e->getMessage(),
                'transaccion_id' => $transaccionId,
                'firmante_id' => $firmanteId
            ]);

            throw $e;
        }
    }

    /**
     * Verificar si el servicio está disponible
     */
    public function verificarDisponibilidad(): bool
    {
        try {
            $response = Http::timeout(10)
                ->get($this->apiUrl . '/health');

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Error verificando disponibilidad de FirmaPlus', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
