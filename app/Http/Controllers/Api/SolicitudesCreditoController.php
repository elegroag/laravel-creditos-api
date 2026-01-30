<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudCredito;
use App\Services\SolicitudService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SolicitudesCreditoController extends Controller
{
    protected SolicitudService $solicitudService;
    protected UserService $userService;

    public function __construct(SolicitudService $solicitudService, UserService $userService)
    {
        $this->solicitudService = $solicitudService;
        $this->userService = $userService;
    }

    /**
     * Crea una nueva solicitud de crédito con validación.
     */
    public function crearSolicitudCredito(Request $request): JsonResponse
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

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'numero_solicitud' => 'sometimes|string|max:50',
                'monto_solicitado' => 'required|numeric|min:0',
                'plazo_meses' => 'required|integer|min:1|max:360',
                'linea_credito' => 'sometimes|string|max:10',
                'solicitante' => 'required|array',
                'solicitante.tipo_identificacion' => 'required|string|max:10',
                'solicitante.numero_identificacion' => 'required|string|max:20',
                'solicitante.nombres_apellidos' => 'required|string|max:200',
                'solicitante.email' => 'sometimes|email|max:255',
                'solicitante.telefono_movil' => 'sometimes|string|max:20',
                'solicitante.direccion' => 'sometimes|string|max:255',
                'solicitante.ciudad' => 'sometimes|string|max:100',
                'observaciones' => 'sometimes|string|max:500'
            ], [
                'monto_solicitado.required' => 'El monto solicitado es requerido',
                'monto_solicitado.numeric' => 'El monto solicitado debe ser un número',
                'monto_solicitado.min' => 'El monto solicitado debe ser mayor o igual a 0',
                'plazo_meses.required' => 'El plazo en meses es requerido',
                'plazo_meses.integer' => 'El plazo debe ser un número entero',
                'plazo_meses.min' => 'El plazo debe ser al menos 1 mes',
                'plazo_meses.max' => 'El plazo no puede exceder 360 meses',
                'solicitante.required' => 'Los datos del solicitante son requeridos',
                'solicitante.tipo_identificacion.required' => 'El tipo de identificación es requerido',
                'solicitante.numero_identificacion.required' => 'El número de identificación es requerido',
                'solicitante.nombres_apellidos.required' => 'Los nombres y apellidos son requeridos',
                'solicitante.email.email' => 'El email debe ser válido'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $solicitudData = $validator->validated();

            Log::info('Creando nueva solicitud de crédito', [
                'username' => $username,
                'monto_solicitado' => $solicitudData['monto_solicitado'],
                'plazo_meses' => $solicitudData['plazo_meses']
            ]);

            // Crear solicitud mediante servicio
            $solicitud = $this->solicitudService->create($solicitudData, $username);

            Log::info('Solicitud creada exitosamente', [
                'solicitud_id' => $solicitud['id'] ?? null,
                'username' => $username
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud creada exitosamente',
                'data' => $solicitud
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear solicitud de crédito', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al crear solicitud',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Lista solicitudes de crédito con filtros básicos por GET (compatibilidad).
     */
    public function listarSolicitudesCredito(Request $request): JsonResponse
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
            $isAdmin = in_array('administrator', $userRoles);

            // Validar parámetros de consulta
            $validator = Validator::make($request->all(), [
                'skip' => 'sometimes|integer|min:0',
                'limit' => 'sometimes|integer|min:1|max:100',
                'estado' => 'sometimes|string|max:50',
                'numero_solicitud' => 'sometimes|string|max:50',
                'fecha_desde' => 'sometimes|date',
                'fecha_hasta' => 'sometimes|date',
                'numero_documento' => 'sometimes|string|max:20',
                'nombre_usuario' => 'sometimes|string|max:255',
                'owner_username' => 'sometimes|string|max:255',
                'monto_minimo' => 'sometimes|numeric|min:0',
                'monto_maximo' => 'sometimes|numeric|min:0',
                'estados' => 'sometimes|array',
                'estados.*' => 'sometimes|string|max:50',
                'ordenar_por' => 'sometimes|string|in:created_at,updated_at,monto_solicitado,plazo_meses,numero_solicitud',
                'orden_direccion' => 'sometimes|string|in:asc,desc'
            ], [
                'skip.integer' => 'Skip debe ser un número entero',
                'skip.min' => 'Skip debe ser mayor o igual a 0',
                'limit.integer' => 'Limit debe ser un número entero',
                'limit.min' => 'Limit debe ser al menos 1',
                'limit.max' => 'Limit no puede exceder 100',
                'monto_minimo.numeric' => 'El monto mínimo debe ser un número',
                'monto_minimo.min' => 'El monto mínimo debe ser mayor o igual a 0',
                'monto_maximo.numeric' => 'El monto máximo debe ser un número',
                'monto_maximo.min' => 'El monto máximo debe ser mayor o igual a 0',
                'ordenar_por.in' => 'El campo de ordenamiento no es válido',
                'orden_direccion.in' => 'La dirección de ordenamiento debe ser asc o desc'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $queryParams = $validator->validated();
            $skip = $queryParams['skip'] ?? 0;
            $limit = $queryParams['limit'] ?? 20;

            Log::info('Listando solicitudes de crédito', [
                'username' => $username,
                'is_admin' => $isAdmin,
                'skip' => $skip,
                'limit' => $limit
            ]);

            if ($isAdmin) {
                // Construir filtros para admin
                $filters = [];

                // Filtros básicos
                if (isset($queryParams['estado'])) {
                    $filters['estado'] = $queryParams['estado'];
                }
                if (isset($queryParams['numero_solicitud'])) {
                    $filters['numero_solicitud'] = $queryParams['numero_solicitud'];
                }

                // Filtros de fecha
                if (isset($queryParams['fecha_desde'])) {
                    $filters['fecha_desde'] = $queryParams['fecha_desde'];
                }
                if (isset($queryParams['fecha_hasta'])) {
                    $filters['fecha_hasta'] = $queryParams['fecha_hasta'];
                }

                // Filtros de usuario
                if (isset($queryParams['numero_documento'])) {
                    $filters['numero_documento'] = $queryParams['numero_documento'];
                }
                if (isset($queryParams['nombre_usuario'])) {
                    $filters['nombre_usuario'] = $queryParams['nombre_usuario'];
                }
                if (isset($queryParams['owner_username'])) {
                    $filters['owner_username'] = $queryParams['owner_username'];
                }

                // Filtros de monto
                if (isset($queryParams['monto_minimo'])) {
                    $filters['monto_minimo'] = $queryParams['monto_minimo'];
                }
                if (isset($queryParams['monto_maximo'])) {
                    $filters['monto_maximo'] = $queryParams['monto_maximo'];
                }

                // Filtros de estado (múltiple)
                if (isset($queryParams['estados'])) {
                    $filters['estados'] = $queryParams['estados'];
                }

                // Ordenamiento
                $ordenarPor = $queryParams['ordenar_por'] ?? 'created_at';
                $ordenDireccion = $queryParams['orden_direccion'] ?? 'desc';

                // Obtener solicitudes con filtros avanzados
                $solicitudes = $this->solicitudService->advancedSearch(
                    $filters,
                    $skip,
                    $limit
                );
            } else {
                // Usuario normal solo ve sus solicitudes con filtros limitados
                $filters = ['owner_username' => $username];

                if (isset($queryParams['estado'])) {
                    $filters['estado'] = $queryParams['estado'];
                }
                if (isset($queryParams['numero_solicitud'])) {
                    $filters['numero_solicitud'] = $queryParams['numero_solicitud'];
                }

                $solicitudes = $this->solicitudService->getByOwner(
                    $username,
                    $skip,
                    $limit,
                    $queryParams['estado'] ?? null
                );
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $solicitudes,
                    'pagination' => [
                        'skip' => $skip,
                        'limit' => $limit,
                        'count' => count($solicitudes)
                    ]
                ],
                'message' => 'Solicitudes obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar solicitudes de crédito', [
                'error' => $e->getMessage(),
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al listar solicitudes',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Lista todas las solicitudes de crédito sin paginación ni límites por usuario.
     */
    public function listarSolicitudesCreditoForUser(): JsonResponse
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
            $isAdmin = in_array('administrator', $userRoles);

            Log::info('Listando todas las solicitudes de crédito', [
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            if ($isAdmin) {
                // Admin puede ver todas las solicitudes
                $solicitudes = $this->solicitudService->list(0, 10000, []);
            } else {
                // Usuario normal solo ve sus solicitudes
                $solicitudes = $this->solicitudService->getByOwner($username, 0, 10000, null);
            }

            return response()->json([
                'success' => true,
                'data' => $solicitudes,
                'message' => 'Todas las solicitudes obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar todas las solicitudes de crédito', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al listar solicitudes',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtiene una solicitud específica con validación.
     */
    public function obtenerSolicitudCredito(string $solicitudId): JsonResponse
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
            $isAdmin = in_array('administrator', $userRoles);
            $isAdviser = in_array('adviser', $userRoles);

            Log::info('Obteniendo solicitud de crédito', [
                'solicitud_id' => $solicitudId,
                'username' => $username,
                'is_admin' => $isAdmin,
                'is_adviser' => $isAdviser
            ]);

            // Obtener solicitud
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return response()->json([
                    'success' => false,
                    'error' => "Solicitud no encontrada: {$solicitudId}",
                    'details' => []
                ], 404);
            }

            // Verificar permisos: admin, adviser o propietario
            if (!$isAdmin && !$isAdviser && ($solicitud['owner_username'] ?? '') !== $username) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado para ver esta solicitud',
                    'details' => []
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Solicitud obtenida exitosamente',
                'data' => $solicitud
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener solicitud de crédito', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener solicitud',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Actualiza una solicitud con validación.
     */
    public function actualizarSolicitudCredito(Request $request, string $solicitudId): JsonResponse
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
            $isAdmin = in_array('administrator', $userRoles);

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'monto_solicitado' => 'sometimes|numeric|min:0',
                'plazo_meses' => 'sometimes|integer|min:1|max:360',
                'linea_credito' => 'sometimes|string|max:10',
                'solicitante' => 'sometimes|array',
                'solicitante.email' => 'sometimes|email|max:255',
                'solicitante.telefono_movil' => 'sometimes|string|max:20',
                'solicitante.direccion' => 'sometimes|string|max:255',
                'solicitante.ciudad' => 'sometimes|string|max:100',
                'observaciones' => 'sometimes|string|max:500',
                'documentos' => 'sometimes|array'
            ], [
                'monto_solicitado.numeric' => 'El monto solicitado debe ser un número',
                'monto_solicitado.min' => 'El monto solicitado debe ser mayor o igual a 0',
                'plazo_meses.integer' => 'El plazo debe ser un número entero',
                'plazo_meses.min' => 'El plazo debe ser al menos 1 mes',
                'plazo_meses.max' => 'El plazo no puede exceder 360 meses',
                'solicitante.email.email' => 'El email debe ser válido'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $updateData = $validator->validated();

            Log::info('Actualizando solicitud de crédito', [
                'solicitud_id' => $solicitudId,
                'username' => $username,
                'is_admin' => $isAdmin,
                'update_fields' => array_keys($updateData)
            ]);

            // Obtener solicitud para verificar permisos
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return response()->json([
                    'success' => false,
                    'error' => "Solicitud no encontrada: {$solicitudId}",
                    'details' => []
                ], 404);
            }

            // Verificar permisos: admin o propietario
            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado para modificar esta solicitud',
                    'details' => []
                ], 403);
            }

            // Actualizar solicitud
            $solicitudActualizada = $this->solicitudService->update($solicitudId, $updateData);

            Log::info('Solicitud actualizada exitosamente', [
                'solicitud_id' => $solicitudId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud actualizada exitosamente',
                'data' => $solicitudActualizada
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar solicitud de crédito', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al actualizar solicitud',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Elimina (desiste) una solicitud con validación.
     */
    public function eliminarSolicitudCredito(string $solicitudId): JsonResponse
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
            $isAdmin = in_array('administrator', $userRoles);

            Log::info('Eliminando solicitud de crédito', [
                'solicitud_id' => $solicitudId,
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            // Obtener solicitud para verificar permisos
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return response()->json([
                    'success' => false,
                    'error' => "Solicitud no encontrada: {$solicitudId}",
                    'details' => []
                ], 404);
            }

            // Verificar permisos: admin o propietario
            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado para eliminar esta solicitud',
                    'details' => []
                ], 403);
            }

            // Eliminar solicitud (cambia estado a 'Desiste')
            $eliminado = $this->solicitudService->delete($solicitudId);

            if ($eliminado) {
                Log::info('Solicitud eliminada exitosamente', [
                    'solicitud_id' => $solicitudId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Solicitud eliminada exitosamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo eliminar la solicitud',
                    'details' => []
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error al eliminar solicitud de crédito', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al eliminar solicitud',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Finaliza el proceso de una solicitud, cambiando el estado a 'ENVIADO_PENDIENTE_APROBACION'.
     */
    public function finalizarProcesoSolicitud(string $solicitudId): JsonResponse
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
            $isAdmin = in_array('administrator', $userRoles);

            Log::info('Finalizando proceso de solicitud', [
                'solicitud_id' => $solicitudId,
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            // Verificar que la solicitud existe y permisos
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return response()->json([
                    'success' => false,
                    'error' => "Solicitud no encontrada: {$solicitudId}",
                    'details' => []
                ], 404);
            }

            // Verificar permisos: admin o propietario
            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado para modificar esta solicitud',
                    'details' => []
                ], 403);
            }

            // Finalizar proceso - actualizar estado a 'ENVIADO_PENDIENTE_APROBACION'
            try {
                $solicitudActualizada = $this->solicitudService->updateEstado(
                    $solicitudId,
                    'ENVIADO_PENDIENTE_APROBACION',
                    'Proceso finalizado y enviado para aprobación'
                );
            } catch (\ValueError $e) {
                // Si el estado no existe, buscar un estado alternativo válido
                if (stripos($e->getMessage(), 'no existe') !== false || stripos($e->getMessage(), 'inválido') !== false) {
                    // Buscar un estado alternativo válido
                    $estadosValidos = $this->solicitudService->getEstadosDisponibles();
                    $estadoAlternativo = null;

                    // Buscar estados que contengan "enviado" o "aprob"
                    foreach ($estadosValidos as $estado) {
                        if (stripos($estado['id'] ?? '', 'enviado') !== false || stripos($estado['id'] ?? '', 'aprob') !== false) {
                            $estadoAlternativo = $estado['id'];
                            break;
                        }
                    }

                    // Si no encuentra, usar el primer estado válido
                    if (!$estadoAlternativo && !empty($estadosValidos)) {
                        $estadoAlternativo = $estadosValidos[0]['id'];
                    }

                    if ($estadoAlternativo) {
                        $solicitudActualizada = $this->solicitudService->updateEstado(
                            $solicitudId,
                            $estadoAlternativo,
                            'Proceso finalizado y enviado para aprobación'
                        );
                    } else {
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }

            if (!$solicitudActualizada) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo finalizar el proceso',
                    'details' => []
                ], 400);
            }

            Log::info('Proceso de solicitud finalizado exitosamente', [
                'solicitud_id' => $solicitudId,
                'nuevo_estado' => $solicitudActualizada['estado'] ?? 'Unknown'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Proceso finalizado exitosamente. Solicitud enviada para aprobación.',
                'data' => $solicitudActualizada
            ]);
        } catch (\Exception $e) {
            Log::error('Error al finalizar proceso de solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al finalizar proceso',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtiene la lista de estados de solicitud disponibles.
     */
    public function obtenerEstadosSolicitud(): JsonResponse
    {
        try {
            Log::info('Obteniendo estados de solicitud disponibles');

            $estados = $this->solicitudService->getEstadosDisponibles();

            return response()->json([
                'success' => true,
                'data' => $estados,
                'message' => 'Estados de solicitud obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estados de solicitud', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener estados',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de solicitudes
     */
    public function obtenerEstadisticasSolicitudes(): JsonResponse
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
            $isAdmin = in_array('administrator', $userRoles);

            Log::info('Obteniendo estadísticas de solicitudes', [
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            $estadisticas = $this->solicitudService->getEstadisticas($isAdmin ? null : $username);

            return response()->json([
                'success' => true,
                'data' => $estadisticas,
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de solicitudes', [
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

    /**
     * Buscar solicitudes
     */
    public function buscarSolicitudes(Request $request): JsonResponse
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
            $isAdmin = in_array('administrator', $userRoles);

            // Validar parámetros
            $validator = Validator::make($request->all(), [
                'termino' => 'required|string|min:2|max:100',
                'limit' => 'sometimes|integer|min:1|max:100',
                'estado' => 'sometimes|string|max:50'
            ], [
                'termino.required' => 'El término de búsqueda es requerido',
                'termino.min' => 'El término debe tener al menos 2 caracteres',
                'termino.max' => 'El término no puede exceder 100 caracteres',
                'limit.integer' => 'El límite debe ser un número entero',
                'limit.min' => 'El límite debe ser al menos 1',
                'limit.max' => 'El límite no puede exceder 100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $termino = $data['termino'];
            $limit = $data['limit'] ?? 50;
            $estado = $data['estado'] ?? null;

            Log::info('Buscando solicitudes', [
                'termino' => $termino,
                'limit' => $limit,
                'estado' => $estado,
                'is_admin' => $isAdmin
            ]);

            $resultados = $this->solicitudService->buscar($termino, $limit, $estado, $isAdmin ? null : $username);

            return response()->json([
                'success' => true,
                'data' => [
                    'solicitudes' => $resultados,
                    'total' => count($resultados),
                    'termino' => $termino,
                    'estado' => $estado
                ],
                'message' => 'Búsqueda completada'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al buscar solicitudes', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al buscar solicitudes',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }
}
