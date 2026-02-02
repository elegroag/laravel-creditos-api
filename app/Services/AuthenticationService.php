<?php

namespace App\Services;

use App\Models\User;
use App\Services\UserService;
use App\Validators\UserValidators;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;

class AuthenticationService extends EloquentService
{
    protected UserService $userService;

    private string $jwtSecret;
    private string $jwtIssuer;
    private int $jwtTtlSeconds;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->jwtSecret = config('app.jwt_secret', 'default-secret-key');
        $this->jwtIssuer = config('app.jwt_issuer', 'comfaca-credito');
        $this->jwtTtlSeconds = config('app.jwt_ttl_seconds', 86400); // 24 hours
    }

    /**
     * Authenticate user with credentials.
     */
    public function authenticate(string $identifier, string $password): ?User
    {
        // Try to find user by username or email
        $user = User::where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (!$user) return null;

        if ($user->disabled || !$user->is_active) return null;

        if (!Hash::check($password, $user->password_hash)) return null;

        $user->update(['last_login_at' => now()]);

        return $user;
    }

    /**
     * Create new user with validation.
     */
    public function createUser(array $userData): User
    {
        // Basic validation
        if (empty($userData['username']) || empty($userData['password'])) {
            throw new ValidationException('El nombre de usuario y la contraseña son requeridos');
        }

        if (strlen($userData['username']) < 3) {
            throw new ValidationException('El nombre de usuario debe tener al menos 3 caracteres');
        }

        if (strlen($userData['password']) < 6) {
            throw new ValidationException('La contraseña debe tener al menos 6 caracteres');
        }

        // Check if username already exists
        if (User::where('username', $userData['username'])->exists()) {
            throw new ValidationException('El nombre de usuario ya está en uso');
        }

        // Check if email already exists
        if (isset($userData['email']) && User::where('email', $userData['email'])->exists()) {
            throw new ValidationException('El correo electrónico ya está en uso');
        }

        // Create user
        return User::create([
            'username' => $userData['username'],
            'email' => $userData['email'] ?? null,
            'password_hash' => Hash::make($userData['password']),
            'nombres' => $userData['nombres'] ?? '',
            'apellidos' => $userData['apellidos'] ?? '',
            'full_name' => $userData['full_name'] ?? null,
            'phone' => $userData['phone'] ?? null,
            'tipo_documento' => $userData['tipo_documento'] ?? null,
            'numero_documento' => $userData['numero_documento'] ?? null,
            'roles' => $userData['roles'] ?? ['user_trabajador'],
            'is_active' => true,
            'disabled' => false
        ]);
    }

    /**
     * Get user by ID.
     */
    public function getUserById(int $userId): ?User
    {
        return User::find($userId);
    }

    /**
     * Transform user for API response.
     */
    public function transformUserForApi(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'phone' => $user->phone,
            'roles' => $user->roles,
            'permissions' => $this->getUserPermissions($user->roles),
            'disabled' => $user->disabled,
            'is_active' => $user->is_active,
            'tipo_documento' => $user->tipo_documento,
            'numero_documento' => $user->numero_documento,
            'nombres' => $user->nombres,
            'apellidos' => $user->apellidos,
            'last_login' => $user->last_login?->toISOString(),
            'created_at' => $user->created_at->toISOString(),
            'updated_at' => $user->updated_at->toISOString(),
            'is_administrator' => $user->is_administrator,
            'is_adviser' => $user->is_adviser,
            'is_regular_user' => $user->is_regular_user,
        ];
    }

    /**
     * Login user and return token.
     */
    public function login(string $identifier, string $password): array
    {
        // Authenticate user
        $user = $this->authenticate($identifier, $password);
        if (!$user) {
            throw new ValidationException('No es posible la autenticación');
        }

        // Generate token
        $token = $this->generateToken($user);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtTtlSeconds,
            'user' => $this->transformUserForApi($user)
        ];
    }

    /**
     * Register new user and return token.
     */
    public function register(array $userData): array
    {
        // Create user
        $user = $this->createUser($userData);

        // Generate token
        $token = $this->generateToken($user);

        $this->log('User registered successfully', [
            'user_id' => $user->id,
            'username' => $user->username
        ]);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtTtlSeconds,
            'user' => $this->transformUserForApi($user)
        ];
    }

    /**
     * Verify JWT token and return payload.
     */
    public function verifyToken(string $token): ?array
    {
        try {
            $payload = $this->decodeToken($token);

            // Get user
            $user = $this->getUserById($payload['sub']);

            if (!$user || $user->disabled || !$user->is_active) {
                return null;
            }

            return [
                'valid' => true,
                'user' => $this->transformUserForApi($user),
                'expires_at' => Carbon::createFromTimestamp($payload['exp'])->toISOString(),
                'user_id' => $payload['sub']
            ];
        } catch (\Exception $e) {
            Log::error('Error verificando token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current user from token.
     */
    public function getCurrentUser(string $token): array
    {
        try {
            $payload = $this->decodeToken($token);

            $user = $this->getUserById($payload['sub']);

            if (!$user || $user->disabled || !$user->is_active) {
                throw new ValidationException('Usuario no válido o deshabilitado');
            }

            return $this->transformUserForApi($user);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de usuario actual');
            throw new ValidationException('Error obteniendo usuario actual: ' . $e->getMessage());
        }
    }

    /**
     * Refresh JWT token.
     */
    public function refreshToken(string $token): array
    {
        try {
            // Verify current token
            $payload = $this->decodeToken($token);

            // Get user
            $user = $this->getUserById($payload['sub']);

            if (!$user || $user->disabled || !$user->is_active) {
                throw new ValidationException('Token inválido o usuario deshabilitado');
            }

            // Generate new token
            $newToken = $this->generateToken($user);

            return [
                'access_token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtTtlSeconds
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'refresco de token');
            throw new ValidationException('Error refrescando token: ' . $e->getMessage());
        }
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(string $token): bool
    {
        try {
            $payload = $this->decodeToken($token);

            // In a real implementation, you might want to blacklist the token
            // For now, we'll just log the logout
            $this->log('User logged out', [
                'user_id' => $payload['sub'],
                'username' => $payload['username'] ?? 'unknown'
            ]);

            return true;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'cierre de sesión');
            return false;
        }
    }

    /**
     * Check if user has required role.
     */
    public function checkPermission(array $userRoles, string $requiredRole): bool
    {
        if (empty($userRoles) || !is_array($userRoles)) {
            return false;
        }

        // Admin has all permissions
        if (in_array('administrator', array_map('strtolower', $userRoles))) {
            return true;
        }

        // Check specific role
        return in_array($requiredRole, array_map('trim', $userRoles));
    }

    /**
     * Require authentication.
     */
    public function requireAuth(string $token): array
    {
        return $this->getCurrentUser($token);
    }

    /**
     * Require specific role.
     */
    public function requireRole(string $token, string $requiredRole): array
    {
        $user = $this->requireAuth($token);

        if (!$this->checkPermission($user['roles'], $requiredRole)) {
            throw new ValidationException("Se requiere el rol '{$requiredRole}'");
        }

        return $user;
    }

    /**
     * Generate JWT token for user.
     */
    private function generateToken(User $user): string
    {
        $now = Carbon::now();
        $exp = $now->addSeconds($this->jwtTtlSeconds);

        $payload = [
            'sub' => $user->id,
            'iss' => $this->jwtIssuer,
            'iat' => $now->timestamp,
            'exp' => $exp->timestamp,
            'username' => $user->username,
            'data' => [
                'roles' => $user->roles,
                'tipo_documento' => $user->tipo_documento,
                'numero_documento' => $user->numero_documento,
                'user_id' => $user->id
            ]
        ];

        // For simplicity, using basic token generation
        // In production, you might want to use a proper JWT library
        return base64_encode(json_encode($payload));
    }

    /**
     * Decode and verify JWT token.
     */
    private function decodeToken(string $token): array
    {
        try {
            // Decode token
            $payload = json_decode(base64_decode($token), true);

            if (!$payload) {
                throw new \Exception('Token inválido');
            }

            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < Carbon::now()->timestamp) {
                throw new \Exception('Token expirado');
            }

            // Check issuer
            if (isset($payload['iss']) && $payload['iss'] !== $this->jwtIssuer) {
                throw new \Exception('Token inválido');
            }

            return $payload;
        } catch (\Exception $e) {
            throw new \Exception('Token inválido: ' . $e->getMessage());
        }
    }

    /**
     * Get user permissions based on roles.
     */
    public function getUserPermissions(array $userRoles): array
    {
        $permissions = [];

        // Handle null or empty roles
        if (empty($userRoles)) {
            return $permissions;
        }

        // Define role permissions
        $rolePermissions = [
            'administrator' => [
                'users.create',
                'users.edit',
                'users.delete',
                'users.view',
                'applications.create',
                'applications.edit',
                'applications.delete',
                'applications.view_all',
                'roles.manage',
                'system.admin'
            ],
            'adviser' => [
                'applications.create',
                'applications.edit',
                'applications.delete',
                'applications.view_all',
                'applications.approve',
                'applications.reject'
            ],
            'user_empresa' => [
                'applications.create',
                'applications.edit',
                'applications.delete',
                'applications.view_own'
            ],
            'user_trabajador' => [
                'applications.create',
                'applications.edit',
                'applications.delete',
                'applications.view_own'
            ]
        ];

        // Add permissions for each role
        foreach ($userRoles as $role) {
            if (isset($rolePermissions[$role])) {
                $permissions = array_merge($permissions, $rolePermissions[$role]);
            }
        }

        return array_unique($permissions);
    }

    /**
     * Validate token format.
     */
    private function validateTokenFormat(string $token): bool
    {
        // Basic validation for token format
        return !empty($token) && strlen($token) > 10;
    }

    /**
     * Get token expiration time.
     */
    public function getTokenExpiration(string $token): ?Carbon
    {
        try {
            $payload = $this->decodeToken($token);

            if (isset($payload['exp'])) {
                return Carbon::createFromTimestamp($payload['exp']);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if token is expired.
     */
    public function isTokenExpired(string $token): bool
    {
        $expiration = $this->getTokenExpiration($token);

        if (!$expiration) {
            return true;
        }

        return $expiration->isPast();
    }

    /**
     * Get token remaining time in seconds.
     */
    public function getTokenRemainingTime(string $token): int
    {
        $expiration = $this->getTokenExpiration($token);

        if (!$expiration) {
            return 0;
        }

        return max(0, $expiration->diffInSeconds(Carbon::now()));
    }

    /**
     * Generate username from names.
     */
    public function generateUsername(string $nombres, string $apellidos): string
    {
        $name1 = strtolower(Str::slug($nombres));
        $name2 = strtolower(Str::slug($apellidos));

        // Remove special characters and ensure it starts with letter
        $name1 = preg_replace('/[^a-z0-9]/', '', $name1);
        $name2 = preg_replace('/[^a-z0-9]/', '', $name2);
        $username = substr($name1, 0, 3) . '_' . substr($name2, 0, 3);

        // Limit length
        return substr($username, 0, 20);
    }

    /**
     * Normalize username.
     */
    public function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    /**
     * Check rate limit for given key.
     */
    public function checkRateLimit(string $key, int $maxAttempts, int $seconds): void
    {
        $key = "auth:{$key}";

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new \Exception('Demasiados intentos, intenta más tarde');
        }

        RateLimiter::hit($key, $seconds);
    }


    /**
     * Validate username format.
     */
    public function isValidUsername(string $username): bool
    {
        return preg_match('/^[a-z0-9_]{3,20}$/', $username);
    }
}
