<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\EmpresaConvenio;
use App\Models\SolicitudCredito;
use App\Services\SolicitudService;
use App\Services\GeneradorPdfService;
use App\Services\TrabajadorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SolicitudPdfController extends Controller
{
    protected SolicitudService $solicitudService;
    protected GeneradorPdfService $generadorPdfService;
    protected TrabajadorService $trabajadorService;

    public function __construct(
        SolicitudService $solicitudService,
        GeneradorPdfService $generadorPdfService,
        TrabajadorService $trabajadorService
    ) {
        $this->solicitudService = $solicitudService;
        $this->generadorPdfService = $generadorPdfService;
        $this->trabajadorService = $trabajadorService;
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
            $resultado = $this->generarPdfConApi($solicitudId);

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
    public function descargarPdfSolicitud(Request $request, string $solicitudId): JsonResponse
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

            // Verificar si tiene PDF generado
            $pdfData = $solicitud->pdf_generado ?? null;

            if (!$pdfData || empty($pdfData['path'])) {
                return ErrorResource::errorResponse('La solicitud no tiene un PDF generado. Use el endpoint /generar-pdf primero.')
                    ->response()
                    ->setStatusCode(400);
            }

            $pdfPath = $pdfData['path'];

            // Intentar obtener el PDF desde la API Flask usando el filename
            try {
                $filename = $pdfData['filename'] ?? null;

                if (!$filename) {
                    return ErrorResource::errorResponse('No se encontró el nombre del archivo PDF')
                        ->response()->setStatusCode(400);
                }

                $verificacion = $this->generadorPdfService->verificarPdf($filename);

                if ($verificacion['existe'] && !empty($verificacion['base64_content'])) {
                    // Retornar el PDF en base64 para que el frontend lo descargue
                    return ApiResource::success([
                        'base64_content' => $verificacion['base64_content'],
                        'filename' => $filename,
                        'size_bytes' => $verificacion['size_bytes'],
                        'content_type' => 'application/pdf'
                    ], 'PDF obtenido exitosamente')->response();
                } else {
                    return ErrorResource::errorResponse('El archivo PDF no se encuentra en el servidor', [
                        'api_response' => $verificacion
                    ])->response()->setStatusCode(404);
                }
            } catch (\Exception $e) {
                Log::error('Error al obtener PDF desde API Flask', [
                    'solicitud_id' => $solicitudId,
                    'error' => $e->getMessage()
                ]);

                return ErrorResource::errorResponse('Error al obtener el PDF del servidor', [
                    'error' => $e->getMessage()
                ])->response()->setStatusCode(500);
            }
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

            $pdfData = $solicitud->pdf_generado ?? null;

            $estado = [
                'solicitud_id' => $solicitudId,
                'tiene_pdf' => !empty($pdfData),
                'pdf_generado' => null
            ];

            if ($pdfData) {
                $pdfPath = $pdfData['path'] ?? null;
                $filename = $pdfData['filename'] ?? null;

                // Verificar si el PDF existe usando la API Flask con el filename (no el path completo)
                $pdfExiste = false;
                $pdfInfo = null;

                if ($filename) {
                    // Usar solo el filename para verificar con la API Flask
                    $verificacion = $this->generadorPdfService->verificarPdf($filename);
                    $pdfExiste = $verificacion['existe'] ?? false;

                    if ($pdfExiste) {
                        $pdfInfo = [
                            'filename' => $verificacion['filename'] ?? $filename,
                            'generado_en' => $pdfData['generado_en'] ?? null,
                            'archivo_existe' => true,
                            'path' => $verificacion['local_path'] ?? $filename,
                            'tamano' => $verificacion['size_bytes'] ?? $pdfData['tamano'] ?? null,
                            'url_descarga' => $verificacion['local_path'] ? Storage::url($verificacion['local_path']) : "/api/solicitud-pdf/{$solicitudId}/download",
                            'verificado_api' => true,
                            'guardado_local' => $verificacion['guardado_local'] ?? false,
                            'api_response' => [
                                'success' => $verificacion['success'] ?? false,
                                'size_bytes' => $verificacion['size_bytes'] ?? null,
                                'local_path' => $verificacion['local_path'] ?? null
                            ]
                        ];

                        // Actualizar la base de datos si el PDF se guardó localmente
                        if ($verificacion['guardado_local'] && $verificacion['local_path']) {
                            try {
                                $solicitud = SolicitudCredito::where('numero_solicitud', $solicitudId)->first();
                                if ($solicitud) {
                                    $updatedPdfData = array_merge($pdfData, [
                                        'path' => $verificacion['local_path'],
                                        'tamano' => $verificacion['size_bytes'],
                                        'guardado_local' => true,
                                        'guardado_en' => Carbon::now()->toISOString()
                                    ]);
                                    $solicitud->update([
                                        'pdf_generado' => $updatedPdfData,
                                        'updated_at' => Carbon::now()
                                    ]);

                                    Log::info('Base de datos actualizada con path local del PDF', [
                                        'solicitud_id' => $solicitudId,
                                        'local_path' => $verificacion['local_path']
                                    ]);
                                }
                            } catch (\Exception $e) {
                                Log::error('Error al actualizar base de datos con path local', [
                                    'solicitud_id' => $solicitudId,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    } else {
                        $pdfInfo = [
                            'filename' => $filename,
                            'generado_en' => $pdfData['generado_en'] ?? null,
                            'archivo_existe' => false,
                            'path' => $filename,
                            'tamano' => $pdfData['tamano'] ?? null,
                            'url_descarga' => null,
                            'verificado_api' => true,
                            'guardado_local' => false,
                            'api_response' => [
                                'success' => $verificacion['success'] ?? false,
                                'error' => $verificacion['error'] ?? 'Error desconocido'
                            ]
                        ];
                    }
                } else {
                    // Fallback a verificación local si no hay filename
                    $archivoExiste = $pdfPath ? Storage::disk('public')->exists($pdfPath) : false;
                    $pdfInfo = [
                        'filename' => $pdfData['filename'] ?? null,
                        'generado_en' => $pdfData['generado_en'] ?? null,
                        'archivo_existe' => $archivoExiste,
                        'path' => $archivoExiste ? Storage::url($pdfPath) : null,
                        'tamano' => $pdfData['tamano'] ?? null,
                        'url_descarga' => $archivoExiste ? Storage::url($pdfPath) : null,
                        'verificado_api' => false
                    ];
                }

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
     * Genera PDF usando el servicio externo Flask API
     */
    private function generarPdfConApi(string $solicitudId): array
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
            $solicitante = $solicitud->solicitante;
            $payload = $solicitud->payload;
            $firmantes = $solicitud->firmantes;

            // Mapear datos del solicitante/trabajador
            $trabajadorData = $this->trabajadorService->obtenerDatosTrabajador($solicitante->numero_documento);

            $incluirConvenio = true;
            $convenioData = EmpresaConvenio::where('nit', $solicitante->nit)->first();


            // Mapear datos de firmantes
            $firmantesData = [];
            $incluirFirmantes = false;

            if ($firmantes && $firmantes->count() > 0) {
                $incluirFirmantes = true;
                $firmantesData = $firmantes->map(function ($firmante) {
                    return [
                        'nombre_completo' => $firmante->nombre_completo,
                        'numero_documento' => $firmante->numero_documento,
                        'email' => $firmante->email,
                        'rol' => $firmante->rol,
                        'tipo' => $firmante->tipo,
                        'orden' => $firmante->orden
                    ];
                })->toArray();
            }

            // Preparar datos para la API Flask
            $data = [
                'solicitud_id' => $solicitudId,
                'solicitud_data' => $solicitud,
                'trabajador_data' => $trabajadorData,
                'incluir_convenio' => $incluirConvenio,
                'incluir_firmantes' => $incluirFirmantes,
                'convenio_data' => $convenioData,
                'firmantes_data' => $firmantesData
            ];

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
        } catch (\Exception $e) {
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
     * Guarda información del PDF en la solicitud
     */
    private function guardarInfoPdfEnSolicitud(string $solicitudId, array $pdfData): void
    {
        try {
            $solicitud = SolicitudCredito::where('numero_solicitud', $solicitudId)->first();

            if (!$solicitud) {
                Log::warning('No se encontró la solicitud para guardar info del PDF', [
                    'solicitud_id' => $solicitudId
                ]);
                return;
            }

            $pdfInfo = [
                'filename' => $pdfData['pdf_filename'] ?? null,
                'path' => $pdfData['pdf_path'] ?? null,
                'generado_en' => $pdfData['fecha_generacion'] ?? Carbon::now()->toISOString(),
                'tamano' => null, // No viene en la respuesta, se podría calcular después
                'solicitud_id' => $pdfData['solicitud_id'] ?? null,
                'tiene_convenio' => $pdfData['tiene_convenio'] ?? false,
                'cantidad_firmantes' => $pdfData['cantidad_firmantes'] ?? 0
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
