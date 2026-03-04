<?php

namespace App\Services;

use App\Models\DocumentoPostulante;
use App\Models\SolicitudCredito;
use App\Models\SolicitudSolicitante;
use App\Models\SolicitudPayload;
use App\Models\FirmanteSolicitud;
use App\Models\EmpresaConvenio;
use App\Models\TipoDocumento;
use Exception;
use Illuminate\Support\Facades\Storage;

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
                return [
                    'success' => false,
                    'error' => $resultado['error'] ?? 'Error al generar PDF',
                    'details' => ['api_error' => $resultado['response'] ?? []]
                ];
            }

            // Guardar información del PDF en la solicitud
            $pdfData = $resultado['data'] ?? [];

            if (!empty($pdfData)) {
                $this->guardarInfoPdfEnSolicitud($solicitud, $pdfData);
            }

            return [
                'success' => true,
                'data' => $pdfData
            ];
        } catch (Exception $e) {
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
        $ingresosDescuentos = $payload->toApiArrayIngresosDescuentos();

        // Datos del cónyuge
        $conyugeData = $payload->conyuge ?? [];

        $payloadApi = [
            'solicitud_id' => $solicitudId,
            'solicitud' => $solicitud->toApiArray(),
            'solicitante' => $solicitante->toApiArray(),
            'laboral' => $laboralData,
            'economica' => $economicaData,
            'ingresos' => $ingresosDescuentos['ingresos'],
            'descuentos' => $ingresosDescuentos['descuentos'],
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
        return $payloadApi;
    }

    /**
     * Guarda la información del PDF en la solicitud
     */
    private function guardarInfoPdfEnSolicitud($solicitud, array $pdfData): DocumentoPostulante|null
    {

        if (!$solicitud)  return null;

        $pdfInfo = [
            'api_path' => $pdfData['api_path'] ?? null,
            'api_filename' => $pdfData['api_filename'] ?? null,
            'generated_at' => now()->toISOString(),
            'generated_by' => 'pdf_generation_service'
        ];

        // Actualizar el campo pdf_generado en la solicitud
        $solicitud->update([
            'pdf_generado' => json_encode($pdfInfo)
        ]);

        $dataSaved = $this->guardarPdfDesdeBase64(
            $pdfData['api_filename'] ?? null,
            $pdfData['api_content'],
            $solicitud->numero_solicitud
        );

        //si existe el DocumentoPostulante se debe borrar
        DocumentoPostulante::where('solicitud_id', $solicitud->numero_solicitud)
            ->where('tipo_documento', 'pdf')
            ->delete();

        //es necesario guardar en DocumentoPostulante
        $tipoPdf = TipoDocumento::porTipo('pdf');
        $data = [
            'username' => $solicitud->owner_username,
            'tipo_documento' => $tipoPdf->tipo,
            'nombre_original' => $pdfData['api_filename'] ?? null,
            'saved_filename' => $dataSaved['saved_filename'] ?? null,
            'tipo_mime' => $dataSaved['tipo_mime'] ?? null,
            'tamano_bytes' => $dataSaved['tamano_bytes'] ?? null,
            'ruta_archivo' => $dataSaved['ruta_archivo'] ?? null,
            'api_path' => $pdfData['api_path'] ?? null,
            'api_filename' => $pdfData['api_filename'] ?? null,
            'solicitud_id' => $solicitud->numero_solicitud,
            'activo' => 1
        ];

        $dataSaved = DocumentoPostulante::create($data);
        return $dataSaved;
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

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function guardarPdfDesdeBase64(string $filename, string $base64Content, string $solicitudId): array
    {
        // Decodificar base64
        $pdfContent = base64_decode($base64Content);

        if ($pdfContent === false) {
            throw new Exception('Error al decodificar contenido base64');
        }

        // Guardar en la misma carpeta de documentos de la solicitud
        if (!Storage::disk('public')->exists($solicitudId)) Storage::disk('public')->makeDirectory($solicitudId);

        $filePath = "{$solicitudId}/{$filename}";

        // Guardar archivo en storage
        $saved = Storage::disk('public')->put($filePath, $pdfContent);

        if (!$saved) {
            throw new Exception('Error al guardar archivo en storage');
        }

        return [
            'tamano_bytes' => strlen($pdfContent),
            'tipo_mime' => 'application/pdf',
            'ruta_archivo' => $filePath,
            'saved_filename' => basename($filename)
        ];
    }
}
