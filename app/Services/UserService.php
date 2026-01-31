<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Validators\UserValidators;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class UserService extends BaseService
{
    protected UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        parent::__construct($userRepository);
        $this->userRepository = $userRepository;
    }

    /**
     * Create a new user.
     */
    public function create(array $data): User
    {
        // Validate data
        $validator = UserValidators::validateRegister($data);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }

        $validated = $validator->validated();

        // Generate username if not provided
        if (!isset($validated['username']) || empty($validated['username'])) {
            $validated['username'] = UserValidators::generateUsername(
                $validated['nombres'],
                $validated['apellidos']
            );
        }

        // Check if username already exists
        if (User::findByUsername($validated['username'])) {
            throw new ValidationException('El nombre de usuario ya está en uso');
        }

        // Check if email already exists
        if (User::findByEmail($validated['email'])) {
            throw new ValidationException('El correo electrónico ya está en uso');
        }

        // Transform password to password_hash
        if (isset($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
            unset($validated['password']);
            unset($validated['password_confirmation']);
        }

        try {
            // Create user with hashed password
            $user = User::create($validated);

            $this->log('User created successfully', [
                'user_id' => $user->id,
                'username' => $user->username
            ]);

            return $user;
        } catch (\Exception $e) {
            $this->logError('Error creating user', ['error' => $e->getMessage()]);
            throw new \Exception('Error al crear usuario: ' . $e->getMessage());
        }
    }

    /**
     * Get user by ID.
     */
    public function getById(string $id): ?User
    {
        try {
            return User::find($id);
        } catch (\Exception $e) {
            $this->logError('Error getting user by ID', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get user by username.
     */
    public function getByUsername(string $username): ?User
    {
        try {
            return User::findByUsername($username);
        } catch (\Exception $e) {
            $this->logError('Error getting user by username', ['username' => $username, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get user by email.
     */
    public function getByEmail(string $email): ?User
    {
        try {
            return User::findByEmail($email);
        } catch (\Exception $e) {
            $this->logError('Error getting user by email', ['email' => $email, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get user by username or email.
     */
    public function getByUsernameOrEmail(string $identifier): ?User
    {
        try {
            return User::where(function ($query) use ($identifier) {
                $query->where('username', strtolower($identifier))
                    ->orWhere('email', $identifier);
            })->first();
        } catch (\Exception $e) {
            $this->logError('Error getting user by username or email', ['identifier' => $identifier, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Update user.
     */
    public function update(string $id, array $data): User
    {
        // Validate data
        $validator = UserValidators::validateUpdate($data, $id);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }

        $validated = $validator->validated();

        // Ensure user exists
        $user = $this->ensureExists($id, 'Usuario');

        // Check if email is being updated and already exists
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            if (User::where('email', $validated['email'])->where('id', '!=', $id)->exists()) {
                throw new ValidationException('El correo electrónico ya está en uso');
            }
        }

        try {
            $user->update($validated);
            $updated = $user->fresh();

            if (!$updated) {
                throw new \Exception('No se pudo actualizar el usuario');
            }

            $this->log('User updated successfully', [
                'user_id' => $id,
                'changes' => array_keys($validated)
            ]);

            return $updated;
        } catch (\Exception $e) {
            $this->logError('Error updating user', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \Exception('Error al actualizar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Delete user (soft delete).
     */
    public function delete(string $id): bool
    {
        // Ensure user exists
        $user = $this->ensureExists($id, 'Usuario');

        try {
            $result = $user->delete();

            if ($result) {
                $this->log('User deleted successfully', ['user_id' => $id]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error deleting user', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \Exception('Error al eliminar usuario: ' . $e->getMessage());
        }
    }

    /**
     * List users with pagination and filters.
     */
    public function list(int $skip = 0, int $limit = 50, ?bool $disabled = null, ?array $roles = null): array
    {
        try {
            $query = User::query();

            if ($disabled !== null) {
                $query->where('disabled', $disabled);
            }

            if ($roles !== null) {
                foreach ($roles as $role) {
                    $query->whereJsonContains('roles', $role);
                }
            }

            $users = $query->orderBy('created_at', 'desc')
                ->offset($skip)
                ->limit($limit)
                ->get();

            $total = $query->count();

            return [
                'users' => $users->toArray(),
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'total' => $total,
                    'has_more' => ($skip + $limit) < $total
                ]
            ];
        } catch (\Exception $e) {
            $this->logError('Error listing users', ['error' => $e->getMessage()]);
            throw new \Exception('Error al listar usuarios: ' . $e->getMessage());
        }
    }

    /**
     * Authenticate user.
     */
    public function authenticate(string $identifier, string $password): ?User
    {
        try {
            $user = $this->getByUsernameOrEmail($identifier);

            if ($user && !$user->disabled && $user->is_active && Hash::check($password, $user->password_hash)) {
                // Update last login
                $user->update(['last_login' => now()]);

                $this->log('User authenticated successfully', [
                    'user_id' => $user->id,
                    'identifier' => $identifier
                ]);

                return $user;
            }

            return null;
        } catch (\Exception $e) {
            $this->logError('Error authenticating user', ['identifier' => $identifier, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Change user password.
     */
    public function changePassword(string $id, string $currentPassword, string $newPassword): bool
    {
        // Get user
        $user = $this->getById($id);

        if (!$user) {
            throw new ValidationException('Usuario no encontrado');
        }

        // Verify current password
        if (!Hash::check($currentPassword, $user->password)) {
            throw new ValidationException('Contraseña actual incorrecta');
        }

        // Validate new password
        if (!UserValidators::validatePassword($newPassword)) {
            throw new ValidationException('La nueva contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número');
        }

        try {
            $result = $user->update(['password' => Hash::make($newPassword)]);

            if ($result) {
                $this->log('Password changed successfully', ['user_id' => $id]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error changing password', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \Exception('Error al cambiar contraseña: ' . $e->getMessage());
        }
    }

    /**
     * Enable user.
     */
    public function enableUser(string $id): bool
    {
        // Ensure user exists
        $user = $this->ensureExists($id, 'Usuario');

        try {
            $result = $user->update(['disabled' => false]);

            if ($result) {
                $this->log('User enabled successfully', ['user_id' => $id]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error enabling user', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \Exception('Error al habilitar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Disable user.
     */
    public function disableUser(string $id): bool
    {
        // Ensure user exists
        $user = $this->ensureExists($id, 'Usuario');

        try {
            $result = $user->update(['disabled' => true]);

            if ($result) {
                $this->log('User disabled successfully', ['user_id' => $id]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error disabling user', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \Exception('Error al deshabilitar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Get user statistics.
     */
    public function getStatistics(): array
    {
        try {
            $total = User::count();
            $active = User::where('disabled', false)->count();
            $disabled = User::where('disabled', true)->count();

            // Get role statistics using JSON operations
            $byRole = [];

            // Get all users with roles
            $usersWithRoles = User::whereNotNull('roles')->get();

            foreach ($usersWithRoles as $user) {
                $userRoles = $user->roles ?? [];
                if (is_array($userRoles)) {
                    foreach ($userRoles as $role) {
                        $byRole[$role] = ($byRole[$role] ?? 0) + 1;
                    }
                }
            }

            // Sort by count descending
            arsort($byRole);

            return [
                'total' => $total,
                'active' => $active,
                'disabled' => $disabled,
                'by_role' => $byRole
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting user statistics', ['error' => $e->getMessage()]);
            return [
                'total' => 0,
                'active' => 0,
                'disabled' => 0,
                'by_role' => []
            ];
        }
    }

    /**
     * Search users.
     */
    public function search(string $term, ?array $roles = null): array
    {
        try {
            $query = User::where(function ($q) use ($term) {
                $q->where('username', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('full_name', 'like', "%{$term}%")
                    ->orWhere('nombres', 'like', "%{$term}%")
                    ->orWhere('apellidos', 'like', "%{$term}%");
            });

            if ($roles !== null) {
                foreach ($roles as $role) {
                    $query->whereJsonContains('roles', $role);
                }
            }

            $users = $query->where('disabled', false)->get();

            return [
                'users' => $users->toArray(),
                'count' => $users->count()
            ];
        } catch (\Exception $e) {
            $this->logError('Error searching users', ['term' => $term, 'error' => $e->getMessage()]);
            return [
                'users' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Get users by role.
     */
    public function getByRole(string $role): array
    {
        try {
            $users = User::whereJsonContains('roles', $role)->get();

            return [
                'users' => $users->toArray(),
                'count' => $users->count(),
                'role' => $role
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting users by role', ['role' => $role, 'error' => $e->getMessage()]);
            return [
                'users' => [],
                'count' => 0,
                'role' => $role
            ];
        }
    }

    /**
     * Get active users.
     */
    public function getActive(): array
    {
        try {
            $users = User::where('disabled', false)
                ->where('is_active', true)
                ->get();

            return [
                'users' => $users->toArray(),
                'count' => $users->count()
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting active users', ['error' => $e->getMessage()]);
            return [
                'users' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Get administrators.
     */
    public function getAdministrators(): array
    {
        try {
            $users = User::whereJsonContains('roles', 'administrator')
                ->where('disabled', false)
                ->get();

            return [
                'users' => $users->toArray(),
                'count' => $users->count()
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting administrators', ['error' => $e->getMessage()]);
            return [
                'users' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Get advisers.
     */
    public function getAdvisers(): array
    {
        try {
            $users = User::whereJsonContains('roles', 'adviser')
                ->where('disabled', false)
                ->get();

            return [
                'users' => $users->toArray(),
                'count' => $users->count()
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting advisers', ['error' => $e->getMessage()]);
            return [
                'users' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Transform user for API response.
     */
    public function transformForApi($user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'phone' => $user->phone,
            'tipo_documento' => $user->tipo_documento,
            'numero_documento' => $user->numero_documento,
            'nombres' => $user->nombres,
            'apellidos' => $user->apellidos,
            'roles' => $user->roles,
            'permissions' => $user->permissions,
            'disabled' => $user->disabled,
            'is_active' => $user->is_active,
            'is_administrator' => $user->is_administrator,
            'is_adviser' => $user->is_adviser,
            'is_regular_user' => $user->is_regular_user,
            'last_login' => $user->last_login?->toISOString(),
            'created_at' => $user->created_at->toISOString(),
            'updated_at' => $user->updated_at->toISOString()
        ];
    }

    /**
     * Transform collection for API response.
     */
    public function transformCollectionForApi($users): array
    {
        return $users->map(fn($user) => $this->transformForApi($user))->toArray();
    }
}
