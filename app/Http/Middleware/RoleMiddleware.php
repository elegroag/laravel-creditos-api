<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
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
        $userRoles = $this->getUserRoles($userData);

        // Verificar si el usuario tiene alguno de los roles requeridos
        $hasRequiredRole = false;
        foreach ($roles as $role) {
            if (in_array($role, $userRoles)) {
                $hasRequiredRole = true;
                break;
            }
        }

        if (!$hasRequiredRole) {
            Log::warning('Acceso denegado: roles requeridos', [
                'user_id' => $userId,
                'username' => $userData['username'] ?? 'unknown',
                'required_roles' => $roles,
                'user_roles' => $userRoles
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Acceso denegado',
                'message' => 'Se requieren roles específicos para acceder',
                'details' => [
                    'required_roles' => $roles,
                    'user_roles' => $userRoles
                ]
            ], 403);
        }

        return $next($request);
    }

    /**
     * Obtiene los roles del usuario desde datos JWT
     */
    private function getUserRoles(array $userData): array
    {
        return $userData['roles'] ?? [];
    }
}
