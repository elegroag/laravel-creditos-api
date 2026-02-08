<?php

namespace App\Services;

use App\Models\SolicitudCredito;
use App\Models\SolicitudSolicitante;
use App\Models\SolicitudPayload;
use App\Models\FirmanteSolicitud;
use App\Models\EmpresaConvenio;
use Illuminate\Support\Facades\Log;
use Exception;

class PdfGenerationService
{
    /**
     * Constructor
     */
    public function __construct(
        private SolicitudService $solicitudService,
        private TrabajadorService $trabajadorService,
        private GeneradorPdfService $generadorPdfService
    ) {}

    /**
     * Genera PDF usando el servicio externo Flask API
     */
    public function generarPdfConApi(string $solicitudId): array
    {
        try {
            // Obtener datos completos de la solicitud
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                Log::error('Solicitud no encontrada', ['solicitud_id' => $solicitudId]);
                return [
                    'success' => false,
                    'error' => 'Solicitud no encontrada'
                ];
            }

            // Obtener datos relacionados
            /** @var \App\Models\SolicitudSolicitante $solicitante */
            $solicitante = $solicitud->solicitante;

            /** @var \App\Models\SolicitudPayload $payload */
            $payload = $solicitud->payload;

            /** @var \App\Models\FirmanteSolicitud $firmantes */
            $firmantes = $solicitud->firmantes;

            // Mapear datos del solicitante/trabajador
            $trabajadorData = $this->trabajadorService->obtenerDatosTrabajador($solicitante->numero_documento);

            /** @var \App\Models\EmpresaConvenio $convenioData */
            $convenioData = EmpresaConvenio::where('nit', $solicitante->nit)->first();

            // Mapear datos de firmantes
            /** @var \App\Models\FirmanteSolicitud[] $firmantesData */
            $firmantesData = [];

            if ($firmantes && $firmantes->count() > 0) {
                $firmantesData = $firmantes->map(function ($firmante) {
                    return $firmante->toApiArray();
                })->toArray();
            }

            // Preparar datos para cada sección
            $data = $this->prepararDatosParaPdf(
                $solicitud,
                $solicitante,
                $payload,
                $firmantesData,
                $convenioData,
                $trabajadorData,
                $solicitudId
            );

            // Llamar al servicio externo
            $resultado = $this->generadorPdfService->generarPdfCreditos($data);

            if (!$resultado['success']) {
                Log::error('Error al generar PDF con API Flask', [
                    'solicitud_id' => $solicitudId,
                    'error' => $resultado['error'] ?? 'Unknown error',
                    'status' => $resultado['status'] ?? 'N/A'
                ]);

                return [
                    'success' => false,
                    'error' => $resultado['error'] ?? 'Error al generar PDF',
                    'details' => ['api_error' => $resultado['response'] ?? []]
                ];
            }

            // Guardar información del PDF en la solicitud
            $pdfData = $resultado['data'] ?? [];

            if (!empty($pdfData)) {
                $this->guardarInfoPdfEnSolicitud($solicitudId, $pdfData);
            }

            Log::info('PDF generado exitosamente con API Flask', [
                'solicitud_id' => $solicitudId,
                'pdf_data' => $pdfData
            ]);

            return [
                'success' => true,
                'data' => $pdfData
            ];
        } catch (Exception $e) {
            Log::error('Error inesperado al generar PDF con API Flask', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno al generar PDF',
                'details' => ['exception' => $e->getMessage()]
            ];
        }
    }

    /**
     * Prepara todos los datos necesarios para la generación del PDF
     */
    private function prepararDatosParaPdf(
        SolicitudCredito $solicitud,
        SolicitudSolicitante $solicitante,
        SolicitudPayload $payload,
        array $firmantesData,
        ?EmpresaConvenio $convenioData,
        array $trabajadorData,
        string $solicitudId
    ): array {
        // Datos de referencias, deudas y propiedades
        $referenciasData = $payload->referencias ?? [];
        $deudasData = $payload->deudas ?? [];
        $propiedadesData = $payload->propiedades ?? [];

        // Datos del proceso de firmado
        $procesoFirmadoData = [
            'proveedor' => 'CAJA DE COMPENSACIÓN FAMILIAR DEL CAQUETÁ',
            'estado' => $solicitud->estado,
            'transaccion_id' => '0',
            'fecha_inicio' => now()->format('Y-m-d H:i:s')
        ];

        // Datos del encabezado
        $encabezadoData = [
            'fecha_radicado' => $solicitud->fecha_radicado,
            'solicitud_id' => $solicitudId,
        ];

        // Metadatos del PDF
        $pdfMetadataData = [
            'fecha_generacion' => now()->format('Y-m-d H:i:s'),
            'solicitud_id' => $solicitudId,
            'version' => '2.0'
        ];

        // Datos laborales y económicos
        $laboralData = $payload->informacion_laboral ?? [];
        $economicaData = $payload->informacion_economica ?? [];

        // Separar ingresos y descuentos
        $ingresosDescuentos = json_to_array($payload->ingresos_descuentos ?? '{}');
        $ingresosData = $ingresosDescuentos['ingresos'] ?? [];
        $descuentosData = $ingresosDescuentos['descuentos'] ?? [];

        // Datos del cónyuge
        $conyugeData = $payload->conyuge ?? [];

        return [
            'solicitud' => $solicitud->toApiArray(),
            'solicitante' => $solicitante->toApiArray(),
            'laboral' => $laboralData,
            'economica' => $economicaData,
            'ingresos' => $ingresosData,
            'descuentos' => $descuentosData,
            'conyuge' => $conyugeData,
            "referencias" => $referenciasData,
            "deudas" => $deudasData,
            "propiedades" => $propiedadesData,
            "firmantes" => $firmantesData,
            "convenio" => $convenioData?->toApiArray() ?? null,
            "proceso_firmado" => $procesoFirmadoData,
            "encabezado" => $encabezadoData,
            "pdf_metadata" => $pdfMetadataData,
            'trabajador' => $trabajadorData
        ];
    }

    /**
     * Guarda la información del PDF en la solicitud
     */
    private function guardarInfoPdfEnSolicitud(string $solicitudId, array $pdfData): void
    {
        try {
            $solicitud = $this->solicitudService->getById($solicitudId);

            if ($solicitud) {
                $pdfInfo = [
                    'api_content' => $pdfData['api_content'] ?? null,
                    'api_path' => $pdfData['api_path'] ?? null,
                    'api_filename' => $pdfData['api_filename'] ?? null,
                    'generated_at' => now()->toISOString(),
                    'generated_by' => 'pdf_generation_service'
                ];

                // Actualizar el campo pdf_generado en la solicitud
                $solicitud->update([
                    'pdf_generado' => json_encode($pdfInfo)
                ]);

                Log::info('Información del PDF guardada en la solicitud', [
                    'solicitud_id' => $solicitudId,
                    'pdf_filename' => $pdfData['api_filename'] ?? null
                ]);
            }
        } catch (Exception $e) {
            Log::error('Error al guardar información del PDF en la solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verifica si una solicitud tiene PDF generado
     */
    public function tienePdfGenerado(string $solicitudId): bool
    {
        try {
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return false;
            }

            $pdfInfo = $solicitud->pdf_generado;

            if (!$pdfInfo) {
                return false;
            }

            $pdfData = json_decode($pdfInfo, true);

            return !empty($pdfData['api_content']) && !empty($pdfData['api_path']);
        } catch (Exception $e) {
            Log::error('Error al verificar si existe PDF generado', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Obtiene la información del PDF generado para una solicitud
     */
    public function obtenerInfoPdf(string $solicitudId): ?array
    {
        try {
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return null;
            }

            $pdfInfo = $solicitud->pdf_generado;

            if (!$pdfInfo) {
                return null;
            }

            return json_decode($pdfInfo, true);
        } catch (Exception $e) {
            Log::error('Error al obtener información del PDF', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Elimina la información del PDF de una solicitud
     */
    public function eliminarPdf(string $solicitudId): bool
    {
        try {
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return false;
            }

            // Obtener información del PDF antes de eliminar
            $pdfInfo = $this->obtenerInfoPdf($solicitudId);

            if ($pdfInfo && !empty($pdfInfo['api_path'])) {
                // Eliminar archivo físico si existe
                if (file_exists($pdfInfo['api_path'])) {
                    unlink($pdfInfo['api_path']);
                }
            }

            // Limpiar campo pdf_generado
            $solicitud->update(['pdf_generado' => null]);

            Log::info('PDF eliminado de la solicitud', [
                'solicitud_id' => $solicitudId
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Error al eliminar PDF de la solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
