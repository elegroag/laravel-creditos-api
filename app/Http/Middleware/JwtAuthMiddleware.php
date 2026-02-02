<?php

namespace App\Http\Middleware;

use App\Services\AuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class JwtAuthMiddleware
{
    protected AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Extraer token del header Authorization
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token requerido',
                    'message' => 'Authorization Bearer token requerido'
                ], 401);
            }

            // Verificar token usando AuthenticationService
            $userData = $this->authService->verifyToken($token);

            if (!$userData) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token inválido',
                    'message' => 'El token proporcionado no es válido o ha expirado'
                ], 401);
            }

            // Agregar datos del usuario al request para uso posterior
            $request->merge([
                'authenticated_user' => $userData,
                'user_id' => $userData['user_id']
            ]);

            return $next($request);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de autenticación',
                'message' => 'No se pudo verificar el token: ' . $e->getMessage()
            ], 401);
        }
    }
}
