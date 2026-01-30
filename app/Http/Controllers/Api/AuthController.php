<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthenticationService;
use App\Services\UserService;
use App\Services\TrabajadorService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected UserService $userService;
    protected TrabajadorService $trabajadorService;

    public function __construct(
        UserService $userService,
        TrabajadorService $trabajadorService
    ) {
        $this->userService = $userService;
        $this->trabajadorService = $trabajadorService;
    }

    /**
     * Login user and return token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Rate limiting
            $this->checkRateLimit('auth.login', 30, 60);

            $username = trim($validated['username']);
            $password = $validated['password'];

            // Normalize username
            $normalizedUsername = $this->normalizeUsername($username);

            if (!$normalizedUsername || !$password) {
                return response()->json([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }

            // Rate limiting by user
            $this->checkRateLimit("auth.login.user:{$normalizedUsername}", 10, 60);

            // Authenticate user using Laravel's built-in authentication
            if (!Auth::attempt(['username' => $normalizedUsername, 'password' => $password])) {
                return response()->json([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }

            $user = Auth::user();

            // Get additional data if user has documento
            $trabajadorData = null;
            if ($user && $user->numero_documento) {
                try {
                    $trabajadorData = $this->trabajadorService->obtenerDatosTrabajador($user->numero_documento);
                } catch (\Exception $e) {
                    Log::warning("No se pudieron obtener datos del trabajador: " . $e->getMessage());
                }
            }

            // Create token using Sanctum
            $token = $user->createToken('api');

            // Prepare user response with additional data
            $userResponse = [
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
            ];

            // Add trabajador data if available
            if ($trabajadorData) {
                $userResponse['trabajador'] = $trabajadorData;
            }

            return response()->json([
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', 5184000), // 60 days default
                'user' => $userResponse
            ]);
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
            $this->checkRateLimit('auth.register', 10, 60);

            // Extract data
            $tipoDocumento = $validated['tipo_documento'];
            $numeroDocumento = $validated['numero_documento'];
            $nombres = $validated['nombres'];
            $apellidos = $validated['apellidos'];
            $telefono = $validated['telefono'];
            $email = $validated['email'];
            $password = $validated['password'];

            // Generate username
            $username = $this->generateUsername($nombres, $apellidos);
            if (!$this->isValidUsername($username)) {
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

            // Create user data
            $userData = [
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
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

            // Create user
            $createdUser = $this->userService->create($userData);

            // Generate token for newly created user using Sanctum
            $token = $createdUser->createToken('api');

            // Prepare user response
            $userResponse = [
                'id' => $createdUser->id,
                'username' => $createdUser->username,
                'email' => $createdUser->email,
                'full_name' => $createdUser->full_name,
                'phone' => $createdUser->phone,
                'tipo_documento' => $createdUser->tipo_documento,
                'numero_documento' => $createdUser->numero_documento,
                'roles' => $createdUser->roles,
                'permissions' => $createdUser->permissions ?? [],
                'is_active' => $createdUser->is_active,
                'disabled' => $createdUser->disabled,
                'created_at' => $createdUser->created_at->toISOString(),
                'updated_at' => $createdUser->updated_at->toISOString()
            ];

            return response()->json([
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', 5184000),
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
            $this->checkRateLimit('auth.verify', 100, 60);

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
            $this->checkRateLimit('auth.adviser', 30, 60);

            $username = trim($validated['username']);
            $password = $validated['password'];

            // Normalize username
            $normalizedUsername = $this->normalizeUsername($username);

            if (!$normalizedUsername || !$password) {
                return response()->json([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }

            // Rate limiting by user
            $this->checkRateLimit("auth.adviser.user:{$normalizedUsername}", 10, 60);

            // Authenticate user using Laravel's built-in authentication
            if (!Auth::attempt(['username' => $normalizedUsername, 'password' => $password])) {
                return response()->json([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }

            $user = Auth::user();

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

            // Create token using Sanctum
            $token = $user->createToken('api');

            // Prepare user response with additional data
            $userResponse = [
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
            ];

            // Add adviser-specific data to user response
            if ($usuarioSisuData) {
                $userResponse['asesor'] = $usuarioSisuData;
            }

            if ($trabajadorData) {
                $userResponse['trabajador'] = $trabajadorData;
            }

            if ($puntosAsesores) {
                $userResponse['puntos_asesores'] = $puntosAsesores;
            }

            return response()->json([
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', 5184000),
                'user' => $userResponse
            ]);
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

            // Create new token
            $token = $user->createToken('api');

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.jwt_ttl', 86400),
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

            // Update password
            $user->update([
                'password' => Hash::make($validated['new_password']),
                'updated_at' => now()
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

            $user->update($validated);

            return response()->json([
                'message' => 'Perfil actualizado exitosamente',
                'user' => $user
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

    // Private helper methods

    /**
     * Check rate limit for given key.
     */
    private function checkRateLimit(string $key, int $maxAttempts, int $seconds): void
    {
        $key = "auth:{$key}";

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new \Exception('Demasiados intentos, intenta más tarde');
        }

        RateLimiter::hit($key, $seconds);
    }

    /**
     * Normalize username.
     */
    private function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    /**
     * Generate username from names.
     */
    private function generateUsername(string $nombres, string $apellidos): string
    {
        $name1 = strtolower(Str::slug($nombres));
        $name2 = strtolower(Str::slug($apellidos));

        // Remove special characters and ensure it starts with letter
        $username = preg_replace('/[^a-z0-9]/', '', $name1 . $name2);

        // Ensure it starts with a letter
        if (!ctype_alpha($username[0])) {
            $username = 'u' . $username;
        }

        // Limit length
        return substr($username, 0, 20);
    }

    /**
     * Validate username format.
     */
    private function isValidUsername(string $username): bool
    {
        return preg_match('/^[a-z0-9]{3,20}$/', $username);
    }
}
