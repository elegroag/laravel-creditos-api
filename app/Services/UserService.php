<?php

namespace App\Services;

use App\Models\User;
use App\Validators\UserValidators;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;

class UserService extends EloquentService
{
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
            throw new ValidationException('El email ya está en uso');
        }

        // Hash password
        $validated['password'] = Hash::make($validated['password']);

        try {
            return User::create($validated);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'creación de usuario');
            throw new \Exception('Error al crear usuario: ' . $e->getMessage());
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
            $this->handleDatabaseError($e, 'búsqueda de usuario por username');
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
            $this->handleDatabaseError($e, 'búsqueda de usuario por email');
            return null;
        }
    }

    /**
     * Update user.
     */
    public function update(string $id, array $data): ?User
    {
        try {
            $user = User::find($id);
            if ($user) {
                $user->update($data);
                return $user;
            }
            return null;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'actualización de usuario');
            throw new \Exception('Error al actualizar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Delete user.
     */
    public function delete(string $id): bool
    {
        try {
            $result = User::destroy($id);
            return (bool) $result;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'eliminación de usuario');
            return false;
        }
    }

    /**
     * Change user password.
     */
    public function changePassword(string $id, string $newPassword): bool
    {
        // Validate password
        if (strlen($newPassword) < 8) {
            throw new ValidationException('La contraseña debe tener al menos 8 caracteres');
        }

        try {
            $user = User::find($id);
            if (!$user) {
                throw new ValidationException('Usuario no encontrado');
            }

            $result = $user->update(['password' => Hash::make($newPassword)]);
            return $result;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'cambio de contraseña');
            throw new \Exception('Error al cambiar contraseña: ' . $e->getMessage());
        }
    }

    /**
     * Enable user.
     */
    public function enableUser(string $id): bool
    {
        try {
            $user = User::find($id);
            if ($user) {
                return $user->update(['disabled' => false]);
            }
            return false;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'habilitación de usuario');
            return false;
        }
    }

    /**
     * Disable user.
     */
    public function disableUser(string $id): bool
    {
        try {
            $user = User::find($id);
            if ($user) {
                return $user->update(['disabled' => true]);
            }
            return false;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'deshabilitación de usuario');
            return false;
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
            $usersWithRoles = User::whereNotNull('roles')->get();

            foreach ($usersWithRoles as $user) {
                $userRoles = $user->roles ?? [];
                if (is_array($userRoles)) {
                    foreach ($userRoles as $role) {
                        $byRole[$role] = ($byRole[$role] ?? 0) + 1;
                    }
                }
            }

            arsort($byRole);

            return [
                'total' => $total,
                'active' => $active,
                'disabled' => $disabled,
                'by_role' => $byRole
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de estadísticas de usuarios');
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
            $this->handleDatabaseError($e, 'búsqueda de usuarios');
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
            $this->handleDatabaseError($e, 'obtención de usuarios por rol');
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
            $this->handleDatabaseError($e, 'obtención de usuarios activos');
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
            $this->handleDatabaseError($e, 'obtención de administradores');
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
            $this->handleDatabaseError($e, 'obtención de asesores');
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
