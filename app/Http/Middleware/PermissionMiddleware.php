<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$permissions
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => 'No autenticado',
                'message' => 'Token de autenticación requerido',
                'details' => []
            ], 401);
        }

        $user = Auth::user();

        // Los administradores tienen todos los permisos
        if ($this->isAdmin($user)) {
            return $next($request);
        }

        $userPermissions = $this->getUserPermissions($user);

        // Verificar si el usuario tiene alguno de los permisos requeridos
        $hasRequiredPermission = false;
        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                $hasRequiredPermission = true;
                break;
            }
        }

        if (!$hasRequiredPermission) {
            Log::warning('Acceso denegado: permisos requeridos', [
                'user_id' => $user->id,
                'username' => $user->username,
                'required_permissions' => $permissions,
                'user_permissions' => $userPermissions
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Acceso denegado',
                'message' => 'Se requieren permisos específicos para acceder',
                'details' => [
                    'required_permissions' => $permissions,
                    'user_permissions' => $userPermissions
                ]
            ], 403);
        }

        return $next($request);
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
        $userRoles = $this->getUserRoles($user);
        return in_array('administrator', $userRoles) || in_array('admin', $userRoles);
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
}
