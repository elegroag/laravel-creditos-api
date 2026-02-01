<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     * Obtiene el perfil del usuario autenticado.
     *
     * Returns:
     *     Datos del perfil del usuario
     */
    public function obtenerPerfil(): JsonResponse
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

            Log::info('Obteniendo perfil para usuario', ['username' => $username]);

            // Obtener usuario completo
            $usuario = $this->userService->getByUsername($username);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado',
                    'details' => []
                ], 404);
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

            return response()->json([
                'success' => true,
                'data' => $perfilData,
                'message' => 'Perfil obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener perfil',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
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
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $updateData = $validator->validated();

            Log::info('Actualizando perfil para usuario', [
                'username' => $username,
                'update_fields' => array_keys($updateData)
            ]);

            $usuario = $this->userService->getByUsername($username);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado',
                    'details' => []
                ], 404);
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
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo actualizar el perfil',
                    'details' => []
                ], 400);
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

            return response()->json([
                'success' => true,
                'data' => $perfilData,
                'message' => 'Perfil actualizado exitosamente'
            ]);
        } catch (ValidationException $e) {
            Log::error('Error de validación al actualizar perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Datos inválidos',
                'details' => $e->errors()
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error actualizando perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al actualizar perfil',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
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
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                    'details' => []
                ], 401);
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
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $passwordData = $validator->validated();
            $currentPassword = $passwordData['current_password'];
            $newPassword = $passwordData['new_password'];

            Log::info('Intentando cambiar contraseña', ['username' => $user->username]);

            // Obtener usuario para verificar que existe
            $usuario = $this->userService->getByUsername($user->username);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado',
                    'details' => []
                ], 404);
            }

            // Verificar contraseña actual
            if (!Hash::check($currentPassword, $usuario->password)) {
                Log::warning('Contraseña actual incorrecta', ['username' => $user->username]);

                return response()->json([
                    'success' => false,
                    'error' => 'La contraseña actual es incorrecta',
                    'details' => []
                ], 400);
            }

            // Cambiar contraseña
            $usuario->password = Hash::make($newPassword);
            $usuario->save();

            Log::info('Contraseña cambiada exitosamente', ['username' => $user->username]);

            return response()->json([
                'success' => true,
                'message' => 'Contraseña cambiada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cambiar contraseña', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al cambiar contraseña',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
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
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                    'details' => []
                ], 401);
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
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $queryParams = $validator->validated();
            $limit = $queryParams['limit'] ?? 20;
            $skip = $queryParams['skip'] ?? 0;
            $type = $queryParams['type'] ?? 'todos';

            Log::info('Obteniendo actividad del usuario', [
                'username' => $user->username,
                'limit' => $limit,
                'skip' => $skip,
                'type' => $type
            ]);

            // Obtener solicitudes recientes del usuario
            $solicitudes = $this->solicitudService->getByOwner($user->username, $skip, $limit);

            // Formatear como actividad
            $actividad = [];

            foreach ($solicitudes as $solicitud) {
                $actividad[] = [
                    'type' => 'solicitud',
                    'id' => $solicitud['id'],
                    'titulo' => 'Solicitud de crédito - ' . ($solicitud['payload']['solicitud']['tipcre'] ?? 'N/A'),
                    'estado' => $solicitud['estado'],
                    'fecha' => $solicitud['created_at'],
                    'descripcion' => 'Estado: ' . $solicitud['estado']
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
                'username' => $user->username,
                'total' => count($actividad)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Actividad obtenida exitosamente',
                'data' => $activityData
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener actividad del usuario', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener actividad',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas del perfil del usuario
     */
    public function obtenerEstadisticasPerfil(): JsonResponse
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

            Log::info('Obteniendo estadísticas del perfil', ['username' => $username]);

            // Obtener estadísticas básicas
            $solicitudes = $this->solicitudService->getByOwner($username, 0, 1000);

            $estadisticas = [
                'total_solicitudes' => count($solicitudes),
                'solicitudes_por_estado' => [],
                'ultima_actividad' => null,
                'fecha_registro' => $user->created_at?->toISOString(),
                'ultima_actualizacion' => $user->updated_at?->toISOString(),
                'roles' => $user->roles ?? [],
                'perfil_completo' => $this->verificarPerfilCompleto($user)
            ];

            // Agrupar solicitudes por estado
            $estados = [];
            foreach ($solicitudes as $solicitud) {
                $estado = $solicitud['estado'];
                if (!isset($estados[$estado])) {
                    $estados[$estado] = 0;
                }
                $estados[$estado]++;
            }
            $estadisticas['solicitudes_por_estado'] = $estados;

            // Última actividad
            if (!empty($solicitudes)) {
                $ultimaSolicitud = $solicitudes[0];
                $estadisticas['ultima_actividad'] = [
                    'tipo' => 'solicitud',
                    'fecha' => $ultimaSolicitud['created_at'],
                    'descripcion' => 'Última solicitud: ' . $ultimaSolicitud['estado']
                ];
            }

            Log::info('Estadísticas del perfil obtenidas', [
                'username' => $username,
                'total_solicitudes' => $estadisticas['total_solicitudes']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estadísticas obtenidas exitosamente',
                'data' => $estadisticas
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas del perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
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
    public function obtenerConfiguracionPerfil(): JsonResponse
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

            Log::info('Configuración del perfil obtenida', ['username' => $user->username]);

            return response()->json([
                'success' => true,
                'message' => 'Configuración obtenida exitosamente',
                'data' => $configuracion
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener configuración del perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener configuración',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Actualiza configuración del perfil
     */
    public function actualizarConfiguracionPerfil(Request $request): JsonResponse
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

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'notificaciones' => 'sometimes|array',
                'privacidad' => 'sometimes|array',
                'apariencia' => 'sometimes|array',
                'seguridad' => 'sometimes|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $configData = $validator->validated();

            Log::info('Actualizando configuración del perfil', [
                'username' => $user->username,
                'config_keys' => array_keys($configData)
            ]);

            // Aquí se guardaría la configuración en la base de datos
            // Por ahora, solo retornamos confirmación

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada exitosamente',
                'data' => $configData
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar configuración del perfil', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al actualizar configuración',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }
}
