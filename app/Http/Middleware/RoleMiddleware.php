<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => 'No autenticado',
                'message' => 'Token de autenticación requerido',
                'details' => []
            ], 401);
        }

        $user = Auth::user();
        $userRoles = $this->getUserRoles($user);

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
                'user_id' => $user->id,
                'username' => $user->username,
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
