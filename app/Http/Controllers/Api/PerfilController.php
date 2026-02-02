<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Services\UserService;
use App\Services\SolicitudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class PerfilController extends Controller
{
    protected UserService $userService;
    protected SolicitudService $solicitudService;

    public function __construct(UserService $userService, SolicitudService $solicitudService)
    {
        $this->userService = $userService;
        $this->solicitudService = $solicitudService;
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
     * Obtiene el perfil del usuario autenticado.
     *
     * Returns:
     *     Datos del perfil del usuario
     */
    public function obtenerPerfil(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            Log::info('Obteniendo perfil para usuario', ['username' => $username]);

            // Obtener usuario completo
            $usuario = $this->userService->getByUsername($username);

            if (!$usuario) {
                return ErrorResource::notFound('Usuario no encontrado')->response();
            }

            // Construir full_name
            $fullName = null;
            if ($usuario->nombres && $usuario->apellidos) {
                $fullName = $usuario->nombres . ' ' . $usuario->apellidos;
            } elseif ($usuario->nombres) {
                $fullName = $usuario->nombres;
            }

            // Formatear respuesta
            $perfilData = [
                'id' => (string) $usuario->id,
                'username' => $usuario->username,
                'email' => $usuario->email,
                'full_name' => $fullName,
                'phone' => $usuario->phone,
                'roles' => $usuario->roles ?? [],
                'disabled' => $usuario->disabled ?? false,
                'tipo_documento' => $usuario->tipo_documento,
                'numero_documento' => $usuario->numero_documento,
                'nombres' => $usuario->nombres,
                'apellidos' => $usuario->apellidos,
                'created_at' => $usuario->created_at?->toISOString(),
                'updated_at' => $usuario->updated_at?->toISOString()
            ];

            Log::info('Perfil obtenido exitosamente', ['username' => $username]);

            return ApiResource::success($perfilData, 'Perfil obtenido exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error obteniendo perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return ErrorResource::serverError('Error interno al obtener perfil', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Actualiza el perfil del usuario autenticado.
     *
     * Args:
     * update_data: Datos de actualización validados
     *
     * Returns:
     *     Perfil actualizado
     */
    public function actualizarPerfil(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'email' => 'sometimes|email|max:255',
                'phone' => 'sometimes|string|max:20',
                'nombres' => 'sometimes|string|max:100',
                'apellidos' => 'sometimes|string|max:100',
                'tipo_documento' => 'sometimes|string|max:20',
                'numero_documento' => 'sometimes|string|max:20',
                'roles' => 'sometimes|array',
                'disabled' => 'sometimes|boolean'
            ], [
                'email.email' => 'El email debe ser válido',
                'phone.string' => 'El teléfono debe ser texto',
                'nombres.string' => 'Los nombres deben ser texto',
                'apellidos.string' => 'Los apellidos deben ser texto',
                'tipo_documento.string' => 'El tipo de documento debe ser texto',
                'numero_documento.string' => 'El número de documento debe ser texto',
                'roles.array' => 'Los roles deben ser un array',
                'disabled.boolean' => 'El campo disabled debe ser booleano'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $updateData = $validator->validated();

            Log::info('Actualizando perfil para usuario', [
                'username' => $username,
                'update_fields' => array_keys($updateData)
            ]);

            $usuario = $this->userService->getByUsername($username);

            if (!$usuario) {
                return ErrorResource::notFound('Usuario no encontrado')->response();
            }

            // Preparar datos para actualización con manejo robusto de roles
            $updateDataClean = $updateData;

            // Manejar roles - asegurar que sea un array
            if (isset($updateDataClean['roles']) && $updateDataClean['roles'] !== null) {
                $roles = $updateDataClean['roles'];
                if (is_string($roles)) {
                    // Si vienen como string separado por comas, convertir a lista
                    $updateDataClean['roles'] = array_map('trim', explode(',', $roles));
                } elseif (!is_array($roles)) {
                    // Si no es lista ni string, convertir a lista vacía
                    $updateDataClean['roles'] = [];
                }
            }

            // Actualizar usuario
            $usuarioActualizado = $this->userService->update($usuario->id, $updateDataClean);

            if (!$usuarioActualizado) {
                return ErrorResource::errorResponse('No se pudo actualizar el perfil')
                    ->response()
                    ->setStatusCode(400);
            }

            // Construir full_name
            $fullName = null;
            if ($usuarioActualizado->nombres && $usuarioActualizado->apellidos) {
                $fullName = $usuarioActualizado->nombres . ' ' . $usuarioActualizado->apellidos;
            } elseif ($usuarioActualizado->nombres) {
                $fullName = $usuarioActualizado->nombres;
            }

            // Formatear respuesta
            $perfilData = [
                'id' => (string) $usuarioActualizado->id,
                'username' => $usuarioActualizado->username,
                'email' => $usuarioActualizado->email,
                'full_name' => $fullName,
                'phone' => $usuarioActualizado->phone,
                'roles' => $usuarioActualizado->roles ?? [],
                'disabled' => $usuarioActualizado->disabled ?? false,
                'tipo_documento' => $usuarioActualizado->tipo_documento,
                'numero_documento' => $usuarioActualizado->numero_documento,
                'nombres' => $usuarioActualizado->nombres,
                'apellidos' => $usuarioActualizado->apellidos,
                'created_at' => $usuarioActualizado->created_at?->toISOString(),
                'updated_at' => $usuarioActualizado->updated_at?->toISOString()
            ];

            Log::info('Perfil actualizado exitosamente', ['username' => $username]);

            return ApiResource::success($perfilData, 'Perfil actualizado exitosamente')->response();
        } catch (ValidationException $e) {
            Log::error('Error de validación al actualizar perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return ErrorResource::validationError($e->errors(), 'Datos inválidos')
                ->response()
                ->setStatusCode(422);
        } catch (\Exception $e) {
            Log::error('Error actualizando perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al actualizar perfil', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Cambia la contraseña del usuario autenticado.
     *
     * Args:
     * password_data: Datos de cambio de contraseña validados
     *
     * Returns:
     *     Confirmación de cambio
     */
    public function cambiarPassword(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string|min:8',
                'new_password' => 'required|string|min:8|confirmed',
                'new_password_confirmation' => 'required|string|min:8'
            ], [
                'current_password.required' => 'La contraseña actual es requerida',
                'current_password.min' => 'La contraseña actual debe tener al menos 8 caracteres',
                'new_password.required' => 'La nueva contraseña es requerida',
                'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres',
                'new_password.confirmed' => 'La confirmación de la nueva contraseña es requerida',
                'new_password_confirmation.required' => 'La confirmación de la nueva contraseña es requerida',
                'new_password_confirmation.min' => 'La confirmación debe tener al menos 8 caracteres'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $passwordData = $validator->validated();
            $currentPassword = $passwordData['current_password'];
            $newPassword = $passwordData['new_password'];

            Log::info('Intentando cambiar contraseña', ['username' => $username]);

            // Obtener usuario para verificar que existe
            $usuario = $this->userService->getByUsername($username);

            if (!$usuario) {
                return ErrorResource::notFound('Usuario no encontrado')->response();
            }

            // Verificar contraseña actual
            if (!Hash::check($currentPassword, $usuario->password)) {
                Log::warning('Contraseña actual incorrecta', ['username' => $username]);

                return ErrorResource::errorResponse('La contraseña actual es incorrecta')
                    ->response()
                    ->setStatusCode(400);
            }

            // Cambiar contraseña
            $usuario->password = Hash::make($newPassword);
            $usuario->save();

            Log::info('Contraseña cambiada exitosamente', ['username' => $username]);

            return ApiResource::success(null, 'Contraseña cambiada exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al cambiar contraseña', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al cambiar contraseña', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtiene la actividad reciente del usuario (solicitudes, documentos, etc.).
     *
     * Args:
     * query_params: Parámetros de consulta validados
     *
     * Returns:
     *     Actividad reciente del usuario
     */
    public function obtenerActividadUsuario(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            // Validar parámetros de consulta
            $validator = Validator::make($request->all(), [
                'limit' => 'sometimes|integer|min:1|max:100',
                'skip' => 'sometimes|integer|min:0',
                'type' => 'sometimes|string|in:solicitudes,documentos,todos'
            ], [
                'limit.integer' => 'El límite debe ser un número entero',
                'limit.min' => 'El límite debe ser al menos 1',
                'limit.max' => 'El límite no puede exceder 100',
                'skip.integer' => 'El offset debe ser un número entero',
                'skip.min' => 'El offset no puede ser negativo',
                'type.in' => 'El tipo debe ser: solicitudes, documentos o todos'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Parámetros inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $queryParams = $validator->validated();
            $limit = $queryParams['limit'] ?? 20;
            $skip = $queryParams['skip'] ?? 0;
            $type = $queryParams['type'] ?? 'todos';

            Log::info('Obteniendo actividad del usuario', [
                'username' => $username,
                'limit' => $limit,
                'skip' => $skip,
                'type' => $type
            ]);

            // Obtener solicitudes recientes del usuario
            $solicitudes = $this->solicitudService->getByOwner($username, $skip, $limit);

            // Formatear como actividad
            $actividad = [];

            foreach ($solicitudes as $solicitud) {
                $actividad[] = [
                    'type' => 'solicitud',
                    'id' => $solicitud['id'] ?? 'unknown',
                    'titulo' => 'Solicitud de crédito - ' . ($solicitud['payload']['solicitud']['tipcre'] ?? 'N/A'),
                    'estado' => $solicitud['estado'] ?? 'desconocido',
                    'fecha' => $solicitud['created_at'] ?? null,
                    'descripcion' => 'Estado: ' . ($solicitud['estado'] ?? 'desconocido')
                ];
            }

            $activityData = [
                'actividad' => $actividad,
                'total' => count($actividad),
                'limit' => $limit,
                'skip' => $skip,
                'type' => $type
            ];

            Log::info('Actividad del usuario obtenida', [
                'username' => $username,
                'total' => count($actividad)
            ]);

            return ApiResource::success($activityData, 'Actividad obtenida exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener actividad del usuario', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al obtener actividad', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtiene estadísticas del perfil del usuario
     */
    public function obtenerEstadisticasPerfil(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            Log::info('Obteniendo estadísticas del perfil', ['username' => $username]);

            // Obtener estadísticas básicas
            $solicitudes = $this->solicitudService->getByOwner($username, 0, 1000);

            // Obtener datos del usuario para fechas y roles
            $usuario = $this->userService->getByUsername($username);

            $estadisticas = [
                'total_solicitudes' => count($solicitudes),
                'solicitudes_por_estado' => [],
                'ultima_actividad' => null,
                'fecha_registro' => $usuario->created_at?->toISOString(),
                'ultima_actualizacion' => $usuario->updated_at?->toISOString(),
                'roles' => $usuario->roles ?? [],
                'perfil_completo' => $this->verificarPerfilCompleto($usuario)
            ];

            // Agrupar solicitudes por estado
            $estados = [];
            foreach ($solicitudes as $solicitud) {
                $estado = $solicitud['estado'] ?? 'desconocido';
                if (!isset($estados[$estado])) {
                    $estados[$estado] = 0;
                }
                $estados[$estado]++;
            }
            $estadisticas['solicitudes_por_estado'] = $estados;

            // Última actividad
            if (!empty($solicitudes) && is_array($solicitudes) && isset($solicitudes[0])) {
                $ultimaSolicitud = $solicitudes[0];
                $estadisticas['ultima_actividad'] = [
                    'tipo' => 'solicitud',
                    'fecha' => $ultimaSolicitud['created_at'] ?? null,
                    'descripcion' => 'Última solicitud: ' . ($ultimaSolicitud['estado'] ?? 'desconocido')
                ];
            }

            Log::info('Estadísticas del perfil obtenidas', [
                'username' => $username,
                'total_solicitudes' => $estadisticas['total_solicitudes']
            ]);

            return ApiResource::success($estadisticas, 'Estadísticas obtenidas exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas del perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
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
     * Verifica si el perfil del usuario está completo
     */
    private function verificarPerfilCompleto($user): bool
    {
        $camposRequeridos = ['email', 'phone', 'nombres', 'apellidos'];

        foreach ($camposRequeridos as $campo) {
            if (empty($user->$campo)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene configuración del perfil (preferencias, etc.)
     */
    public function obtenerConfiguracionPerfil(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            // Configuración por defecto (podría venir de base de datos)
            $configuracion = [
                'notificaciones' => [
                    'email' => true,
                    'sms' => false,
                    'push' => true
                ],
                'privacidad' => [
                    'mostrar_perfil_publico' => false,
                    'compartir_datos' => false
                ],
                'apariencia' => [
                    'tema' => 'light',
                    'idioma' => 'es'
                ],
                'seguridad' => [
                    'autenticacion_doble_factor' => false,
                    'sesion_recordar' => true
                ]
            ];

            Log::info('Configuración del perfil obtenida', ['username' => $username]);

            return ApiResource::success($configuracion, 'Configuración obtenida exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al obtener configuración del perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return ErrorResource::serverError('Error interno al obtener configuración', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Actualiza configuración del perfil
     */
    public function actualizarConfiguracionPerfil(Request $request): JsonResponse
    {
        try {
            // Obtener datos del usuario desde el middleware JWT
            $userData = $this->getAuthenticatedUser($request);
            $username = $userData['username'];

            if (!$username) {
                return ErrorResource::authError('Usuario no autenticado')->response()->setStatusCode(401);
            }

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'notificaciones' => 'sometimes|array',
                'privacidad' => 'sometimes|array',
                'apariencia' => 'sometimes|array',
                'seguridad' => 'sometimes|array'
            ]);

            if ($validator->fails()) {
                return ErrorResource::validationError($validator->errors()->toArray(), 'Datos inválidos')
                    ->response()
                    ->setStatusCode(422);
            }

            $configData = $validator->validated();

            Log::info('Actualizando configuración del perfil', [
                'username' => $username,
                'config_keys' => array_keys($configData)
            ]);

            // Aquí se guardaría la configuración en la base de datos
            // Por ahora, solo retornamos confirmación

            $configuracion = $configData;

            return ApiResource::success($configuracion, 'Configuración actualizada exitosamente')->response();
        } catch (\Exception $e) {
            Log::error('Error al actualizar configuración del perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error interno al actualizar configuración', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getMessage()
            ])->response();
        }
    }
}
