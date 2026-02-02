<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        // Obtener datos del usuario autenticado desde JWT middleware
        $authenticatedUser = $request->get('authenticated_user');

        if (!$authenticatedUser) {
            return response()->json([
                'success' => false,
                'error' => 'No autenticado',
                'message' => 'Token de autenticación requerido',
                'details' => []
            ], 401);
        }

        // Extraer datos del usuario
        $userData = $authenticatedUser['user'] ?? [];
        $userId = $authenticatedUser['user_id'] ?? null;

        // Los administradores tienen todos los permisos
        if ($this->isAdmin($userData)) {
            return $next($request);
        }

        $userPermissions = $this->getUserPermissions($userData);

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
                'user_id' => $userId,
                'username' => $userData['username'] ?? 'unknown',
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
     * Obtiene los permisos del usuario desde datos JWT
     */
    private function getUserPermissions(array $userData): array
    {
        return $userData['permissions'] ?? [];
    }

    /**
     * Verifica si el usuario es administrador desde datos JWT
     */
    private function isAdmin(array $userData): bool
    {
        $userRoles = $this->getUserRoles($userData);
        return in_array('administrator', $userRoles) || in_array('admin', $userRoles);
    }

    /**
     * Obtiene los roles del usuario desde datos JWT
     */
    private function getUserRoles(array $userData): array
    {
        return $userData['roles'] ?? [];
    }
}
