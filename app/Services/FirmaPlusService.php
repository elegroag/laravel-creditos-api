<?php

namespace App\Services;

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
            // Preparar payload para FirmaPlus
            $payload = [
                'documento' => base64_encode(file_get_contents($documentoPath)),
                'nombre_documento' => basename($documentoPath),
                'firmantes' => $firmantes,
                'metadata' => $metadata,
                'callback_url' => config('app.url') . '/api/firmas/webhook'
            ];

            $response = Http::post($this->apiUrl . '/documentos/enviar', $payload)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ]);

            if (!$response->successful()) {
                throw new \Exception('Error al enviar documento a FirmaPlus: ' . $response->body());
            }

            $resultado = $response->json();

            return $resultado;
        } catch (\Exception $e) {
            throw new \Exception('Error al enviar documento a FirmaPlus: ' . $e->getMessage());
        }
    }

    /**
     * Consultar estado de documento en FirmaPlus
     */
    public function consultarEstadoDocumento(string $transaccionId): array
    {
        try {
            $response = Http::get($this->apiUrl . '/documentos/' . $transaccionId . '/estado')
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ]);

            if (!$response->successful()) {
                throw new \Exception('Error al consultar estado en FirmaPlus: ' . $response->body());
            }

            $estado = $response->json();

            return $estado;
        } catch (\Exception $e) {
            throw new \Exception('Error al consultar estado del documento: ' . $e->getMessage());
        }
    }

    /**
     * Descargar documento firmado desde FirmaPlus
     */
    public function descargarDocumentoFirmado(string $transaccionId, string $rutaDestino): void
    {
        try {
            $response = Http::get($this->apiUrl . '/documentos/' . $transaccionId . '/descargar')
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/pdf'
                ]);

            if (!$response->successful()) {
                throw new \Exception('Error al descargar documento firmado: ' . $response->body());
            }

            // Guardar el archivo
            file_put_contents($rutaDestino, $response->body());
        } catch (\Exception $e) {
            throw new \Exception('Error al descargar documento firmado: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar proceso de firmado
     */
    public function cancelarProcesoFirmado(string $transaccionId): array
    {
        try {
            $response = Http::post($this->apiUrl . '/documentos/' . $transaccionId . '/cancelar')
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ]);

            if (!$response->successful()) {
                throw new \Exception('Error al cancelar proceso en FirmaPlus: ' . $response->body());
            }

            $resultado = $response->json();

            return $resultado;
        } catch (\Exception $e) {
            throw new \Exception('Error al cancelar proceso de firmado: ' . $e->getMessage());
        }
    }

    /**
     * Obtener URL de firma para un firmante específico
     */
    public function obtenerUrlFirma(string $transaccionId, string $firmanteId): string
    {
        try {
            $response = Http::get($this->apiUrl . '/documentos/' . $transaccionId . '/firmantes/' . $firmanteId . '/url')
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ]);

            if (!$response->successful()) {
                throw new \Exception('Error al obtener URL de firma: ' . $response->body());
            }

            $resultado = $response->json();

            return $resultado['url_firma'] ?? '';
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener URL de firma: ' . $e->getMessage());
        }
    }

    /**
     * Verificar si el servicio está disponible
     */
    public function verificarDisponibilidad(): bool
    {
        try {
            $response = Http::get($this->apiUrl . '/health');

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validar estructura del payload del webhook
     *
     * @param array $data
     * @return array Lista de errores de validación (vacío si es válido)
     */
    public function validarPayloadWebhook(array $data): array
    {
        $errores = [];

        if (empty($data['transaccion_id'])) {
            $errores[] = 'transaccion_id es requerido';
        }

        if (empty($data['solicitud_id'])) {
            $errores[] = 'solicitud_id es requerido';
        }

        if (empty($data['estado'])) {
            $errores[] = 'estado es requerido';
        }

        $estadosValidos = ['FIRMADO', 'RECHAZADO', 'EXPIRADO', 'CANCELADO'];
        if (!empty($data['estado']) && !in_array($data['estado'], $estadosValidos)) {
            $errores[] = "estado debe ser uno de: " . implode(', ', $estadosValidos);
        }

        return $errores;
    }

    /**
     * Descargar documento firmado con reintentos y backoff exponencial
     *
     * @param string $transaccionId
     * @param string $rutaDestino
     * @param int $maxReintentos
     * @return bool True si se descargó exitosamente
     */
    public function descargarDocumentoFirmadoConReintentos(
        string $transaccionId,
        string $rutaDestino,
        int $maxReintentos = 3
    ): bool {
        $intento = 0;
        $ultimoError = null;

        while ($intento < $maxReintentos) {
            try {
                $this->descargarDocumentoFirmado($transaccionId, $rutaDestino);

                // Verificar que el archivo se descargó correctamente
                if (file_exists($rutaDestino) && filesize($rutaDestino) > 0) {
                    return true;
                }

                throw new \Exception('Archivo descargado está vacío o no existe');
            } catch (\Exception $e) {
                $ultimoError = $e->getMessage();
                $intento++;

                if ($intento < $maxReintentos) {
                    $waitSeconds = pow(2, $intento);

                    // Esperar antes de reintentar (backoff exponencial: 2, 4, 8 segundos)
                    sleep($waitSeconds);
                } else {
                    throw new \Exception("Falló descarga de PDF firmado después de {$maxReintentos} intentos: {$ultimoError}");
                }
            }
        }

        return false;
    }

    /**
     * Verificar integridad del PDF descargado
     *
     * @param string $rutaPdf
     * @return bool True si el PDF es válido
     */
    public function verificarIntegridadPdf(string $rutaPdf): bool
    {
        if (!file_exists($rutaPdf)) {
            return false;
        }

        $size = filesize($rutaPdf);

        // Verificar tamaño mínimo (1 KB)
        if ($size < 1024) {
            return false;
        }

        // Verificar que sea un PDF válido (magic bytes %PDF-)
        $handle = fopen($rutaPdf, 'r');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            return false;
        }

        return true;
    }
}
