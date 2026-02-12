<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Models\User;
use App\Services\UserService;
use App\Services\ExternalApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class AdminUsuariosController extends Controller
{
    protected UserService $userService;
    protected ExternalApiService $externalApiService;

    public function __construct(UserService $userService, ExternalApiService $externalApiService)
    {
        $this->userService = $userService;
        $this->externalApiService = $externalApiService;
    }

    /**
     * Obtener estadísticas de usuarios para el dashboard administrativo
     */
    #[OA\Get(
        path: '/admin/usuarios/estadisticas',
        tags: ['AdminUsuarios'],
        summary: 'Obtener estadísticas de usuarios',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Estadísticas de usuarios obtenidas'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function obtenerEstadisticas(Request $request): JsonResponse
    {
        try {
            Log::info('Obteniendo estadísticas de usuarios para dashboard');

            // Obtener conteo de usuarios por rol
            $conteoRoles = $this->obtenerConteoRoles();

            // Formatear usuarios por rol para el frontend
            $usuariosPorRol = [];
            foreach ($conteoRoles as $rol => $cantidad) {
                $usuariosPorRol[] = [
                    'rol' => $rol,
                    'count' => $cantidad
                ];
            }

            // Obtener conteo de trabajadores específicamente
            $trabajadores = $conteoRoles['user_trabajador'] ?? 0;

            // Obtener estadísticas adicionales
            $totalUsuarios = User::count();
            $usuariosActivos = User::where('disabled', false)->count();
            $usuariosInactivos = $totalUsuarios - $usuariosActivos;

            // Usuarios creados en los últimos 30 días
            $usuariosRecientes = User::where('created_at', '>=', now()->subDays(30))->count();

            // Distribución por fecha de creación (últimos 7 días)
            $usuariosPorDia = [];
            for ($i = 6; $i >= 0; $i--) {
                $fecha = now()->subDays($i)->format('Y-m-d');
                $conteo = User::whereDate('created_at', $fecha)->count();
                $usuariosPorDia[] = [
                    'fecha' => $fecha,
                    'conteo' => $conteo
                ];
            }

            $data = [
                'trabajadores' => $trabajadores,
                'usuariosPorRol' => $usuariosPorRol,
                'totalUsuarios' => $totalUsuarios,
                'usuariosActivos' => $usuariosActivos,
                'usuariosInactivos' => $usuariosInactivos,
                'usuariosRecientes' => $usuariosRecientes,
                'usuariosPorDia' => $usuariosPorDia,
                'ultimaActualizacion' => now()->toISOString()
            ];

            Log::info('Estadísticas de usuarios obtenidas exitosamente', [
                'total_usuarios' => $totalUsuarios,
                'trabajadores' => $trabajadores
            ]);

            return ApiResource::success($data, 'Estadísticas de usuarios obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de usuarios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al obtener estadísticas de usuarios', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener lista de usuarios con paginación y filtros
     *
     * Query params:
     * - page: número de página (default: 1)
     * - limit: límite de resultados por página (default: 20)
     * - rol: filtro por rol
     * - estado: filtro por estado (active/inactive)
     * - tipo_documento: filtro por tipo de documento
     * - numero_documento: filtro por número de documento
     */
    #[OA\Get(
        path: '/admin/usuarios',
        tags: ['AdminUsuarios'],
        summary: 'Listar usuarios administrativos',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Número de página', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Límite por página', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'rol', in: 'query', required: false, description: 'Filtro por rol', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'estado', in: 'query', required: false, description: 'Estado del usuario', schema: new OA\Schema(type: 'string', enum: ['active', 'inactive']))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de usuarios'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 422, description: 'Parámetros inválidos')
        ]
    )]
    public function obtenerUsuarios(Request $request): JsonResponse
    {
        try {
            // Validar parámetros de paginación
            $validator = Validator::make($request->all(), [
                'page' => 'sometimes|integer|min:1',
                'limit' => 'sometimes|integer|min:1|max:100',
                'rol' => 'sometimes|string|max:50',
                'busqueda' => 'sometimes|string|max:100',
                'tipo_documento' => 'sometimes|string|max:20',
                'numero_documento' => 'sometimes|string|max:20',
                'is_active' => 'sometimes|boolean'
            ], [
                'page.integer' => 'La página debe ser un número entero',
                'page.min' => 'La página debe ser al menos 1',
                'limit.integer' => 'El límite debe ser un número entero',
                'limit.min' => 'El límite debe ser al menos 1',
                'limit.max' => 'El límite no puede exceder 100',
                'is_active.boolean' => 'El estado debe ser un valor booleano'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Parámetros inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $params = $validator->validated();
            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 20;
            $offset = ($page - 1) * $limit;

            Log::info('Obteniendo lista de usuarios', [
                'page' => $page,
                'limit' => $limit,
                'filters' => array_diff_key($params, ['page' => '', 'limit' => ''])
            ]);

            // Construir filtros
            $query = User::query();

            // Filtro por rol
            if (!empty($params['rol'])) {
                $query->where('roles', 'LIKE', '%' . $params['rol'] . '%');
            }

            // Filtro por estado
            if (!empty($params['is_active'])) {
                $query->where('is_active', $params['is_active']);
            }

            // Filtro por tipo de documento
            if (!empty($params['tipo_documento'])) {
                $query->where('tipo_documento', $params['tipo_documento']);
            }

            // Filtro por número de documento
            if (!empty($params['numero_documento'])) {
                $query->where('numero_documento', $params['numero_documento']);
            }

            // Búsqueda por nombre, email o documento
            if (!empty($params['busqueda'])) {
                $busqueda = $params['busqueda'];
                $query->where(function ($q) use ($busqueda) {
                    $q->where('username', 'LIKE', '%' . $busqueda . '%')
                        ->orWhere('email', 'LIKE', '%' . $busqueda . '%')
                        ->orWhere('nombres', 'LIKE', '%' . $busqueda . '%')
                        ->orWhere('apellidos', 'LIKE', '%' . $busqueda . '%')
                        ->orWhere('numero_documento', 'LIKE', '%' . $busqueda . '%');
                });
            }

            // Contar total
            $total = $query->count();

            // Obtener usuarios con paginación
            $usuarios = $query->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            // Formatear usuarios con datos de trabajador
            $usuariosFormateados = [];
            foreach ($usuarios as $usuario) {
                $usuarioFormateado = $this->formatearUsuarioConTrabajador($usuario);
                $usuariosFormateados[] = $usuarioFormateado;
            }

            // Generar respuesta de paginación
            $totalPages = (int) ceil($total / $limit);

            // Agregar conteos adicionales
            $conteoRoles = $this->obtenerConteoRoles();
            $conteoEstados = $this->obtenerConteoEstados();

            $data = [
                'usuarios' => $usuariosFormateados,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ],
                'conteo_roles' => $conteoRoles,
                'conteo_estados' => $conteoEstados
            ];

            Log::info('Usuarios obtenidos exitosamente', [
                'total' => $total,
                'page' => $page
            ]);

            return ApiResource::success($data, 'Usuarios obtenidos exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener usuarios', [
                'error' => $e->getMessage(),
                'params' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al obtener usuarios', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener detalles de un usuario específico
     */
    #[OA\Get(
        path: '/admin/usuarios/{userId}',
        tags: ['AdminUsuarios'],
        summary: 'Obtener usuario por ID',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                description: 'ID del usuario',
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuario encontrado'),
            new OA\Response(response: 404, description: 'Usuario no encontrado'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function obtenerUsuario(string $userId): JsonResponse
    {
        try {
            // Validar UUID
            if (!$userId) {
                return ErrorResource::errorResponse('ID de usuario inválido')
                    ->response()
                    ->setStatusCode(400);
            }

            Log::info('Obteniendo detalles de usuario', ['user_id' => $userId]);

            // Buscar usuario
            $usuario = User::find($userId);

            if (!$usuario) {
                return ErrorResource::notFound('Usuario no encontrado')->response();
            }

            // Formatear respuesta
            $usuarioFormateado = [
                'id' => $usuario->id,
                'username' => $usuario->username,
                'email' => $usuario->email,
                'full_name' => $usuario->full_name,
                'phone' => $usuario->phone,
                'roles' => $usuario->roles ?? [],
                'disabled' => $usuario->disabled ?? false,
                'tipo_documento' => $usuario->tipo_documento,
                'numero_documento' => $usuario->numero_documento,
                'nombres' => $usuario->nombres,
                'apellidos' => $usuario->apellidos,
                'created_at' => $usuario->created_at?->toISOString(),
                'updated_at' => $usuario->updated_at?->toISOString(),
                'puntos_asesorias' => null,
            ];

            // Si es asesor, obtener puntos de asesorías
            if (in_array('adviser', $usuario->roles ?? [])) {
                $puntosAsesorias = $this->obtenerPuntosAsesorias($usuario->numero_documento);
                if (!empty($puntosAsesorias)) {
                    $usuarioFormateado['puntos_asesorias'] = $puntosAsesorias;
                }
            }

            Log::info('Usuario obtenido exitosamente', [
                'user_id' => $userId,
                'username' => $usuario->username
            ]);

            return ApiResource::success($usuarioFormateado, 'Usuario obtenido exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener usuario', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al obtener usuario', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Crear un nuevo usuario
     */
    #[OA\Post(
        path: '/admin/usuarios',
        tags: ['AdminUsuarios'],
        summary: 'Crear nuevo usuario',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'nombres', 'apellidos', 'email', 'password', 'tipo_documento', 'numero_documento'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'jperez'),
                    new OA\Property(property: 'nombres', type: 'string', example: 'Juan'),
                    new OA\Property(property: 'apellidos', type: 'string', example: 'Pérez'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan@ejemplo.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123'),
                    new OA\Property(property: 'tipo_documento', type: 'string', example: 'CC'),
                    new OA\Property(property: 'numero_documento', type: 'string', example: '12345678'),
                    new OA\Property(property: 'telefono', type: 'string', example: '3001234567'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['user'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuario creado'),
            new OA\Response(response: 422, description: 'Datos inválidos'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function crearUsuario(Request $request): JsonResponse
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:255|unique:users,username',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
                'nombre' => 'required|string|max:100',
                'apellido' => 'required|string|max:100',
                'roles' => 'sometimes|array',
                'disabled' => 'sometimes|boolean',
                'tipo_documento' => 'sometimes|string|max:20',
                'numero_documento' => 'sometimes|string|max:20',
                'telefono' => 'sometimes|string|max:20'
            ], [
                'username.required' => 'El nombre de usuario es requerido',
                'username.unique' => 'El nombre de usuario ya existe',
                'email.required' => 'El email es requerido',
                'email.email' => 'El email debe ser válido',
                'email.unique' => 'El email ya está registrado',
                'password.required' => 'La contraseña es requerida',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres',
                'nombre.required' => 'El nombre es requerido',
                'apellido.required' => 'El apellido es requerido'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();

            Log::info('Creando nuevo usuario', [
                'username' => $data['username'],
                'email' => $data['email']
            ]);

            // Validar si el usuario tiene rol de asesor
            $puntosAsesorias = null;
            if (isset($data['roles']) && in_array('adviser', $data['roles'])) {
                $puntosAsesorias = $this->validarAsesorExterno($data['numero_documento'] ?? '');
            }

            // Crear usuario
            $usuario = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => Hash::make($data['password']),
                'roles' => $data['roles'] ?? ['user_trabajador'],
                'disabled' => $data['disabled'] ?? false,
                'tipo_documento' => $data['tipo_documento'] ?? 'CC',
                'numero_documento' => $data['numero_documento'] ?? '',
                'nombres' => $data['nombre'],
                'apellidos' => $data['apellido'],
                'full_name' => $data['nombre'] . ' ' . $data['apellido'],
                'phone' => $data['telefono'] ?? '',
                'puntos_asesorias' => $puntosAsesorias,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            Log::info('Usuario creado exitosamente', [
                'user_id' => $usuario->id,
                'username' => $usuario->username
            ]);

            return ApiResource::success(['id' => $usuario->id], 'Usuario creado exitosamente')->response()
                ->setStatusCode(201);
        } catch (\Exception $e) {
            Log::error('Error al crear usuario', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al crear usuario', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Actualizar un usuario existente
     */
    #[OA\Put(
        path: '/admin/usuarios/{userId}',
        tags: ['AdminUsuarios'],
        summary: 'Actualizar usuario',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                description: 'ID del usuario',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nombres', type: 'string', example: 'Juan'),
                    new OA\Property(property: 'apellidos', type: 'string', example: 'Pérez'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan@ejemplo.com'),
                    new OA\Property(property: 'telefono', type: 'string', example: '3001234567'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['user', 'adviser'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Usuario actualizado'),
            new OA\Response(response: 404, description: 'Usuario no encontrado'),
            new OA\Response(response: 422, description: 'Datos inválidos'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function actualizarUsuario(Request $request, string $userId): JsonResponse
    {
        try {
            // Validar UUID
            if (!Str::isUuid($userId)) {
                return ErrorResource::errorResponse('ID de usuario inválido')
                    ->response()
                    ->setStatusCode(400);
            }

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'email' => 'sometimes|email|max:255|unique:users,email,' . $userId,
                'username' => 'sometimes|string|max:255|unique:users,username,' . $userId,
                'password' => 'sometimes|string|min:8',
                'roles' => 'sometimes|array',
                'is_active' => 'sometimes|boolean',
                'telefono' => 'sometimes|string|max:20',
                'nombre' => 'sometimes|string|max:100',
                'apellido' => 'sometimes|string|max:100',
                'tipo_documento' => 'sometimes|string|max:20',
                'numero_documento' => 'sometimes|string|max:20'
            ], [
                'email.email' => 'El email debe ser válido',
                'email.unique' => 'El email ya está registrado',
                'username.unique' => 'El nombre de usuario ya existe',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres',
                'is_active.boolean' => 'El is_active debe ser boolean'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();

            Log::info('Actualizando usuario', [
                'user_id' => $userId,
                'fields' => array_keys($data)
            ]);

            // Verificar que el usuario existe
            $usuario = User::find($userId);

            if (!$usuario) {
                return ErrorResource::notFound('Usuario no encontrado')->response();
            }

            // Preparar actualización
            $updateData = ['updated_at' => Carbon::now()];

            // Campos actualizables
            $camposUsuario = ['email', 'roles', 'is_active'];
            foreach ($camposUsuario as $campo) {
                if (isset($data[$campo])) {
                    $updateData[$campo] = $data[$campo];
                }
            }

            // Actualizar username si se proporciona
            if (isset($data['username'])) {
                $updateData['username'] = $data['username'];
            }

            // Actualizar contraseña si se proporciona
            if (isset($data['password']) && !empty($data['password'])) {
                $updateData['password_hash'] = Hash::make($data['password']);
            }

            // Actualizar datos personales
            if (isset($data['nombre'])) {
                $updateData['nombres'] = $data['nombre'];
            }
            if (isset($data['apellido'])) {
                $updateData['apellidos'] = $data['apellido'];
            }
            if (isset($data['telefono'])) {
                $updateData['phone'] = $data['telefono'];
            }
            if (isset($data['tipo_documento'])) {
                $updateData['tipo_documento'] = $data['tipo_documento'];
            }
            if (isset($data['numero_documento'])) {
                $updateData['numero_documento'] = $data['numero_documento'];
            }

            // Actualizar full_name si se cambió nombre o apellido
            if (isset($data['nombre']) || isset($data['apellido'])) {
                $nombres = $data['nombre'] ?? $usuario->nombres;
                $apellidos = $data['apellido'] ?? $usuario->apellidos;
                $updateData['full_name'] = trim($nombres . ' ' . $apellidos);
            }

            // Realizar actualización
            $usuario->update($updateData);

            Log::info('Usuario actualizado exitosamente', [
                'user_id' => $userId,
                'username' => $usuario->username
            ]);

            return ApiResource::success(null, 'Usuario actualizado exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al actualizar usuario', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al actualizar usuario', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Eliminar un usuario
     */
    #[OA\Delete(
        path: '/admin/usuarios/{userId}',
        tags: ['AdminUsuarios'],
        summary: 'Eliminar usuario',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                description: 'ID del usuario',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuario eliminado'),
            new OA\Response(response: 404, description: 'Usuario no encontrado'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function eliminarUsuario(string $userId): JsonResponse
    {
        try {
            // Validar UUID
            if (!Str::isUuid($userId)) {
                return ErrorResource::errorResponse('ID de usuario inválido')
                    ->response()
                    ->setStatusCode(400);
            }

            Log::info('Eliminando usuario', ['user_id' => $userId]);

            // Verificar que el usuario existe
            $usuario = User::find($userId);

            if (!$usuario) {
                return ErrorResource::notFound('Usuario no encontrado')->response();
            }

            // Eliminar usuario
            $usuario->delete();

            Log::info('Usuario eliminado exitosamente', [
                'user_id' => $userId,
                'username' => $usuario->username
            ]);

            return ApiResource::success(null, 'Usuario eliminado exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al eliminar usuario', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al eliminar usuario', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Cambiar el estado de un usuario (active/inactive)
     */
    #[OA\Put(
        path: '/admin/usuarios/{userId}/estado',
        tags: ['AdminUsuarios'],
        summary: 'Cambiar estado de usuario',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                description: 'ID del usuario',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['estado'],
                properties: [
                    new OA\Property(property: 'estado', type: 'string', enum: ['active', 'inactive'], example: 'active')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Estado cambiado'),
            new OA\Response(response: 404, description: 'Usuario no encontrado'),
            new OA\Response(response: 422, description: 'Datos inválidos'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function cambiarEstadoUsuario(Request $request, string $userId): JsonResponse
    {
        try {
            // Validar UUID
            if (!Str::isUuid($userId)) {
                return ErrorResource::errorResponse('ID de usuario inválido')
                    ->response()
                    ->setStatusCode(400);
            }

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'is_active' => 'required|string|in:active,inactive,suspended'
            ], [
                'is_active.required' => 'El is_active es requerido',
                'is_active.in' => 'El is_active debe ser: active, inactive o suspended'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $data = $validator->validated();
            $nuevoEstado = $data['is_active'];

            Log::info('Cambiando estado de usuario', [
                'user_id' => $userId,
                'nuevo_estado' => $nuevoEstado
            ]);

            // Verificar que el usuario existe
            $usuario = User::find($userId);

            if (!$usuario) {
                return ErrorResource::notFound('Usuario no encontrado')->response();
            }

            // Actualizar estado
            $usuario->update([
                'is_active' => $nuevoEstado,
                'updated_at' => Carbon::now()
            ]);

            Log::info('Estado de usuario cambiado exitosamente', [
                'user_id' => $userId,
                'nuevo_estado' => $nuevoEstado
            ]);

            return ApiResource::success([
                'id' => $usuario->id,
                'username' => $usuario->username,
                'nuevo_estado' => $nuevoEstado
            ], 'Estado del usuario cambiado a ' . $nuevoEstado . ' exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado del usuario', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al cambiar estado del usuario', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Exportar lista de usuarios a CSV
     */
    #[OA\Get(
        path: '/admin/usuarios/export',
        tags: ['AdminUsuarios'],
        summary: 'Exportar usuarios a CSV',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'rol', in: 'query', required: false, description: 'Filtrar por rol', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'estado', in: 'query', required: false, description: 'Filtrar por estado', schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'CSV exportado'),
            new OA\Response(response: 401, description: 'No autorizado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function exportarUsuarios(Request $request): JsonResponse
    {
        try {
            Log::info('Exportando usuarios a CSV');

            // Obtener todos los usuarios (sin paginación para exportación)
            $usuarios = User::orderBy('created_at', 'desc')->get();

            // Formatear datos para CSV
            $csvData = [];
            $csvData[] = [
                'ID',
                'Username',
                'Email',
                'Nombres',
                'Apellidos',
                'Tipo Documento',
                'Número Documento',
                'Teléfono',
                'Roles',
                'Estado',
                'Fecha Creación',
                'Último Acceso'
            ];

            foreach ($usuarios as $usuario) {
                $usuarioFormateado = $this->formatearUsuarioConTrabajador($usuario);

                $csvData[] = [
                    $usuario->id,
                    $usuario->username,
                    $usuario->email,
                    $usuarioFormateado['nombres'],
                    $usuarioFormateado['apellidos'],
                    $usuarioFormateado['tipo_documento'],
                    $usuarioFormateado['numero_documento'],
                    $usuarioFormateado['telefono'],
                    implode(', ', $usuario->roles ?? []),
                    $usuario->estado ?? 'active',
                    $usuario->created_at?->toISOString(),
                    $usuario->last_login?->toISOString() ?? ''
                ];
            }

            // Generar CSV
            $csvContent = $this->generarCSV($csvData);

            Log::info('Usuarios exportados exitosamente', [
                'total' => count($usuarios)
            ]);

            return ApiResource::success([
                'csv_data' => $csvData,
                'filename' => 'usuarios_export.csv'
            ], 'Datos exportados exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al exportar usuarios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al exportar usuarios', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Formatear usuario con datos de trabajador de API externa
     */
    private function formatearUsuarioConTrabajador(User $usuario): array
    {
        // Intentar obtener datos del trabajador desde API externa
        $trabajador = $this->obtenerDatosTrabajador($usuario->numero_documento);

        if ($trabajador) {
            return [
                'id' => $usuario->id,
                'nombres' => trim(($trabajador['prinom'] ?? '') . ' ' . ($trabajador['segnom'] ?? '')),
                'apellidos' => trim(($trabajador['priape'] ?? '') . ' ' . ($trabajador['segape'] ?? '')),
                'email' => $usuario->email,
                'username' => $usuario->username,
                'tipo_documento' => $trabajador['coddoc'] ?? '',
                'numero_documento' => $trabajador['cedtra'] ?? '',
                'rol' => ($usuario->roles ?? ['user_trabajador'])[0],
                'estado' => $usuario->estado ?? 'active',
                'ultimo_acceso' => $usuario->last_login?->toISOString() ?? '',
                'fecha_creacion' => $usuario->created_at?->toISOString(),
                'telefono' => $trabajador['telefono'] ?? '',
                'codigo_categoria' => $trabajador['codcat'] ?? '',
                'empresa_nit' => $trabajador['nit'] ?? '',
                'empresa_razon_social' => $trabajador['razsoc'] ?? '',
            ];
        } else {
            return [
                'id' => $usuario->id,
                'nombres' => trim(($usuario->nombres ?? '') . ' ' . ($usuario->apellidos ?? '')),
                'apellidos' => $usuario->apellidos ?? '',
                'email' => $usuario->email,
                'username' => $usuario->username,
                'tipo_documento' => $usuario->tipo_documento ?? '',
                'numero_documento' => $usuario->numero_documento ?? '',
                'rol' => ($usuario->roles ?? ['user_trabajador'])[0],
                'estado' => $usuario->estado ?? 'active',
                'ultimo_acceso' => $usuario->last_login?->toISOString() ?? '',
                'fecha_creacion' => $usuario->created_at?->toISOString(),
                'telefono' => $usuario->phone ?? '',
                'codigo_categoria' => 'D',
                'empresa_nit' => '',
                'empresa_razon_social' => '',
            ];
        }
    }

    /**
     * Obtener datos del trabajador desde API externa
     */
    private function obtenerDatosTrabajador(?string $cedtra): ?array
    {
        try {
            $response = $this->externalApiService->post('company/informacion_trabajador', [
                'cedtra' => $cedtra
            ]);

            if ($response['success'] && $response['data']) {
                return $response['data'];
            }

            Log::warning('No se pudieron obtener datos del trabajador', [
                'cedtra' => $cedtra,
                'response' => $response
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error al obtener datos del trabajador', [
                'cedtra' => $cedtra,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Obtener puntos de asesorías para un asesor
     */
    private function obtenerPuntosAsesorias(string $numeroDocumento): array
    {
        try {
            $response = $this->externalApiService->get('creditos/usuarios_creditos');

            if ($response['success'] && $response['data']) {
                return array_filter($response['data'], function ($user) use ($numeroDocumento) {
                    return ($user['estado'] ?? '') === 'A' &&
                        (string)($user['numero_documento'] ?? '') === (string)$numeroDocumento;
                });
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Error al obtener puntos de asesorías', [
                'numero_documento' => $numeroDocumento,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Validar asesor en API externa
     */
    private function validarAsesorExterno(string $numeroDocumento): array
    {
        try {
            $response = $this->externalApiService->post('creditos/usuarios_creditos', []);

            if ($response['status'] && $response['data']) {
                $puntosAsesorias = array_filter($response['data'], function ($user) use ($numeroDocumento) {
                    return (string)($user['numero_documento'] ?? '') === (string)$numeroDocumento;
                });

                if (empty($puntosAsesorias)) {
                    throw new \Exception("No se encontró el usuario con número de documento {$numeroDocumento} en el sistema de créditos");
                }

                return $puntosAsesorias;
            }

            throw new \Exception('El servicio externo no se encuentra disponible');
        } catch (\Exception $e) {
            Log::error('Error al validar asesor externo', [
                'numero_documento' => $numeroDocumento,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Obtener conteo de usuarios por rol
     */
    private function obtenerConteoRoles(): array
    {
        $conteo = [];

        $usuarios = User::all();
        foreach ($usuarios as $usuario) {
            $roles = $usuario->roles ?? [];
            foreach ($roles as $rol) {
                if (!isset($conteo[$rol])) {
                    $conteo[$rol] = 0;
                }
                $conteo[$rol]++;
            }
        }

        return $conteo;
    }

    /**
     * Obtener conteo de usuarios por estado
     */
    private function obtenerConteoEstados(): array
    {
        return User::selectRaw('is_active, COUNT(*) as count')
            ->groupBy('is_active')
            ->pluck('count', 'is_active')
            ->toArray();
    }

    /**
     * Generar contenido CSV
     */
    private function generarCSV(array $data): string
    {
        $csv = '';
        foreach ($data as $row) {
            $csv .= implode(',', array_map(function ($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        return $csv;
    }
}
