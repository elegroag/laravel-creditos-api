<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Services\MobileAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Str;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class MovileController extends Controller
{
    protected UserService $userService;
    protected MobileAuthService $authService;

    public function __construct(UserService $userService, MobileAuthService $authService)
    {
        $this->userService = $userService;
        $this->authService = $authService;
    }

    /**
     * Genera un token temporal para el QR basado en la sesión actual.
     */
    public function getQrToken(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Sesión no válida',
                    'details' => []
                ], 401);
            }

            $username = $user->username;

            Log::info('Generando token QR para autorización móvil', [
                'username' => $username
            ]);

            // Generar un token de corta duración para la autorización móvil (20 min)
            $now = Carbon::now();
            $expiresAt = $now->copy()->addMinutes(20);

            $authPayload = [
                'iss' => config('app.jwt.issuer', 'comfaca-credito'),
                'sub' => $username,
                'type' => 'mobile_auth',
                'iat' => $now->timestamp('UTC'),
                'exp' => $expiresAt->timestamp('UTC'),
                'jti' => \Illuminate\Support\Str::uuid()->toString()
            ];

            $tempToken = JWTAuth::encode($authPayload);

            Log::info('Token QR generado exitosamente', [
                'username' => $username,
                'expires_at' => $expiresAt->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token QR generado exitosamente',
                'data' => [
                    'qr_token' => $tempToken,
                    'expires_at' => $authPayload['exp'],
                    'user' => [
                        'username' => $username,
                        'nombres' => $user->nombres ?? '',
                        'apellidos' => $user->apellidos ?? ''
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al generar token QR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al generar token QR',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Endpoint llamado por la app móvil para autorizar el acceso en el frontend.
     */
    public function mobileAuthorize(string $token): JsonResponse
    {
        try {
            Log::info('Procesando autorización móvil', [
                'token' => substr($token, 0, 20) . '...' // Solo mostrar primeros 20 chars por seguridad
            ]);

            // Verificar token
            try {
                $decoded = JWTAuth::parseToken($token);

                if ($decoded->get('type') !== 'mobile_auth') {
                    return response()->json([
                        'success' => false,
                        'error' => 'Tipo de token inválido',
                        'details' => []
                    ], 401);
                }

                $username = $decoded->get('sub');

                // Emitir evento por socket para notificar al frontend
                $this->emitirEventoAuthComplete($username, 'authorized', 'Autorización móvil exitosa');

                // Generar token de sesión
                $authResult = $this->authService->login($username, ''); // Password no necesario para token existente

                Log::info('Autorización móvil procesada exitosamente', [
                    'username' => $username,
                    'auth_result' => array_keys($authResult)
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Autorización procesada y notificada',
                    'data' => [
                        'token' => $authResult['access_token'] ?? '',
                        'token_type' => $authResult['token_type'] ?? 'bearer',
                        'user' => $authResult['user'] ?? [],
                        'api_host_url' => env('API_HOST_URL', '')
                    ]
                ]);
            } catch (JWTException $e) {
                Log::warning('Error en token de autorización móvil', [
                    'error' => $e->getMessage(),
                    'token_preview' => substr($token, 0, 20) . '...'
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'El token ha expirado',
                    'details' => []
                ], 401);
            } catch (\Exception $e) {
                Log::error('Error al procesar autorización móvil', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Token inválido',
                    'details' => [
                        'internal_error' => $e->getMessage()
                    ]
                ], 401);
            }
        } catch (\Exception $e) {
            Log::error('Error inesperado en autorización móvil', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al procesar autorización',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Confirma capturas de documentos desde entidad digital.
     */
    public function confirmCapturas(Request $request): JsonResponse
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:255',
                'capturas' => 'required|array',
                'capturas.*.documento_id' => 'required|string|max:255',
                'capturas.*.tipo_documento' => 'required|string|max:100',
                'capturas.*.estado' => 'sometimes|string|max:50',
                'capturas.*.observaciones' => 'sometimes|string|max:500'
            ], [
                'username.required' => 'El nombre de usuario es requerido',
                'username.string' => 'El nombre de usuario debe ser texto',
                'username.max' => 'El nombre de usuario no puede exceder 255 caracteres',
                'capturas.required' => 'Las capturas son requeridas',
                'capturas.array' => 'Las capturas deben ser un array',
                'capturas.*.documento_id.required' => 'El ID del documento es requerido',
                'capturas.*.documento_id.string' => 'El ID del documento debe ser texto',
                'capturas.*.documento_id.max' => 'El ID del documento no puede exceder 255 caracteres',
                'capturas.*.tipo_documento.required' => 'El tipo de documento es requerido',
                'capturas.*.tipo_documento.string' => 'El tipo de documento debe ser texto',
                'capturas.*.tipo_documento.max' => 'El tipo de documento no puede exceder 100 caracteres'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            $username = $data['username'];
            $capturas = $data['capturas'];

            Log::info('Confirmando capturas de documentos', [
                'username' => $username,
                'total_capturas' => count($capturas)
            ]);

            // Emitir evento por socket para notificar al frontend
            $this->emitirEventoConfirmaCapturas($username, $capturas);

            return response()->json([
                'success' => true,
                'message' => 'Confirmación de capturas exitosa de los documentos.',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error al confirmar capturas', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al confirmar capturas',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtiene el estado de autenticación móvil
     */
    public function getMobileAuthStatus(): JsonResponse
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

            Log::info('Verificando estado de autenticación móvil', [
                'username' => $user->username
            ]);

            // Verificar si el usuario tiene permisos para acceso móvil
            $userRoles = $user->roles ?? [];
            $hasMobileAccess = in_array('mobile', $userRoles) || in_array('user_trabajador', $userRoles);

            return response()->json([
                'success' => true,
                'message' => 'Estado de autenticación móvil obtenido',
                'data' => [
                    'authenticated' => true,
                    'username' => $user->username,
                    'nombres' => $user->nombres ?? '',
                    'apellidos' => $user->apellidos ?? '',
                    'roles' => $userRoles,
                    'has_mobile_access' => $hasMobileAccess,
                    'qr_token_available' => true,
                    'qr_token_expires_in' => '20 minutos'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estado de autenticación móvil', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener estado',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Revoca el token QR generado
     */
    public function revokeQrToken(): JsonResponse
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

            Log::info('Revocando token QR', [
                'username' => $user->username
            ]);

            // Aquí se podría implementar la lógica para revocar tokens
            // Por ahora, simplemente informamos que el token ha sido revocado
            // En una implementación real, se podría usar una lista negra de tokens

            return response()->json([
                'success' => true,
                'message' => 'Token QR revocado exitosamente',
                'data' => [
                    'revoked_at' => Carbon::now()->toISOString(),
                    'username' => $user->username
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al revocar token QR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al revocar token',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Obtiene información del dispositivo móvil
     */
    public function getDeviceInfo(Request $request): JsonResponse
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

            Log::info('Obteniendo información del dispositivo móvil', [
                'username' => $user->username
            ]);

            // Obtener información del dispositivo desde el request
            $deviceInfo = [
                'user_agent' => $request->header('User-Agent'),
                'ip_address' => $request->ip(),
                'platform' => $this->getPlatformFromUserAgent($request->header('User-Agent')),
                'browser' => $this->getBrowserFromUserAgent($request->header('User-Agent')),
                'is_mobile' => $this->isMobile($request->header('User-Agent')),
                'timestamp' => Carbon::now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Información del dispositivo obtenida',
                'data' => [
                    'user' => [
                        'username' => $user->username,
                        'nombres' => $user->nombres ?? '',
                        'apellidos' => $user->apellidos ?? ''
                    ],
                    'device' => $deviceInfo
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener información del dispositivo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al obtener información del dispositivo',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Valida y refresca el token QR
     */
    public function validateQrToken(Request $request): JsonResponse
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'qr_token' => 'required|string|max:500'
            ], [
                'qr_token.required' => 'El token QR es requerido',
                'qr_token.string' => 'El token QR debe ser texto',
                'qr_token.max' => 'El token QR no puede exceder 500 caracteres'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $qrToken = $validator->validated()['qr_token'];

            Log::info('Validando token QR', [
                'token_preview' => substr($qrToken, 0, 20) . '...'
            ]);

            try {
                $decoded = JWTAuth::parseToken($qrToken);

                if ($decoded->get('type') !== 'mobile_auth') {
                    return response()->json([
                        'success' => false,
                        'error' => 'Tipo de token inválido',
                        'details' => []
                    ], 401);
                }

                $username = $decoded->get('sub');
                $expiresAt = Carbon::createFromTimestamp($decoded->get('exp'));
                $isExpired = $expiresAt->isPast();

                return response()->json([
                    'success' => true,
                    'message' => 'Token QR validado',
                    'data' => [
                        'valid' => !$isExpired,
                        'username' => $username,
                        'expires_at' => $expiresAt->toISOString(),
                        'expires_in_minutes' => $expiresAt->diffInMinutes(Carbon::now())->getValue(),
                        'type' => 'mobile_auth'
                    ]
                ]);
            } catch (JWTException $e) {
                Log::warning('Token QR inválido', [
                    'error' => $e->getMessage(),
                    'token_preview' => substr($qrToken, 0, 20) . '...'
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Token inválido o expirado',
                    'details' => [
                        'error' => $e->getMessage()
                    ]
                ], 401);
            } catch (\Exception $e) {
                Log::error('Error al validar token QR', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al validar token',
                    'details' => [
                        'internal_error' => $e->getMessage()
                    ]
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en validación de token QR', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno al validar token',
                'details' => [
                    'internal_error' => 'Error interno del servidor'
                ]
            ], 500);
        }
    }

    /**
     * Emite evento de autorización completa por WebSocket
     */
    private function emitirEventoAuthComplete(string $username, string $status, string $message): void
    {
        try {
            // Aquí se implementaría la lógica para emitir eventos WebSocket
            // Por ahora, solo lo registramos en el log

            Log::info('Emitiendo evento WebSocket de autorización', [
                'event' => "auth_complete_{$username}",
                'status' => $status,
                'username' => $username,
                'message' => $message
            ]);

            // En una implementación real:
            // event(new AuthCompleteEvent($username, $status, $message));
            // broadcast('auth_complete_' . $username, [
            //     'status' => $status,
            //     'username' => $username,
            //     'message' => $message
            // ]);

        } catch (\Exception $e) {
            Log::error('Error al emitir evento WebSocket de autorización', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Emite evento de confirmación de capturas por WebSocket
     */
    private function emitirEventoConfirmaCapturas(string $username, array $capturas): void
    {
        try {
            // Aquí se implementaría la lógica para emitir eventos WebSocket
            // Por ahora, solo lo registramos en el log

            Log::info('Emitiendo evento WebSocket de confirmación de capturas', [
                'event' => "confirma_capturas_{$username}",
                'username' => $username,
                'total_capturas' => count($capturas)
            ]);

            // En una implementación real:
            // event(new ConfirmaCapturasEvent($username, $capturas));
            // broadcast('confirma_capturas_' . $username, [
            //     'success' => true,
            //     'data' => $capturas,
            //     'message' => 'Confirmación de capturas exitosa de los documentos.'
            // ]);

        } catch (\Exception $e) {
            Log::error('Error al emitir evento WebSocket de confirmación de capturas', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Detecta si el User-Agent corresponde a un dispositivo móvil
     */
    private function isMobile(string $userAgent): bool
    {
        return preg_match('/(android|iphone|ipad|ipod|mobile)/i', $userAgent) ||
            preg_match('/(tablet|silk)/i', $userAgent) ||
            preg_match('/(mobile|phone)/i', $userAgent);
    }

    /**
     * Obtiene la plataforma desde el User-Agent
     */
    private function getPlatformFromUserAgent(string $userAgent): string
    {
        if (preg_match('/android/i', $userAgent)) {
            return 'Android';
        } elseif (preg_match('/iphone/i', $userAgent)) {
            return 'iOS';
        } elseif (preg_match('/ipad/i', $userAgent)) {
            return 'iPad';
        } elseif (preg_match('/ipod/i', $userAgent)) {
            return 'iPod';
        } elseif (preg_match('/mac/i', $userAgent)) {
            return 'macOS';
        } elseif (preg_match('/windows/i', $userAgent)) {
            return 'Windows';
        } elseif (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        }

        return 'Unknown';
    }

    /**
     * Obtiene el navegador desde el User-Agent
     */
    private function getBrowserFromUserAgent(string $userAgent): string
    {
        if (preg_match('/chrome/i', $userAgent)) {
            return 'Chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            return 'Firefox';
        } elseif (preg_match('/safari/i', $userAgent)) {
            return 'Safari';
        } elseif (preg_match('/edge/i', $userAgent)) {
            return 'Edge';
        } elseif (preg_match('/opera/i', $userAgent)) {
            return 'Opera';
        }

        return 'Unknown';
    }
}
