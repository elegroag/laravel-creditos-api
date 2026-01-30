<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $role
     * @param  string|null  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $role = null, ?string $permission = null)
    {
        try {
            // Verificar si el usuario está autenticado
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autenticado',
                    'message' => 'Token de autenticación requerido',
                    'details' => []
                ], 401);
            }

            $user = Auth::user();

            // Verificar si el usuario está activo
            if (isset($user->is_active) && !$user->is_active) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario inactivo',
                    'message' => 'Tu cuenta ha sido desactivada',
                    'details' => []
                ], 403);
            }

            // Verificar rol requerido
            if ($role && !$this->hasRole($user, $role)) {
                Log::warning('Acceso denegado: rol requerido', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'required_role' => $role,
                    'user_roles' => $this->getUserRoles($user)
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Acceso denegado',
                    'message' => "Se requiere el rol: {$role}",
                    'details' => [
                        'required_role' => $role,
                        'user_roles' => $this->getUserRoles($user)
                    ]
                ], 403);
            }

            // Verificar permiso requerido
            if ($permission && !$this->hasPermission($user, $permission)) {
                Log::warning('Acceso denegado: permiso requerido', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'required_permission' => $permission,
                    'user_permissions' => $this->getUserPermissions($user)
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Acceso denegado',
                    'message' => "Se requiere el permiso: {$permission}",
                    'details' => [
                        'required_permission' => $permission,
                        'user_permissions' => $this->getUserPermissions($user)
                    ]
                ], 403);
            }

            // Agregar información del usuario al request para uso posterior
            $request->merge([
                'current_user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'roles' => $this->getUserRoles($user),
                    'permissions' => $this->getUserPermissions($user),
                    'is_admin' => $this->isAdmin($user),
                    'is_adviser' => $this->isAdviser($user)
                ]
            ]);

            // Log de acceso exitoso
            Log::info('Acceso autorizado', [
                'user_id' => $user->id,
                'username' => $user->username,
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip()
            ]);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Error en AuthMiddleware', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'path' => $request->path(),
                'method' => $request->method()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error de autenticación',
                'message' => 'Error interno al verificar autenticación',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Verifica si el usuario tiene un rol específico
     */
    private function hasRole($user, string $role): bool
    {
        $userRoles = $this->getUserRoles($user);
        return in_array($role, $userRoles);
    }

    /**
     * Verifica si el usuario tiene un permiso específico
     */
    private function hasPermission($user, string $permission): bool
    {
        // Los administradores tienen todos los permisos
        if ($this->isAdmin($user)) {
            return true;
        }

        $userPermissions = $this->getUserPermissions($user);
        return in_array($permission, $userPermissions);
    }

    /**
     * Obtiene los roles del usuario
     */
    private function getUserRoles($user): array
    {
        if (isset($user->roles)) {
            return is_array($user->roles) ? $user->roles : json_decode($user->roles, true) ?? [];
        }
        return [];
    }

    /**
     * Obtiene los permisos del usuario
     */
    private function getUserPermissions($user): array
    {
        if (isset($user->permissions)) {
            return is_array($user->permissions) ? $user->permissions : json_decode($user->permissions, true) ?? [];
        }
        return [];
    }

    /**
     * Verifica si el usuario es administrador
     */
    private function isAdmin($user): bool
    {
        return $this->hasRole($user, 'administrator') || $this->hasRole($user, 'admin');
    }

    /**
     * Verifica si el usuario es asesor
     */
    private function isAdviser($user): bool
    {
        return $this->hasRole($user, 'adviser') || $this->hasRole($user, 'user_trabajador');
    }
}
