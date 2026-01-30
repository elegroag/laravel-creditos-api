<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudCredito;
use App\Services\SolicitudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SolicitudPdfController extends Controller
{
    protected SolicitudService $solicitudService;

    public function __construct(SolicitudService $solicitudService)
    {
        $this->solicitudService = $solicitudService;
    }

    /**
     * Genera el PDF de una solicitud de crédito con soporte para convenios y firmantes.
     * Este endpoint reemplaza el antiguo '/api/solicitud-credito/xml' (deprecado).
     */
    public function generarPdfSolicitud(Request $request, string $solicitudId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                    'details' => []
                ], 401);
            }

            // Validar parámetros opcionales
            $validator = Validator::make($request->all(), [
                'incluir_convenio' => 'sometimes|boolean',
                'incluir_firmantes' => 'sometimes|boolean'
            ], [
                'incluir_convenio.boolean' => 'El campo incluir_convenio debe ser booleano',
                'incluir_firmantes.boolean' => 'El campo incluir_firmantes debe ser booleano'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $incluirConvenio = $data['incluir_convenio'] ?? true;
            $incluirFirmantes = $data['incluir_firmantes'] ?? true;

            Log::info('Generando PDF de solicitud', [
                'solicitud_id' => $solicitudId,
                'incluir_convenio' => $incluirConvenio,
                'incluir_firmantes' => $incluirFirmantes,
                'username' => $user->username
            ]);

            // Verificar que la solicitud existe
            $solicitud = $this->solicitudService->getById($solicitudId);
            
            if (!$solicitud) {
                return response()->json([
                    'success' => false,
                    'error' => "Solicitud no encontrada: {$solicitudId}",
                    'details' => []
                ], 404);
            }

            // Verificar permisos (admin o propietario)
            $userRoles = $user->roles ?? [];
            $isAdmin = in_array('admin', $userRoles);
            
            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $user->username) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado para generar PDF de esta solicitud',
                    'details' => []
                ], 403);
            }

            // Generar PDF usando script Python
            $resultado = $this->generarPdfConScript($solicitudId, $incluirConvenio, $incluirFirmantes);

            if (!$resultado['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Error al generar PDF',
                    'details' => $resultado['error'] ?? []
                ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'PDF generado exitosamente y solicitud enviada para validación',
                'data' => $resultado['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Error inesperado al generar PDF', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al generar el PDF',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Descarga el PDF previamente generado de una solicitud.
     */
    public function descargarPdfSolicitud(string $solicitudId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                    'details' => []
                ], 401);
            }

            Log::info('Descargando PDF de solicitud', [
                'solicitud_id' => $solicitudId,
                'username' => $user->username
            ]);

            // Verificar que la solicitud existe
            $solicitud = $this->solicitudService->getById($solicitudId);
            
            if (!$solicitud) {
                return response()->json([
                    'success' => false,
                    'error' => "Solicitud no encontrada: {$solicitudId}",
                    'details' => []
                ], 404);
            }

            // Verificar permisos (admin o propietario)
            $userRoles = $user->roles ?? [];
            $isAdmin = in_array('admin', $userRoles);
            
            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $user->username) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado para descargar PDF de esta solicitud',
                    'details' => []
                ], 403);
            }

            // Verificar si tiene PDF generado
            $pdfData = $solicitud['pdf_generado'] ?? null;

            if (!$pdfData || empty($pdfData['path'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'La solicitud no tiene un PDF generado. Use el endpoint /generar-pdf primero.',
                    'details' => []
                ], 404);
            }

            $pdfPath = $pdfData['path'];

            // Verificar que el archivo existe
            if (!Storage::disk('public')->exists($pdfPath)) {
                Log::error('Archivo PDF no existe en el sistema de archivos', [
                    'solicitud_id' => $solicitudId,
                    'pdf_path' => $pdfPath
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'El archivo PDF no se encuentra en el servidor',
                    'details' => []
                ], 404);
            }

            // Generar URL de descarga
            $downloadUrl = Storage::url($pdfPath);
            $filename = $pdfData['filename'] ?? "solicitud_{$solicitudId}.pdf";

            Log::info('URL de descarga de PDF generada', [
                'solicitud_id' => $solicitudId,
                'download_url' => $downloadUrl,
                'filename' => $filename
            ]);

            return response()->json([
                'success' => true,
                'message' => 'URL de descarga generada',
                'data' => [
                    'download_url' => $downloadUrl,
                    'filename' => $filename,
                    'content_type' => 'application/pdf'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al descargar PDF', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al descargar el PDF',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Verifica si una solicitud tiene PDF generado y retorna su estado.
     */
    public function verificarEstadoPdf(string $solicitudId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                    'details' => []
                ], 401);
            }

            Log::info('Verificando estado del PDF', [
                'solicitud_id' => $solicitudId,
                'username' => $user->username
            ]);

            // Verificar que la solicitud existe
            $solicitud = $this->solicitudService->getById($solicitudId);
            
            if (!$solicitud) {
                return response()->json([
                    'success' => false,
                    'error' => "Solicitud no encontrada: {$solicitudId}",
                    'details' => []
                ], 404);
            }

            // Verificar permisos (admin o propietario)
            $userRoles = $user->roles ?? [];
            $isAdmin = in_array('admin', $userRoles);
            
            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $user->username) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado para verificar estado de PDF de esta solicitud',
                    'details' => []
                ], 403);
            }

            $pdfData = $solicitud['pdf_generado'] ?? null;

            $estado = [
                'solicitud_id' => $solicitudId,
                'tiene_pdf' => !empty($pdfData),
                'pdf_generado' => null
            ];

            if ($pdfData) {
                $pdfPath = $pdfData['path'] ?? null;
                $archivoExiste = $pdfPath ? Storage::disk('public')->exists($pdfPath) : false;

                $estado['pdf_generado'] = [
                    'filename' => $pdfData['filename'] ?? null,
                    'generado_en' => $pdfData['generado_en'] ?? null,
                    'archivo_existe' => $archivoExiste,
                    'path' => $archivoExiste ? Storage::url($pdfPath) : null,
                    'tamano' => $pdfData['tamano'] ?? null,
                    'url_descarga' => $archivoExiste ? Storage::url($pdfPath) : null
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Estado del PDF obtenido exitosamente',
                'data' => $estado
            ]);

        } catch (\Exception $e) {
            Log::error('Error verificando estado del PDF', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al verificar el estado del PDF',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Genera PDF usando script Python
     */
    private function generarPdfConScript(string $solicitudId, bool $incluirConvenio, bool $incluirFirmantes): array
    {
        try {
            // Ruta al script Python
            $scriptPath = base_path('scripts/generate_pdf.py');
            
            if (!file_exists($scriptPath)) {
                Log::error('Script Python no encontrado', ['script_path' => $scriptPath]);
                return [
                    'success' => false,
                    'error' => 'Script de generación de PDF no encontrado'
                ];
            }

            // Preparar parámetros para el script
            $params = [
                'solicitud_id' => $solicitudId,
                'incluir_convenio' => $incluirConvenio,
                'incluir_firmantes' => $incluirFirmantes,
                'output_dir' => storage_path('app/public/pdfs/solicitudes'),
                'db_host' => config('database.connections.mongodb.host', 'localhost'),
                'db_port' => config('database.connections.mongodb.port', 27017),
                'db_name' => config('database.connections.mongodb.database', 'comfaca_credito')
            ];

            // Crear directorio de salida si no existe
            $outputDir = storage_path('app/public/pdfs/solicitudes');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Construir comando Python
            $pythonCommand = 'python3';
            $command = [
                $pythonCommand,
                $scriptPath,
                json_encode($params)
            ];

            Log::info('Ejecutando script Python para generar PDF', [
                'script_path' => $scriptPath,
                'params' => $params
            ]);

            // Ejecutar script Python
            $process = new Process($command);
            $process->setTimeout(300); // 5 minutos timeout
            $process->run();

            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                Log::error('Error al ejecutar script Python', [
                    'error_output' => $errorOutput,
                    'exit_code' => $process->getExitCode()
                ]);

                return [
                    'success' => false,
                    'error' => 'Error al ejecutar script de generación de PDF',
                    'details' => ['script_error' => $errorOutput]
                ];
            }

            $output = $process->getOutput();
            $resultado = json_decode($output, true);

            if (!$resultado || !isset($resultado['success'])) {
                Log::error('Respuesta inválida del script Python', ['output' => $output]);
                return [
                    'success' => false,
                    'error' => 'Respuesta inválida del script de generación de PDF'
                ];
            }

            if (!$resultado['success']) {
                Log::error('Script Python reportó error', ['error' => $resultado['error'] ?? 'Unknown error']);
                return [
                    'success' => false,
                    'error' => $resultado['error'] ?? 'Error desconocido en script Python'
                ];
            }

            // Guardar información del PDF en la solicitud
            $pdfData = $resultado['data'] ?? [];
            if (!empty($pdfData)) {
                $this->guardarInfoPdfEnSolicitud($solicitudId, $pdfData);
            }

            Log::info('PDF generado exitosamente con script Python', [
                'solicitud_id' => $solicitudId,
                'pdf_data' => $pdfData
            ]);

            return [
                'success' => true,
                'data' => $resultado['data'] ?? []
            ];

        } catch (ProcessFailedException $e) {
            Log::error('Excepción al ejecutar script Python', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error al ejecutar script Python',
                'details' => ['exception' => $e->getMessage()]
            ];

        } catch (\Exception $e) {
            Log::error('Error inesperado al generar PDF con script', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno al generar PDF',
                'details' => ['internal_error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Guarda información del PDF en la solicitud
     */
    private function guardarInfoPdfEnSolicitud(string $solicitudId, array $pdfData): void
    {
        try {
            $solicitud = SolicitudCredito::find($solicitudId);
            
            if (!$solicitud) {
                Log::warning('No se encontró la solicitud para guardar info del PDF', [
                    'solicitud_id' => $solicitudId
                ]);
                return;
            }

            $pdfInfo = [
                'filename' => $pdfData['filename'] ?? null,
                'path' => $pdfData['path'] ?? null,
                'generado_en' => Carbon::now()->toISOString(),
                'tamano' => $pdfData['tamano'] ?? null,
                'incluir_convenio' => $pdfData['incluir_convenio'] ?? false,
                'incluir_firmantes' => $pdfData['incluir_firmantes'] ?? false
            ];

            $solicitud->update([
                'pdf_generado' => $pdfInfo,
                'updated_at' => Carbon::now()
            ]);

            Log::info('Información del PDF guardada en solicitud', [
                'solicitud_id' => $solicitudId,
                'pdf_info' => $pdfInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar información del PDF en solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Eliminar PDF de una solicitud
     */
    public function eliminarPdfSolicitud(string $solicitudId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                    'details' => []
                ], 401);
            }

            Log::info('Eliminando PDF de solicitud', [
                'solicitud_id' => $solicitudId,
                'username' => $user->username
            ]);

            // Verificar que la solicitud existe
            $solicitud = $this->solicitudService->getById($solicitudId);
            
            if (!$solicitud) {
                return response()->json([
                    'success' => false,
                    'error' => "Solicitud no encontrada: {$solicitudId}",
                    'details' => []
                ], 404);
            }

            // Verificar permisos (admin o propietario)
            $userRoles = $user->roles ?? [];
            $isAdmin = in_array('admin', $userRoles);
            
            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $user->username) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado para eliminar PDF de esta solicitud',
                    'details' => []
                ], 403);
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
            $solicitudModel = SolicitudCredito::find($solicitudId);
            $solicitudModel->update([
                'pdf_generado' => null,
                'updated_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PDF eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar PDF de solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al eliminar PDF',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de PDFs generados
     */
    public function obtenerEstadisticasPdf(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                    'details' => []
                ], 401);
            }

            $username = $user->username;
            $userRoles = $user->roles ?? [];
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

            return response()->json([
                'success' => true,
                'message' => 'Estadísticas de PDFs obtenidas exitosamente',
                'data' => $estadisticas
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de PDFs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener estadísticas',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }
}
