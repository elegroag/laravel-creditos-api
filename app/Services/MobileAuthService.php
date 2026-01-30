<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MobileAuthService
{
    /**
     * Login de usuario y generación de token
     */
    public function login(string $username, string $password): array
    {
        try {
            Log::info('Intentando login de usuario', ['username' => $username]);

            // Buscar usuario
            $user = User::where('username', $username)->first();

            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'Usuario no encontrado',
                    'details' => []
                ];
            }

            // Verificar contraseña
            if (!Hash::check($password, $user->password)) {
                return [
                    'success' => false,
                    'error' => 'Contraseña incorrecta',
                    'details' => []
                ];
            }

            // Generar token JWT
            $tokenPayload = [
                'iss' => config('app.jwt.issuer', 'comfaca-credito'),
                'sub' => $user->username,
                'iat' => Carbon::now()->timestamp('UTC'),
                'exp' => Carbon::now()->addDays(7)->timestamp('UTC'),
                'jti' => Str::uuid()->toString()
            ];

            $token = JWTAuth::encode($tokenPayload);

            // Actualizar último login
            $userModel = User::find($user->id);
            if ($userModel) {
                $userModel->update([
                    'last_login_at' => Carbon::now(),
                    'remember_token' => $token,
                    'updated_at' => Carbon::now()
                ]);
            }

            Log::info('Login exitoso', ['username' => $username]);

            return [
                'success' => true,
                'access_token' => $token,
                'token_type' => 'bearer',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'nombres' => $user->nombres ?? '',
                    'apellidos' => $user->apellidos ?? '',
                    'roles' => $user->roles ?? []
                ],
                'expires_in' => '7 días'
            ];
        } catch (\Exception $e) {
            Log::error('Error en login', [
                'username' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno al iniciar sesión',
                'details' => [
                    'internal_error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Verifica un token JWT
     */
    public function verifyToken(string $token): array
    {
        try {
            $decoded = JWTAuth::parseToken($token);

            return [
                'sub' => $decoded->get('sub'),
                'type' => $decoded->get('type'),
                'iat' => $decoded->get('iat'),
                'exp' => $decoded->get('exp'),
                'jti' => $decoded->get('jti')
            ];
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            throw new \Exception('Token inválido: ' . $e->getMessage());
        }
    }

    /**
     * Registra un nuevo usuario
     */
    public function register(array $userData): array
    {
        try {
            Log::info('Registrando nuevo usuario', ['username' => $userData['username']]);

            // Validar que el usuario no exista
            $existingUser = User::where('username', $userData['username'])->first();

            if ($existingUser) {
                return [
                    'success' => false,
                    'error' => 'El usuario ya existe',
                    'details' => []
                ];
            }

            // Crear usuario
            $user = User::create([
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'nombres' => $userData['nombres'] ?? '',
                'apellidos' => $userData['apellidos'] ?? '',
                'roles' => $userData['roles'] ?? ['user_trabajador'],
                'disabled' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            Log::info('Usuario registrado exitosamente', ['username' => $userData['username']]);

            return [
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
                'data' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error en registro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno al registrar usuario',
                'details' => [
                    'internal_error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Obtiene información del usuario autenticado
     */
    public function getAuthenticatedUser(): ?array
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return null;
            }

            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'nombres' => $user->nombres ?? '',
                'apellidos' => $user->apellidos ?? '',
                'roles' => $user->roles ?? [],
                'disabled' => $user->disabled ?? false,
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
                'last_login_at' => $user->last_login_at?->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('Error al obtener usuario autenticado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logout(): array
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'No hay sesión activa',
                    'details' => []
                ];
            }

            $userModel = User::find($user->id);
            if (!$userModel) {
                return [
                    'success' => false,
                    'error' => 'No se pudo encontrar el usuario',
                    'details' => []
                ];
            }

            // Invalidar token actual
            JWTAuth::invalidate();

            // Actualizar último logout
            $userModel = User::find($user->id);
            if ($userModel) {
                $userModel->update([
                    'remember_token' => null,
                    'updated_at' => Carbon::now()
                ]);
            }

            Log::info('Sesión cerrada exitosamente', ['username' => $user->username]);

            return [
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ];
        } catch (\Exception $e) {
            Log::error('Error al cerrar sesión', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno al cerrar sesión',
                'details' => [
                    'internal_error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Refrescar el token del usuario
     */
    public function refreshToken(): array
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'No hay sesión activa',
                    'details' => []
                ];
            }

            // Invalidar token actual
            JWTAuth::invalidate();

            // Generar nuevo token
            $tokenPayload = [
                'iss' => config('app.jwt.issuer', 'comfaca-credito'),
                'sub' => $user->username,
                'iat' => Carbon::now()->timestamp('UTC'),
                'exp' => Carbon::now()->addDays(7)->timestamp('UTC'),
                'jti' => Str::uuid()->toString()
            ];

            $newToken = JWTAuth::encode($tokenPayload);

            // Actualizar token - obtener el modelo User
            $userModel = User::find($user->id);
            if ($userModel) {
                $userModel->update([
                    'remember_token' => $newToken,
                    'updated_at' => Carbon::now()
                ]);
            }

            Log::info('Token refrescado exitosamente', ['username' => $user->username]);

            return [
                'success' => true,
                'message' => 'Token refrescado exitosamente',
                'data' => [
                    'access_token' => $newToken,
                    'token_type' => 'bearer',
                    'expires_in' => '7 días'
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error al refrescar token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno al refrescar token',
                'details' => [
                    'internal_error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Obtiene estadísticas de autenticación
     */
    public function getAuthStatistics(): array
    {
        try {
            $totalUsers = User::count();
            $activeUsers = User::where('disabled', false)->count();
            $mobileUsers = User::whereHas('roles', ['mobile', 'user_trabajador'])->count();

            $stats = [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'mobile_users' => $mobileUsers,
                'web_users' => $totalUsers - $mobileUsers,
                'login_count_24h' => $this->getLoginCount24h(),
                'new_users_today' => $this->getNewUsersToday(),
                'most_active_roles' => $this->getMostActiveRoles()
            ];

            Log::info('Estadísticas de autenticación obtenidas', $stats);

            return [
                'success' => true,
                'message' => 'Estadísticas obtenidas exitosamente',
                'data' => $stats
            ];
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de autenticación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno al obtener estadísticas',
                'details' => [
                    'internal_error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Obtiene conteo de logins en las últimas 24 horas
     */
    private function getLoginCount24h(): int
    {
        try {
            return User::where('last_login_at', '>=', Carbon::now()->subHours(24))->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Obtiene usuarios nuevos hoy
     */
    private function getNewUsersToday(): int
    {
        try {
            return User::whereDate('created_at', Carbon::today())->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Obtiene los roles más activos
     */
    private function getMostActiveRoles(): array
    {
        try {
            $roles = User::selectRaw('roles')->get()->flatten()->toArray();
            $roleCounts = array_count_values($roles);
            arsort($roleCounts);

            return array_slice($roleCounts, 0, 5); // Top 5 roles más activos
        } catch (\Exception $e) {
            return [];
        }
    }
}
