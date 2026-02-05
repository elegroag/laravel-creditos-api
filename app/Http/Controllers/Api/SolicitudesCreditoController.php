<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\EstadoSolicitud;
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
     * Obtiene los datos del usuario autenticado desde JWT middleware
     */
    private function getAuthenticatedUser(Request $request): array
    {
        $authenticatedUser = $request->get('authenticated_user');
        return $authenticatedUser['user'] ?? [];
    }

    /**
     * Crea una nueva solicitud de crédito con validación.
     */
    public function crearSolicitudCredito(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

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
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
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

            return ApiResource::success($solicitud, 'Solicitud creada exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al crear solicitud de crédito', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al crear solicitud', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Lista solicitudes de crédito con filtros básicos por GET (compatibilidad).
     */
    public function listarSolicitudesCredito(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];
            $userRoles = $userData['roles'] ?? [];
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
                return ErrorResource::validationError($validator->errors()->toArray(), 'Parámetros inválidos')
                    ->response()
                    ->setStatusCode(422);
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

            return ApiResource::success([
                'items' => $solicitudes,
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'count' => count($solicitudes)
                ]
            ], 'Solicitudes obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al listar solicitudes de crédito', [
                'error' => $e->getMessage(),
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al listar solicitudes', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Lista todas las solicitudes de crédito sin paginación ni límites por usuario.
     */
    public function listarSolicitudesCreditoForUser(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];
            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('administrator', $userRoles);

            if ($isAdmin) {
                // Admin puede ver todas las solicitudes
                $paginado = $this->solicitudService->list(0, 10000, []);
                $solicitudes = $paginado['solicitudes'];
            } else {
                // Usuario normal solo ve sus solicitudes
                $consulta = $this->solicitudService->getByOwner($username, 0, 10000, null);
                $solicitudes = $consulta['solicitudes'];
            }

            return ApiResource::success($solicitudes, 'Todas las solicitudes obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al listar todas las solicitudes de crédito', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al listar solicitudes', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtiene una solicitud específica con validación.
     */
    public function obtenerSolicitudCredito(Request $request, string $solicitudId): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];
            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('administrator', $userRoles);
            $isAdviser = in_array('adviser', $userRoles);

            // Obtener solicitud
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            // Verificar permisos: admin, adviser o propietario
            if (!$isAdmin && !$isAdviser && ($solicitud['owner_username'] !== $username)) {
                return ErrorResource::forbidden('No autorizado para ver esta solicitud')->response();
            }

            return ApiResource::success($solicitud, 'Solicitud obtenida exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener solicitud de crédito', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al obtener solicitud', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Actualiza una solicitud con validación.
     */
    public function actualizarSolicitudCredito(Request $request, string $solicitudId): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];
            $userRoles = $userData['roles'] ?? [];
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
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
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
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            // Verificar permisos: admin o propietario
            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para modificar esta solicitud')->response();
            }

            // Actualizar solicitud
            $solicitudActualizada = $this->solicitudService->update($solicitudId, $updateData);

            Log::info('Solicitud actualizada exitosamente', [
                'solicitud_id' => $solicitudId
            ]);

            return ApiResource::success($solicitudActualizada, 'Solicitud actualizada exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al actualizar solicitud de crédito', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al actualizar solicitud', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Elimina (desiste) una solicitud con validación.
     */
    public function eliminarSolicitudCredito(Request $request, string $solicitudId): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];
            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('administrator', $userRoles);

            Log::info('Eliminando solicitud de crédito', [
                'solicitud_id' => $solicitudId,
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            // Obtener solicitud para verificar permisos
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            // Verificar permisos: admin o propietario
            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para eliminar esta solicitud')->response();
            }

            // Eliminar solicitud (cambia estado a 'Desiste')
            $eliminado = $this->solicitudService->delete($solicitudId);

            if ($eliminado) {
                Log::info('Solicitud eliminada exitosamente', [
                    'solicitud_id' => $solicitudId
                ]);

                return ApiResource::success(null, 'Solicitud eliminada exitosamente')->response();
            } else {
                return ErrorResource::errorResponse('No se pudo eliminar la solicitud')->response()->setStatusCode(400);
            }
        } catch (\Exception $e) {
            Log::error('Error al eliminar solicitud de crédito', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al eliminar solicitud', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Finaliza el proceso de una solicitud, cambiando el estado a 'ENVIADO_PENDIENTE_APROBACION'.
     */
    public function finalizarProcesoSolicitud(Request $request, string $solicitudId): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];
            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('administrator', $userRoles);

            Log::info('Finalizando proceso de solicitud', [
                'solicitud_id' => $solicitudId,
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            // Verificar que la solicitud existe y permisos
            $solicitud = $this->solicitudService->getById($solicitudId);

            if (!$solicitud) {
                return ErrorResource::notFound("Solicitud no encontrada: {$solicitudId}")->response();
            }

            // Verificar permisos: admin o propietario
            if (!$isAdmin && ($solicitud['owner_username'] ?? '') !== $username) {
                return ErrorResource::forbidden('No autorizado para modificar esta solicitud')->response();
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
                return ErrorResource::errorResponse('No se pudo finalizar el proceso')->response()->setStatusCode(400);
            }

            Log::info('Proceso de solicitud finalizado exitosamente', [
                'solicitud_id' => $solicitudId,
                'nuevo_estado' => $solicitudActualizada['estado'] ?? 'Unknown'
            ]);

            return ApiResource::success($solicitudActualizada, 'Proceso finalizado exitosamente. Solicitud enviada para aprobación.')->response();
        } catch (\Exception $e) {
            Log::error('Error al finalizar proceso de solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al finalizar proceso', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtiene la lista de estados de solicitud disponibles.
     */
    public function obtenerEstadosSolicitud(): JsonResponse
    {
        try {
            $estados = EstadoSolicitud::all()->toArray();
            return ApiResource::success($estados, 'Estados de solicitud obtenidos exitosamente')->response();
        } catch (\Exception $e) {
            return ErrorResource::errorResponse('Error al obtener estados de solicitud', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response()->setStatusCode(400);
        }
    }

    /**
     * Obtener estadísticas de solicitudes
     */
    public function obtenerEstadisticasSolicitudes(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];
            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('administrator', $userRoles);

            Log::info('Obteniendo estadísticas de solicitudes', [
                'username' => $username,
                'is_admin' => $isAdmin
            ]);

            $estadisticas = $this->solicitudService->getEstadisticas($isAdmin ? null : $username);

            return ApiResource::success($estadisticas, 'Estadísticas obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de solicitudes', [
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

    /**
     * Buscar solicitudes
     */
    public function buscarSolicitudes(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];
            $userRoles = $userData['roles'] ?? [];
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
                return ErrorResource::validationError($validator->errors()->toArray(), 'Parámetros inválidos')
                    ->response()
                    ->setStatusCode(422);
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

            return ApiResource::success([
                'solicitudes' => $resultados,
                'total' => count($resultados),
                'termino' => $termino,
                'estado' => $estado
            ], 'Búsqueda completada')->response();
        } catch (\Exception $e) {
            Log::error('Error al buscar solicitudes', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al buscar solicitudes', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    public function contarSolicitudesPorEstado(Request $request)
    {
        try {

            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('administrator', $userRoles);

            Log::info('Contando solicitudes por estado', [
                'is_admin' => $isAdmin
            ]);

            $resultados = $this->solicitudService->contarSolicitudesPorEstado($isAdmin ? null : $username);

            // Transformar el array de resultados a un objeto con estados como claves
            $estadosConteo = [];
            foreach ($resultados as $resultado) {
                $estadosConteo[$resultado['estado']] = $resultado['count'];
            }

            return ApiResource::success($estadosConteo, 'Conteo de solicitudes obtenido exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al contar solicitudes por estado', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al contar solicitudes por estado', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    public function listarSolicitudesCreditoPaginado($limit, $offset, $estado)
    {
        try {
            $userData = $this->getAuthenticatedUser(request());
            $username = $userData['username'];

            $userRoles = $userData['roles'] ?? [];
            $isAdmin = in_array('administrator', $userRoles);

            Log::info('Listando solicitudes paginadas', [
                'limit' => $limit,
                'offset' => $offset,
                'estado' => $estado,
                'is_admin' => $isAdmin
            ]);

            $resultados = $this->solicitudService->listarSolicitudesCreditoPaginado($limit, $offset, $estado, $isAdmin ? null : $username);

            return ApiResource::success([
                'collection' => $resultados,
                'total' => count($resultados),
                'limit' => $limit,
                'offset' => $offset,
                'estado' => $estado
            ], 'Listado completado')->response();
        } catch (\Exception $e) {
            Log::error('Error al listar solicitudes paginadas', [
                'error' => $e->getMessage(),
                'data' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'estado' => $estado
                ],
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al listar solicitudes paginadas', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Guardar solicitud
     */
    public function guardarSolicitud(Request $request)
    {
        try {
            $userData = $this->getAuthenticatedUser($request);

            if (!$userData['username']) {
                throw new \ValueError('Token inválido');
            }
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'encabezado' => 'sometimes|array',
                'solicitud' => 'sometimes|array',
                'solicitante' => 'sometimes|array',
                'linea_credito' => 'sometimes|array',
                'conyuge' => 'sometimes|array',
                'informacion_laboral' => 'sometimes|array',
                'ingresos_descuentos' => 'sometimes|array',
                'garantia' => 'sometimes|array',
                'informacion_economica' => 'sometimes|array',
                'propiedades' => 'sometimes|array',
                'deudas' => 'sometimes|array',
                'referencias' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();

            $activosDir = storage_path('app/storage/activos');
            // Crear directorio si no existe
            if (!is_dir($activosDir)) {
                mkdir($activosDir, 0775, true);
            }

            $base = !empty($numeroSolicitud) ? safe_filename_component($numeroSolicitud) : 'solicitud';
            $timestamp = Carbon::now()->format('Ymd-His');
            $candidate = "{$base}-{$timestamp}.pdf";

            // Generar número de solicitud si se va a guardar
            $solicitudPayload = $data['solicitud'] ?? [];
            $numeroSolicitud = $this->solicitudService->generarNumeroSolicitudSiEsNecesario($solicitudPayload);

            // Guardar en base de datos
            $savedSolicitudId = $this->solicitudService->guardarSolicitudEnBaseDatos($data, $numeroSolicitud, $userData['username']);

            return ApiResource::success([
                'numero_solicitud' => $savedSolicitudId,
                'filename' => $candidate
            ], 'Solicitud guardada exitosamente')
                ->response();
        } catch (\Exception $e) {
            Log::error('Error interno al generar PDF de solicitud', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al generar PDF', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }
}
