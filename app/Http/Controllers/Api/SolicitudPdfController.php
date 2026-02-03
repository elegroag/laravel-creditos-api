<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\SolicitudCredito;
use App\Services\SolicitudService;
use App\Services\GeneradorPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SolicitudPdfController extends Controller
{
    protected SolicitudService $solicitudService;
    protected GeneradorPdfService $generadorPdfService;

    public function __construct(SolicitudService $solicitudService, GeneradorPdfService $generadorPdfService)
    {
        $this->solicitudService = $solicitudService;
        $this->generadorPdfService = $generadorPdfService;
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

            // Generar PDF usando script Python
            $resultado = $this->generarPdfConScript($solicitudId);

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

            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para descargar PDF de esta solicitud')->response();
            }

            // Verificar si tiene PDF generado
            $pdfData = $solicitud['pdf_generado'] ?? null;

            if (!$pdfData || empty($pdfData['path'])) {
                return ErrorResource::errorResponse('La solicitud no tiene un PDF generado. Use el endpoint /generar-pdf primero.')
                    ->response()
                    ->setStatusCode(400);
            }

            $pdfPath = $pdfData['path'];

            // Verificar que el archivo existe
            if (!Storage::disk('public')->exists($pdfPath)) {
                Log::error('Archivo PDF no existe en el sistema de archivos', [
                    'solicitud_id' => $solicitudId,
                    'pdf_path' => $pdfPath
                ]);

                return ErrorResource::errorResponse('El archivo PDF no se encuentra en el servidor')
                    ->response()
                    ->setStatusCode(404);
            }

            // Generar URL de descarga
            $downloadUrl = Storage::url($pdfPath);
            $filename = $pdfData['filename'] ?? "solicitud_{$solicitudId}.pdf";

            Log::info('URL de descarga de PDF generada', [
                'solicitud_id' => $solicitudId,
                'download_url' => $downloadUrl,
                'filename' => $filename
            ]);

            return ApiResource::success([
                'download_url' => $downloadUrl,
                'filename' => $filename
            ], 'URL de descarga generada')->response();
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

            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para verificar estado de PDF de esta solicitud')->response();
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
    private function generarPdfConScript(string $solicitudId): array
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

            // Mapear datos de la solicitud
            $solicitudData = [
                'numero_solicitud' => $solicitud->numero_solicitud,
                'monto_solicitado' => $solicitud->monto_solicitado,
                'monto_aprobado' => $solicitud->monto_aprobado,
                'plazo_meses' => $solicitud->plazo_meses,
                'tasa_interes' => $solicitud->tasa_interes,
                'destino_credito' => $solicitud->destino_credito,
                'descripcion' => $solicitud->descripcion,
                'estado' => $solicitud->estado,
                'created_at' => $solicitud->created_at->toISOString(),
                'updated_at' => $solicitud->updated_at->toISOString()
            ];

            // Mapear datos del solicitante/trabajador
            $trabajadorData = [];
            if ($solicitante) {
                $trabajadorData = [
                    'nombre_completo' => $solicitante->nombre_completo ?? '',
                    'tipo_documento' => $solicitante->tipo_documento ?? '',
                    'numero_documento' => $solicitante->numero_documento ?? '',
                    'email' => $solicitante->email ?? '',
                    'telefono' => $solicitante->telefono ?? '',
                    'direccion' => $solicitante->direccion ?? '',
                    'ciudad' => $solicitante->ciudad ?? '',
                    'departamento' => $solicitante->departamento ?? '',
                    'cargo' => $solicitante->cargo ?? '',
                    'empresa' => $solicitante->empresa ?? '',
                    'salario' => $solicitante->salario ?? 0,
                    'tipo_contrato' => $solicitante->tipo_contrato ?? ''
                ];
            }

            // Mapear datos del payload (información adicional)
            if ($payload) {
                $payloadData = json_decode($payload->payload, true) ?? [];
                $trabajadorData = array_merge($trabajadorData, $payloadData);
            }

            // Mapear datos de convenio (si aplica)
            $convenioData = [];
            $incluirConvenio = false;

            // Aquí puedes agregar lógica para determinar si incluye convenio
            // Por ejemplo, si el trabajador pertenece a una empresa con convenio
            if ($solicitante && !empty($solicitante->convenio_id)) {
                $incluirConvenio = true;
                $convenioData = [
                    'nombre_convenio' => $solicitante->convenio->nombre ?? '',
                    'codigo_convenio' => $solicitante->convenio->codigo ?? '',
                    'tasa_descuento' => $solicitante->convenio->tasa_descuento ?? 0,
                    'empresa_convenio' => $solicitante->convenio->empresa ?? ''
                ];
            }

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
                'solicitud_data' => $solicitudData,
                'trabajador_data' => $trabajadorData,
                'incluir_convenio' => $incluirConvenio,
                'incluir_firmantes' => $incluirFirmantes,
                'convenio_data' => $convenioData,
                'firmantes_data' => $firmantesData
            ];

            Log::info('Enviando datos a API Flask para generar PDF', [
                'solicitud_id' => $solicitudId,
                'data_keys' => array_keys($data),
                'has_solicitante' => !empty($trabajadorData),
                'has_firmantes' => $incluirFirmantes,
                'has_convenio' => $incluirConvenio
            ]);

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
            $pdfData = $resultado['data']['data'] ?? [];
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
            $solicitudModel = SolicitudCredito::find($solicitudId);
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
