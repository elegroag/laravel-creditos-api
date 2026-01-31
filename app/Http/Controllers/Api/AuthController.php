<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthenticationService;
use App\Services\UserService;
use App\Services\TrabajadorService;
use App\Services\SenderEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected UserService $userService;
    protected TrabajadorService $trabajadorService;
    protected AuthenticationService $authService;

    public function __construct(
        UserService $userService,
        TrabajadorService $trabajadorService,
        AuthenticationService $authService
    ) {
        $this->userService = $userService;
        $this->trabajadorService = $trabajadorService;
        $this->authService = $authService;
    }

    /**
     * Login user and return token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Rate limiting
            $this->authService->checkRateLimit('auth.login', 30, 60);

            $username = trim($validated['username']);
            $password = $validated['password'];

            // Normalize username
            $normalizedUsername = $this->authService->normalizeUsername($username);

            if (!$normalizedUsername || !$password) {
                return response()->json([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }

            // Rate limiting by user
            $this->authService->checkRateLimit("auth.login.user:{$normalizedUsername}", 10, 60);

            // Authenticate using AuthenticationService
            $authResult = $this->authService->login($normalizedUsername, $password);

            // Get additional trabajador data if user has documento
            $trabajadorData = null;
            if (isset($authResult['user']['numero_documento']) && $authResult['user']['numero_documento']) {
                try {
                    $trabajadorData = $this->trabajadorService->obtenerDatosTrabajador($authResult['user']['numero_documento']);
                } catch (\Exception $e) {
                    Log::warning("No se pudieron obtener datos del trabajador: " . $e->getMessage());
                }
            }

            // Add trabajador data if available
            if ($trabajadorData) {
                $authResult['user']['trabajador'] = $trabajadorData;
            }

            return response()->json($authResult);
        } catch (\Exception $e) {
            Log::error('Error en login: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error de autenticación'
            ], 401);
        }
    }

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Rate limiting
            $this->authService->checkRateLimit('auth.register', 10, 60);

            // Extract data
            Log::info('Validated data: ' . json_encode($validated));
            $tipoDocumento = $validated['tipo_documento'];
            $numeroDocumento = $validated['numero_documento'];
            $nombres = $validated['nombres'];
            $apellidos = $validated['apellidos'];
            $telefono = $validated['telefono'];
            $email = $validated['email'];
            $password = $validated['password'];
            $username = $validated['username'];

            if (!$this->authService->isValidUsername($username)) {
                return response()->json([
                    'error' => 'Usuario inválido'
                ], 400);
            }

            // Check if username already exists
            if ($this->userService->getByUsername($username)) {
                return response()->json([
                    'error' => 'El nombre de usuario ya está en uso'
                ], 400);
            }

            // Check if email already exists
            if ($this->userService->getByEmail($email)) {
                return response()->json([
                    'error' => 'El correo electrónico ya está en uso'
                ], 400);
            }

            // Build full name
            $fullName = trim($nombres . ' ' . $apellidos);

            $userData = [
                'username' => $username,
                'email' => $email,
                'password' => $password, // Pass plain password, UserService will hash it
                'password_confirmation' => $password, // Add confirmation
                'full_name' => $fullName,
                'phone' => $telefono,
                'tipo_documento' => $tipoDocumento,
                'numero_documento' => $numeroDocumento,
                'nombres' => trim($nombres),
                'apellidos' => trim($apellidos),
                'roles' => ['user_trabajador'],
                'disabled' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Use AuthenticationService to create user and generate token
            $authResult = $this->authService->register($userData);
            $token = $authResult['access_token'];

            $pin = $this->userService->generatePin();

            // Use the user data from authResult
            $userResponse = $authResult['user'];
            $userResponse['pin'] = $pin;

            // Crear instancia del servicio
            $sender = new SenderEmail([
                'asunto' => 'Comfaca Credito - Verificación de correo'
            ]);
            $body = '
                <html>
                <head>
                    <title>Gracias por registrarte en Comfaca Credito</title>
                </head>
                <body>
                    <h1>Gracias por registrarte en Comfaca Credito</h1>
                    <p>Gracias por registrarte en Comfaca Credito. Ahora puedes iniciar sesión con tu correo electrónico y contraseña.</p>
                    <p><strong>Fecha de envío:</strong> ' . now()->format('Y-m-d H:i:s') . '</p>
                    <p><strong>Entorno:</strong> ' . config('app.env') . '</p>
                    <p><strong>PIN:</strong> ' . $pin . '</p>
                    <hr>
                    <p><small>Este correo fue enviado automáticamente desde el sistema de Comfaca Credito.</small></p>
                </body>
                </html>
            ';

            $altBody = 'Gracias por registrarte en Comfaca Credito - Este es un correo de prueba para verificar que el registro se realizo correctamente.';

            try {
                // Enviar el correo
                $sender->send($authResult['user']['email'], $body, null, $altBody);
                Log::info('Correo enviado exitosamente');
            } catch (\Exception $e) {
                Log::error('Error al enviar el correo: ' . $e->getMessage());
            }

            return response()->json([
                'access_token' => $token,
                'token_type' => $authResult['token_type'],
                'expires_in' => $authResult['expires_in'],
                'user' => $userResponse
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error en registro: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error al registrar usuario'
            ], 400);
        }
    }

    /**
     * Verify JWT token and return user info.
     */
    public function verify(Request $request): JsonResponse
    {
        try {
            // Rate limiting for token verification
            $this->authService->checkRateLimit('auth.verify', 100, 60);

            // The middleware should already verify the token
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'error' => 'Token inválido'
                ], 401);
            }

            return response()->json([
                'valid' => true,
                'user' => [
                    'username' => $user->username,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'roles' => $user->roles,
                    'permissions' => $user->permissions ?? [],
                    'tipo_documento' => $user->tipo_documento,
                    'numero_documento' => $user->numero_documento,
                    'is_active' => $user->is_active,
                    'disabled' => $user->disabled
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error verificando token: ' . $e->getMessage());

            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        }
    }

    /**
     * Login for advisers with additional data.
     */
    public function adviserLogin(LoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Rate limiting
            $this->authService->checkRateLimit('auth.adviser', 30, 60);

            $username = trim($validated['username']);
            $password = $validated['password'];

            // Normalize username
            $normalizedUsername = $this->authService->normalizeUsername($username);

            if (!$normalizedUsername || !$password) {
                return response()->json([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }

            // Rate limiting by user
            $this->authService->checkRateLimit("auth.adviser.user:{$normalizedUsername}", 10, 60);

            // Authenticate using AuthenticationService
            $authResult = $this->authService->login($normalizedUsername, $password);
            $user = $this->authService->authenticate($normalizedUsername, $password);

            // Get additional data for advisers
            $usuarioSisuData = null;
            $trabajadorData = null;
            $puntosAsesores = null;

            if ($user && $user->numero_documento) {
                try {
                    $usuarioSisuData = $this->trabajadorService->obtenerDatosUsuarioSisu($user);
                    $trabajadorData = $this->trabajadorService->obtenerDatosTrabajador($user->numero_documento);
                    $puntosAsesores = $this->trabajadorService->obtenerPuntosAsesoresPorUsuario($user);
                } catch (\Exception $e) {
                    Log::warning("No se pudieron obtener datos adicionales del asesor: " . $e->getMessage());
                }
            }

            // Add adviser-specific data to user response
            if ($usuarioSisuData) {
                $authResult['user']['asesor'] = $usuarioSisuData;
            }

            if ($trabajadorData) {
                $authResult['user']['trabajador'] = $trabajadorData;
            }

            if ($puntosAsesores) {
                $authResult['user']['puntos_asesores'] = $puntosAsesores;
            }

            return response()->json($authResult);
        } catch (\Exception $e) {
            Log::error('Error en login de asesor: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error de autenticación'
            ], 401);
        }
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Revoke the token
            Auth::logout();

            return response()->json([
                'message' => 'Sesión cerrada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error en logout: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error al cerrar sesión'
            ], 500);
        }
    }

    /**
     * Refresh token.
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'error' => 'No autenticado'
                ], 401);
            }

            // Get current token from request and refresh it
            $currentToken = $request->bearerToken();
            if (!$currentToken) {
                return response()->json([
                    'error' => 'Token no proporcionado'
                ], 401);
            }

            $tokenResult = $this->authService->refreshToken($currentToken);

            return response()->json([
                'access_token' => $tokenResult['access_token'],
                'token_type' => $tokenResult['token_type'],
                'expires_in' => $tokenResult['expires_in'],
                'user' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Error refrescando token: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error al refrescar token'
            ], 401);
        }
    }

    /**
     * Get current authenticated user info.
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'error' => 'No autenticado'
                ], 401);
            }

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'phone' => $user->phone,
                    'tipo_documento' => $user->tipo_documento,
                    'numero_documento' => $user->numero_documento,
                    'roles' => $user->roles,
                    'permissions' => $user->permissions ?? [],
                    'is_active' => $user->is_active,
                    'disabled' => $user->disabled,
                    'created_at' => $user->created_at->toISOString(),
                    'updated_at' => $user->updated_at->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo datos del usuario: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error al obtener datos del usuario'
            ], 500);
        }
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'error' => 'No autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8',
                'new_password_confirmation' => 'required|string|same:new_password'
            ]);

            // Verify current password
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'error' => 'Contraseña actual incorrecta'
                ], 400);
            }

            // Update password using repository
            $this->userService->update($user->id, [
                'password' => $validated['new_password']
            ]);

            return response()->json([
                'message' => 'Contraseña actualizada exitosamente'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error cambiando contraseña: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error al cambiar contraseña'
            ], 500);
        }
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'error' => 'No autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'full_name' => 'sometimes|string',
                'phone' => 'sometimes|string',
                'email' => 'sometimes|email|unique:users,email',
                'tipo_documento' => 'sometimes|string',
                'numero_documento' => 'sometimes|string|unique:users,numero_documento'
            ]);

            $updatedUser = $this->userService->update($user->id, $validated);

            return response()->json([
                'message' => 'Perfil actualizado exitosamente',
                'user' => $updatedUser
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error actualizando perfil: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error al actualizar perfil'
            ], 500);
        }
    }
}
