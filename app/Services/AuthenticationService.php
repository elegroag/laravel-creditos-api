<?php

namespace App\Services;

use App\Models\User;
use App\Services\UserService;
use App\Repositories\UserRepository;
use App\Validators\UserValidators;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class AuthenticationService extends BaseService
{
    protected UserService $userService;
    protected UserRepository $userRepository;

    private string $jwtSecret;
    private string $jwtIssuer;
    private int $jwtTtlSeconds;

    public function __construct(UserService $userService, UserRepository $userRepository)
    {
        $this->userService = $userService;
        $this->userRepository = $userRepository;
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
        $user = $this->userRepository->findByUsernameOrEmail($identifier);

        if (!$user) {
            return null;
        }

        // Check if user is disabled or inactive
        if ($user->disabled || !$user->is_active) {
            return null;
        }

        // Verify password
        if (!Hash::check($password, $user->password)) {
            return null;
        }

        // Update last login
        $this->userRepository->updateLastLogin($user->id);

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
        if ($this->userRepository->usernameExists($userData['username'])) {
            throw new ValidationException('El nombre de usuario ya está en uso');
        }

        // Check if email already exists
        if (isset($userData['email']) && $this->userRepository->emailExists($userData['email'])) {
            throw new ValidationException('El correo electrónico ya está en uso');
        }

        // Create user
        return $this->userRepository->createWithPassword($userData, $userData['password']);
    }

    /**
     * Get user by ID.
     */
    public function getUserById(int $userId): ?User
    {
        return $this->userRepository->findById($userId);
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
            'tipo_documento' => $user->tipo_documento,
            'numero_documento' => $user->numero_documento,
            'nombres' => $user->nombres,
            'apellidos' => $user->apellidos,
            'last_login' => $user->last_login?->toISOString(),
            'created_at' => $user->created_at->toISOString(),
            'updated_at' => $user->updated_at->toISOString(),
            'is_administrator' => $user->is_administrator,
            'is_adviser' => $user->is_adviser,
            'is_regular_user' => $user->is_regular_user
        ];
    }

    /**
     * Login user and return token.
     */
    public function login(string $identifier, string $password): array
    {
        try {
            // Authenticate user
            $user = $this->authenticate($identifier, $password);

            if (!$user) {
                throw new ValidationException('Credenciales inválidas');
            }

            // Generate token
            $token = $this->generateToken($user);

            $this->log('User logged in successfully', [
                'user_id' => $user->id,
                'username' => $user->username
            ]);

            return [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtTtlSeconds,
                'user' => $this->transformUserForApi($user)
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error during login', ['identifier' => $identifier, 'error' => $e->getMessage()]);
            throw new \Exception('Error en el inicio de sesión: ' . $e->getMessage());
        }
    }

    /**
     * Register new user and return token.
     */
    public function register(array $userData): array
    {
        try {
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
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error during registration', ['error' => $e->getMessage()]);
            throw new \Exception('Error en el registro: ' . $e->getMessage());
        }
    }

    /**
     * Verify JWT token and return payload.
     */
    public function verifyToken(string $token): array
    {
        try {
            $payload = $this->decodeToken($token);

            // Get user
            $user = $this->getUserById($payload['sub']);

            if (!$user || $user->disabled || !$user->is_active) {
                throw new ValidationException('Token inválido o usuario deshabilitado');
            }

            return [
                'valid' => true,
                'user' => $this->transformUserForApi($user),
                'expires_at' => Carbon::createFromTimestamp($payload['exp'])->toISOString()
            ];
        } catch (\Exception $e) {
            $this->logError('Error verifying token', ['error' => $e->getMessage()]);
            throw new ValidationException('Token inválido: ' . $e->getMessage());
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
            $this->logError('Error getting current user', ['error' => $e->getMessage()]);
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
            $this->logError('Error refreshing token', ['error' => $e->getMessage()]);
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
            $this->logError('Error during logout', ['error' => $e->getMessage()]);
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
    private function getUserPermissions(array $userRoles): array
    {
        $permissions = [];

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
}
