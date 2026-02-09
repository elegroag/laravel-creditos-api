<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\DocumentoPostulante;
use App\Models\SolicitudCredito;
use App\Services\SolicitudService;
use App\Services\GeneradorPdfService;
use App\Services\TrabajadorService;
use App\Services\PdfGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Carbon\Carbon;

class SolicitudPdfController extends Controller
{
    protected SolicitudService $solicitudService;
    protected GeneradorPdfService $generadorPdfService;
    protected TrabajadorService $trabajadorService;
    protected PdfGenerationService $pdfGenerationService;


    public function __construct(
        SolicitudService $solicitudService,
        GeneradorPdfService $generadorPdfService,
        TrabajadorService $trabajadorService,
        PdfGenerationService $pdfGenerationService
    ) {
        $this->solicitudService = $solicitudService;
        $this->generadorPdfService = $generadorPdfService;
        $this->trabajadorService = $trabajadorService;
        $this->pdfGenerationService = $pdfGenerationService;
    }

    /**
     * Obtiene los datos del usuario autenticado desde JWT middleware
     */
    private function getAuthenticatedUser(Request $request): array
    {
        $authenticatedUser = $request->get('authenticated_user');
        return $authenticatedUser['user'] ?? [];
    }

    /**
     * Genera el PDF de una solicitud de crédito con soporte para convenios y firmantes.
     * Este endpoint reemplaza el antiguo '/api/solicitud-credito/xml' (deprecado).
     */
    public function generarPdfSolicitud(Request $request, string $solicitudId): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('admin', $userRoles);

            // Verificar que la solicitud existe
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            // Verificar permisos (admin o propietario)
            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('admin', $userRoles);

            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para generar PDF de esta solicitud')->response();
            }

            // Generar PDF usando API python
            $resultado = $this->pdfGenerationService->generarPdfConApi($solicitudId);

            if (!$resultado['success']) {
                return ErrorResource::errorResponse('Error al generar PDF', $resultado['error'] ?? [])
                    ->response()
                    ->setStatusCode(500);
            }

            // Actualizar estado de la solicitud a ENVIADO_VALIDACION
            try {
                $this->solicitudService->updateEstado(
                    $solicitudId,
                    'ENVIADO_VALIDACION',
                    'PDF generado exitosamente, enviado para validación de asesores'
                );

                Log::info('PDF generado y estado actualizado', [
                    'solicitud_id' => $solicitudId,
                    'nuevo_estado' => 'ENVIADO_VALIDACION'
                ]);
            } catch (\Exception $e) {
                Log::warning('No se pudo actualizar el estado de la solicitud', [
                    'solicitud_id' => $solicitudId,
                    'error' => $e->getMessage()
                ]);
            }

            return ApiResource::success($resultado['data'], 'PDF generado exitosamente y solicitud enviada para validación')->response();
        } catch (\Exception $e) {
            Log::error('Error inesperado al generar PDF', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al generar el PDF', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Descarga el PDF previamente generado de una solicitud.
     */
    public function descargarPdfSolicitud(Request $request, string $solicitudId): BinaryFileResponse|JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            Log::info('Descargando PDF de solicitud', [
                'solicitud_id' => $solicitudId,
                'username' => $username
            ]);

            // Verificar que la solicitud existe
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            // Verificar permisos (admin o propietario)
            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('admin', $userRoles);

            if (!$isAdmin && ($solicitud->owner_username ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para descargar PDF de esta solicitud')->response();
            }

            // Verificar si tiene PDF generado usando DocumentoPostulante
            $pdfData = DocumentoPostulante::where("solicitud_id", $solicitudId)->first();

            if (!$pdfData || empty($pdfData->ruta_archivo)) {
                return ErrorResource::errorResponse('La solicitud no tiene un PDF generado. Use el endpoint /generar-pdf primero.')
                    ->response()
                    ->setStatusCode(400);
            }

            // Verificar si el archivo existe físicamente
            $pdfPath = $pdfData->ruta_archivo;
            $fullPath = storage_path("app/public/{$pdfPath}");

            if (!file_exists($fullPath)) {
                return ErrorResource::errorResponse('El archivo PDF no se encuentra en el servidor')
                    ->response()
                    ->setStatusCode(404);
            }

            // Leer el archivo y convertir a base64
            $pdfContent = file_get_contents($fullPath);
            if ($pdfContent === false) {
                return ErrorResource::errorResponse('Error al leer el archivo PDF')
                    ->response()
                    ->setStatusCode(500);
            }

            $base64Content = base64_encode($pdfContent);
            $filename = $pdfData->saved_filename ?? 'solicitud.pdf';
            $sizeBytes = filesize($fullPath);

            // Retornar el archivo PDF directamente
            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => $sizeBytes
            ]);
        } catch (\Exception $e) {
            Log::error('Error al descargar PDF', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error al descargar el PDF', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Verifica si una solicitud tiene PDF generado y retorna su estado.
     */
    public function verificarEstadoPdf(Request $request, string $solicitudId): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            Log::info('Verificando estado del PDF', [
                'solicitud_id' => $solicitudId,
                'username' => $username
            ]);

            // Verificar que la solicitud existe
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            // Verificar permisos (admin o propietario)
            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('admin', $userRoles);

            if (!$isAdmin && ($solicitud->owner_username ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para verificar estado de PDF de esta solicitud')->response();
            }

            $pdfData = DocumentoPostulante::where("solicitud_id", $solicitudId)
                ->where("activo", 1)
                ->where("tipo_documento", 'pdf')
                ->first();

            Log::info('PDF Data', [
                'pdfData' => $pdfData
            ]);

            $estado = [
                'solicitud_id' => $solicitudId,
                'tiene_pdf' => !empty($pdfData),
                'pdf_generado' => null
            ];

            if ($pdfData) {
                $path = $pdfData->ruta_archivo ?? null;
                $filename = $pdfData->saved_filename ?? null;

                // Verificar si el archivo existe físicamente en storage
                $archivoExiste = false;
                $tamanoArchivo = null;

                if ($path) {
                    $fullPath = storage_path("app/public/{$path}");
                    $archivoExiste = file_exists($fullPath);
                    if ($archivoExiste) {
                        $tamanoArchivo = filesize($fullPath);
                    }
                }

                $pdfInfo = [
                    'filename' => $filename,
                    'archivo_existe' => $archivoExiste,
                    'path' => $path,
                    'url_descarga' => $archivoExiste ? "/api/solicitud-pdf/{$solicitudId}/download" : "",
                    'verificado_api' => $archivoExiste ? true : false,
                    'tamano_bytes' => $tamanoArchivo,
                    'tipo_documento' => $pdfData->tipo_documento,
                    'api_path' => $pdfData->api_path,
                    'api_filename' => $pdfData->api_filename,
                    'created_at' => $pdfData->created_at?->toISOString(),
                    'updated_at' => $pdfData->updated_at?->toISOString()
                ];

                $estado['pdf_generado'] = $pdfInfo;
            }

            return ApiResource::success($estado, 'Estado del PDF obtenido exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error verificando estado del PDF', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error al verificar el estado del PDF', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Eliminar PDF de una solicitud
     */
    public function eliminarPdfSolicitud(Request $request, string $solicitudId): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            // Verificar que la solicitud existe
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            // Verificar permisos (admin o propietario)
            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('admin', $userRoles);

            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para eliminar PDF de esta solicitud')->response();
            }

            // Eliminar archivo PDF si existe
            $pdfData = $solicitud['pdf_generado'] ?? null;
            if ($pdfData && !empty($pdfData['path'])) {
                $pdfPath = $pdfData['path'];
                if (Storage::disk('public')->exists($pdfPath)) {
                    Storage::disk('public')->delete($pdfPath);
                    Log::info('Archivo PDF eliminado', ['pdf_path' => $pdfPath]);
                }
            }

            // Limpiar información del PDF en la solicitud
            $solicitudModel = SolicitudCredito::where('numero_solicitud', $solicitudId)->first();
            $solicitudModel->update([
                'pdf_generado' => null,
                'updated_at' => Carbon::now()
            ]);

            return ApiResource::success(null, 'PDF eliminado exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al eliminar PDF de solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al eliminar PDF', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener estadísticas de PDFs generados
     */
    public function obtenerEstadisticasPdf(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('admin', $userRoles);

            Log::info('Obteniendo estadísticas de PDFs', [
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            $query = SolicitudCredito::query();

            // Si no es admin, filtrar por usuario
            if (!$isAdmin) {
                $query->where('owner_username', $username);
            }

            $solicitudes = $query->get();

            $estadisticas = [
                'total_solicitudes' => $solicitudes->count(),
                'con_pdf' => 0,
                'sin_pdf' => 0,
                'tamano_total' => 0,
                'tamano_promedio' => 0,
                'por_fecha' => [],
                'tipos_incluidos' => [
                    'con_convenio' => 0,
                    'con_firmantes' => 0
                ]
            ];

            foreach ($solicitudes as $solicitud) {
                $pdfData = $solicitud->pdf_generado ?? null;

                if ($pdfData) {
                    $estadisticas['con_pdf']++;
                    $estadisticas['tamano_total'] += $pdfData['tamano'] ?? 0;

                    if ($pdfData['incluir_convenio'] ?? false) {
                        $estadisticas['tipos_incluidos']['con_convenio']++;
                    }

                    if ($pdfData['incluir_firmantes'] ?? false) {
                        $estadisticas['tipos_incluidos']['con_firmantes']++;
                    }

                    // Agrupar por fecha
                    $fecha = Carbon::parse($pdfData['generado_en'])->format('Y-m-d');
                    if (!isset($estadisticas['por_fecha'][$fecha])) {
                        $estadisticas['por_fecha'][$fecha] = 0;
                    }
                    $estadisticas['por_fecha'][$fecha]++;
                } else {
                    $estadisticas['sin_pdf']++;
                }
            }

            if ($estadisticas['con_pdf'] > 0) {
                $estadisticas['tamano_promedio'] = round($estadisticas['tamano_total'] / $estadisticas['con_pdf'], 2);
            }

            // Ordenar por fecha
            krsort($estadisticas['por_fecha']);

            return ApiResource::success($estadisticas, 'Estadísticas de PDFs obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de PDFs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al obtener estadísticas', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }
}
