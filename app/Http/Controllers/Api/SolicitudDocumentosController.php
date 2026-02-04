<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\SolicitudCredito;
use App\Models\SolicitudDocumento;
use App\Services\SolicitudService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SolicitudDocumentosController extends Controller
{
    protected SolicitudService $solicitudService;
    protected UserService $userService;

    public function __construct(SolicitudService $solicitudService, UserService $userService)
    {
        $this->solicitudService = $solicitudService;
        $this->userService = $userService;
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
     * Lista los documentos requeridos para una solicitud según el tipo de crédito.
     */
    public function listarDocumentosRequeridos(Request $request, string $solicitudId): JsonResponse
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

            Log::info('Listando documentos requeridos', [
                'solicitud_id' => $solicitudId,
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            // Verificar que la solicitud existe y permisos
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para ver esta solicitud')->response();
            }

            // Obtener tipo de crédito desde el payload
            $payload = $solicitud['payload'] ?? [];
            $lineaCredito = $payload['linea_credito'] ?? [];
            $tipoCredito = $lineaCredito['tipcre'] ?? '';
            $detalleModalidad = $lineaCredito['detalle_modalidad'] ?? '';

            // Definir documentos requeridos según el tipo de crédito
            $documentosRequeridos = $this->obtenerDocumentosPorTipoCredito($detalleModalidad);

            Log::info('Documentos requeridos obtenidos', [
                'solicitud_id' => $solicitudId,
                'tipo_credito' => $tipoCredito,
                'detalle_modalidad' => $detalleModalidad,
                'total_documentos' => count($documentosRequeridos)
            ]);

            return ApiResource::success($documentosRequeridos, 'Documentos requeridos obtenidos exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al listar documentos requeridos', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al listar documentos requeridos', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Lista los documentos de una solicitud.
     */
    public function listarDocumentosSolicitud(Request $request, string $solicitudId): JsonResponse
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

            Log::info('Listando documentos de solicitud', [
                'solicitud_id' => $solicitudId,
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            // Verificar que la solicitud existe y permisos
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para ver esta solicitud')->response();
            }

            // Obtener documentos
            $documentos = $solicitud['documentos'] ?? [];

            Log::info('Documentos de solicitud obtenidos', [
                'solicitud_id' => $solicitudId,
                'total_documentos' => count($documentos)
            ]);

            return ApiResource::success($documentos, 'Documentos obtenidos exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al listar documentos de solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al listar documentos', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Agrega un documento a una solicitud.
     */
    public function agregarDocumentoSolicitud(Request $request, string $solicitudId): JsonResponse
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

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'documento_requerido_id' => 'required|string|max:100',
                'documento' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png'
            ], [
                'documento_requerido_id.required' => 'El ID del documento requerido es obligatorio',
                'documento.required' => 'El archivo es requerido',
                'documento.max' => 'El archivo no puede exceder 10MB',
                'documento.mimes' => 'El archivo debe ser PDF, JPG o PNG'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();
            $documentoRequeridoId = $data['documento_requerido_id'];
            $file = $request->file('documento');

            Log::info('Agregando documento a solicitud', [
                'solicitud_id' => $solicitudId,
                'username' => $username,
                'documento_requerido_id' => $documentoRequeridoId,
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType()
            ]);

            // Verificar que la solicitud existe y permisos
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para modificar esta solicitud')->response();
            }

            // Preparar datos del documento
            $fileData = [
                'documento_requerido_id' => $documentoRequeridoId,
                'nombre_original' => $file->getClientOriginalName(),
                'tipo_mime' => $file->getMimeType(),
                'tamano' => $file->getSize(),
                'fecha_subida' => Carbon::now()->toISOString()
            ];

            // Generar nombre único para el archivo
            $fileName = $this->generarNombreArchivo($solicitudId, $documentoRequeridoId, $file->getClientOriginalExtension());

            // Guardar archivo en storage
            $filePath = $file->storeAs('documentos/solicitudes', $fileName, 'public');

            if (!$filePath) {
                throw new \Exception('No se pudo guardar el archivo');
            }

            $fileData['ruta_archivo'] = $filePath;
            $fileData['id'] = Str::uuid()->toString();

            // Agregar documento a la solicitud
            $solicitudActualizada = $this->agregarDocumentoASolicitud($solicitudId, $fileData);

            Log::info('Documento agregado exitosamente', [
                'solicitud_id' => $solicitudId,
                'documento_id' => $fileData['id'],
                'ruta_archivo' => $filePath
            ]);

            return ApiResource::success($solicitudActualizada, 'Documento agregado exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al agregar documento a solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al agregar documento', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Elimina un documento de una solicitud.
     */
    public function eliminarDocumentoSolicitud(Request $request, string $solicitudId, string $documentoId): JsonResponse
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

            Log::info('Eliminando documento de solicitud', [
                'solicitud_id' => $solicitudId,
                'documento_id' => $documentoId,
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            // Verificar que la solicitud existe y permisos
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para modificar esta solicitud')->response();
            }

            // Eliminar documento
            $solicitudActualizada = $this->eliminarDocumentoDeSolicitud($solicitudId, $documentoId);

            if (!$solicitudActualizada) {
                return ErrorResource::notFound("Documento no encontrado: {$documentoId}")->response();
            }

            Log::info('Documento eliminado exitosamente', [
                'solicitud_id' => $solicitudId,
                'documento_id' => $documentoId
            ]);

            return ApiResource::success($solicitudActualizada, 'Documento eliminado exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al eliminar documento de solicitud', [
                'solicitud_id' => $solicitudId,
                'documento_id' => $documentoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al eliminar documento', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Descargar un documento de una solicitud
     */
    public function descargarDocumento(Request $request, string $solicitudId, string $documentoId): JsonResponse
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

            Log::info('Descargando documento de solicitud', [
                'solicitud_id' => $solicitudId,
                'documento_id' => $documentoId,
                'username' => $username
            ]);

            // Verificar que la solicitud existe y permisos
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para ver esta solicitud')->response();
            }

            // Buscar documento
            $documentos = $solicitud['documentos'] ?? [];
            $documento = null;

            foreach ($documentos as $doc) {
                if ($doc['id'] === $documentoId) {
                    $documento = $doc;
                    break;
                }
            }

            if (!$documento) {
                return ErrorResource::notFound("Documento no encontrado: {$documentoId}")->response();
            }

            // Verificar que el archivo exista
            $filePath = $documento['ruta_archivo'] ?? '';

            if (!Storage::disk('public')->exists($filePath)) {
                return ErrorResource::errorResponse('Archivo no encontrado en el sistema')
                    ->response()
                    ->setStatusCode(404);
            }

            // Obtener URL de descarga
            $downloadUrl = Storage::url($filePath);

            Log::info('URL de descarga generada', [
                'solicitud_id' => $solicitudId,
                'documento_id' => $documentoId,
                'download_url' => $downloadUrl
            ]);

            return ApiResource::success([
                'download_url' => $downloadUrl,
                'nombre_original' => $documento['nombre_original'] ?? '',
                'tipo_mime' => $documento['tipo_mime'] ?? '',
                'tamano' => $documento['tamano'] ?? 0
            ], 'URL de descarga generada')->response();
        } catch (\Exception $e) {
            Log::error('Error al descargar documento', [
                'solicitud_id' => $solicitudId,
                'documento_id' => $documentoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al descargar documento', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener documentos requeridos según el tipo de crédito
     */
    private function obtenerDocumentosPorTipoCredito(string $detalleModalidad): array
    {
        // Documentos base para todos los créditos
        $documentosBase = [
            [
                'id' => 'cedula_frente',
                'nombre' => 'Cédula de Ciudadanía - Frente',
                'descripcion' => 'Copia legible de la cédula por el frente',
                'tipo' => 'identificacion',
                'obligatorio' => true
            ],
            [
                'id' => 'cedula_reverso',
                'nombre' => 'Cédula de Ciudadanía - Reverso',
                'descripcion' => 'Copia legible de la cédula por el reverso',
                'tipo' => 'identificacion',
                'obligatorio' => true
            ],
            [
                'id' => 'recibo_servicios',
                'nombre' => 'Recibo de Servicios Públicos',
                'descripcion' => 'Recibo de servicios públicos no mayor a 3 meses',
                'tipo' => 'domicilio',
                'obligatorio' => true
            ]
        ];

        // Documentos según tipo de crédito
        if (stripos($detalleModalidad, 'vivienda') !== false) {
            $documentosEspecificos = [
                [
                    'id' => 'certificado_laboral',
                    'nombre' => 'Certificado Laboral',
                    'descripcion' => 'Certificado de trabajo con antigüedad y salario',
                    'tipo' => 'laboral',
                    'obligatorio' => true
                ],
                [
                    'id' => 'desprendibles_nomina',
                    'nombre' => 'Desprendibles de Nómina',
                    'descripcion' => 'Últimos 3 desprendibles de nómina',
                    'tipo' => 'laboral',
                    'obligatorio' => true
                ],
                [
                    'id' => 'declaracion_renta',
                    'nombre' => 'Declaración de Renta',
                    'descripcion' => 'Declaración de renta del último año (si aplica)',
                    'tipo' => 'financiero',
                    'obligatorio' => false
                ],
                [
                    'id' => 'escritura_propiedad',
                    'nombre' => 'Escritura de Propiedad',
                    'descripcion' => 'Escritura del inmueble a adquirir (si aplica)',
                    'tipo' => 'propiedad',
                    'obligatorio' => false
                ]
            ];
        } elseif (stripos($detalleModalidad, 'educacion') !== false) {
            $documentosEspecificos = [
                [
                    'id' => 'certificado_estudiantil',
                    'nombre' => 'Certificado Estudiantil',
                    'descripcion' => 'Certificado de estudiante actual',
                    'tipo' => 'academico',
                    'obligatorio' => true
                ],
                [
                    'id' => 'pago_matricula',
                    'nombre' => 'Comprobante de Matrícula',
                    'descripcion' => 'Comprobante de pago de matrícula',
                    'tipo' => 'academico',
                    'obligatorio' => true
                ]
            ];
        } else {
            // Documentos genéricos para otros tipos de crédito
            $documentosEspecificos = [
                [
                    'id' => 'certificado_laboral',
                    'nombre' => 'Certificado Laboral',
                    'descripcion' => 'Certificado de trabajo con antigüedad y salario',
                    'tipo' => 'laboral',
                    'obligatorio' => true
                ],
                [
                    'id' => 'desprendibles_nomina',
                    'nombre' => 'Desprendibles de Nómina',
                    'descripcion' => 'Últimos 3 desprendibles de nómina',
                    'tipo' => 'laboral',
                    'obligatorio' => true
                ]
            ];
        }

        return array_merge($documentosBase, $documentosEspecificos);
    }

    /**
     * Generar nombre único para archivo
     */
    private function generarNombreArchivo(string $solicitudId, string $documentoId, string $extension): string
    {
        $timestamp = Carbon::now()->format('YmdHis');
        return "{$solicitudId}_{$documentoId}_{$timestamp}.{$extension}";
    }

    /**
     * Agregar documento a la solicitud usando el modelo SolicitudDocumento
     */
    private function agregarDocumentoASolicitud(string $solicitudId, array $fileData): array
    {
        try {
            $solicitud = (new SolicitudService)->getById($solicitudId);

            if (!$solicitud) {
                throw new \Exception("Solicitud no encontrada: {$solicitudId}");
            }

            // Crear registro en la tabla solicitud_documentos
            $documento = SolicitudDocumento::create([
                'solicitud_id' => $solicitudId,
                'documento_uuid' => $fileData['id'],
                'documento_requerido_id' => $fileData['documento_requerido_id'],
                'nombre_original' => $fileData['nombre_original'],
                'saved_filename' => basename($fileData['ruta_archivo']),
                'tipo_mime' => $fileData['tipo_mime'],
                'tamano_bytes' => $fileData['tamano'],
                'ruta_archivo' => $fileData['ruta_archivo'],
                'activo' => true
            ]);

            Log::info('Documento guardado en base de datos', [
                'documento_id' => $documento->id,
                'solicitud_id' => $solicitudId,
                'documento_uuid' => $fileData['id']
            ]);

            return [
                'documento' => $documento->toArray(),
                'solicitud' => $solicitud->fresh()->toArray()
            ];
        } catch (\Exception $e) {
            Log::error('Error al agregar documento a solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar documento de la solicitud
     */
    private function eliminarDocumentoDeSolicitud(string $solicitudId, string $documentoId): ?array
    {
        try {
            $solicitud = SolicitudCredito::where('numero_solicitud', $solicitudId)->first();

            if (!$solicitud) {
                return null;
            }

            $documentos = $solicitud->documentos ?? [];
            $documentoEncontrado = false;
            $rutaArchivoAEliminar = null;

            // Buscar y eliminar documento
            $documentosActualizados = array_filter($documentos, function ($documento) use ($documentoId, &$documentoEncontrado, &$rutaArchivoAEliminar) {
                if ($documento['id'] === $documentoId) {
                    $documentoEncontrado = true;
                    $rutaArchivoAEliminar = $documento['ruta_archivo'] ?? null;
                    return false;
                }
                return true;
            });

            if (!$documentoEncontrado) {
                return null;
            }

            // Eliminar archivo del storage
            if ($rutaArchivoAEliminar && Storage::disk('public')->exists($rutaArchivoAEliminar)) {
                Storage::disk('public')->delete($rutaArchivoAEliminar);
            }

            // Actualizar solicitud
            $solicitud->update([
                'documentos' => array_values($documentosActualizados),
                'updated_at' => Carbon::now()
            ]);

            return $solicitud->fresh()->toArray();
        } catch (\Exception $e) {
            Log::error('Error al eliminar documento de solicitud', [
                'solicitud_id' => $solicitudId,
                'documento_id' => $documentoId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de documentos
     */
    public function obtenerEstadisticasDocumentos(Request $request): JsonResponse
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

            Log::info('Obteniendo estadísticas de documentos', [
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
                'con_documentos' => 0,
                'total_documentos' => 0,
                'tipos_documento' => [],
                'tamano_promedio' => 0,
                'tamano_total' => 0
            ];

            foreach ($solicitudes as $solicitud) {
                $documentos = $solicitud->documentos ?? [];

                if (!empty($documentos)) {
                    $estadisticas['con_documentos']++;
                    $estadisticas['total_documentos'] += count($documentos);

                    foreach ($documentos as $documento) {
                        $tipo = $documento['tipo_mime'] ?? 'unknown';

                        if (!isset($estadisticas['tipos_documento'][$tipo])) {
                            $estadisticas['tipos_documento'][$tipo] = 0;
                        }
                        $estadisticas['tipos_documento'][$tipo]++;

                        $tamano = $documento['tamano'] ?? 0;
                        $estadisticas['tamano_total'] += $tamano;
                    }
                }
            }

            if ($estadisticas['total_documentos'] > 0) {
                $estadisticas['tamano_promedio'] = round($estadisticas['tamano_total'] / $estadisticas['total_documentos'], 2);
            }

            return ApiResource::success($estadisticas, 'Estadísticas obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de documentos', [
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
