<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class UserRepository extends BaseRepository
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    /**
     * Find user by ID.
     */
    public function findById($id)
    {
        return $this->model->find($id);
    }

    /**
     * Find user by username.
     */
    public function findByUsername(string $username): ?User
    {
        return $this->model->where('username', strtolower($username))->first();
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Find user by username or email.
     */
    public function findByUsernameOrEmail(string $identifier): ?User
    {
        return $this->model->where(function ($query) use ($identifier) {
            $query->where('username', strtolower($identifier))
                ->orWhere('email', $identifier);
        })->first();
    }

    /**
     * Update user and get updated record.
     */
    public function updateAndGet(int $id, array $data): ?User
    {
        $user = $this->findById($id);

        if (!$user) {
            return null;
        }

        $user->update($data);

        return $user->fresh();
    }

    /**
     * Find user by document number.
     */
    public function findByDocumentNumber(string $documentNumber): ?User
    {
        return $this->model->where('numero_documento', $documentNumber)->first();
    }

    /**
     * Create user with password hash.
     */
    public function createWithPassword(array $userData, string $password)
    {
        $userData['password_hash'] = Hash::make($password);
        $userData['username'] = strtolower($userData['username']);

        return $this->create($userData);
    }

    /**
     * Update user password.
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $user = $this->findById($userId);

        if (!$user) {
            return false;
        }

        return $user->update(['password' => Hash::make($newPassword)]);
    }

    /**
     * List users with filters.
     */
    public function listUsers(
        int $skip = 0,
        int $limit = 50,
        ?bool $disabled = null,
        ?array $roles = null
    ): Collection {
        $query = $this->model->query();

        if ($disabled !== null) {
            $query->where('disabled', $disabled);
        }

        if ($roles !== null) {
            foreach ($roles as $role) {
                $query->whereJsonContains('roles', $role);
            }
        }

        return $query->orderBy('created_at', 'desc')
            ->offset($skip)
            ->limit($limit)
            ->get();
    }

    /**
     * Count users with filters.
     */
    public function countUsers(?bool $disabled = null, ?array $roles = null): int
    {
        $query = $this->model->query();

        if ($disabled !== null) {
            $query->where('disabled', $disabled);
        }

        if ($roles !== null) {
            foreach ($roles as $role) {
                $query->whereJsonContains('roles', $role);
            }
        }

        return $query->count();
    }

    /**
     * Disable user.
     */
    public function disableUser(int $userId): bool
    {
        return $this->update($userId, ['disabled' => true]);
    }

    /**
     * Enable user.
     */
    public function enableUser(int $userId): bool
    {
        return $this->update($userId, ['disabled' => false]);
    }

    /**
     * Find user by role.
     */
    public function findByRole(string $role): ?User
    {
        return $this->model->whereJsonContains('roles', $role)->first();
    }

    /**
     * Get users by role.
     */
    public function getByRole(string $role): Collection
    {
        return $this->model->whereJsonContains('roles', $role)->get();
    }

    /**
     * Check if username exists.
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $query = $this->model->where('username', strtolower($username));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Check if email exists.
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->model->where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get active users.
     */
    public function getActive(): Collection
    {
        return $this->model->where('disabled', false)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get administrators.
     */
    public function getAdministrators(): Collection
    {
        return $this->model->whereJsonContains('roles', 'administrator')
            ->where('disabled', false)
            ->get();
    }

    /**
     * Get advisers.
     */
    public function getAdvisers(): Collection
    {
        return $this->model->whereJsonContains('roles', 'adviser')
            ->where('disabled', false)
            ->get();
    }

    /**
     * Update last login.
     */
    public function updateLastLogin(int $userId): bool
    {
        return $this->update($userId, ['last_login' => now()]);
    }

    /**
     * Search users by multiple fields.
     */
    public function searchUsers(string $term, ?array $roles = null): Collection
    {
        $query = $this->model->where(function ($q) use ($term) {
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

        return $query->where('disabled', false)->get();
    }

    /**
     * Get user statistics.
     */
    public function getStatistics(): array
    {
        $total = $this->model->count();
        $active = $this->model->where('disabled', false)->count();
        $disabled = $this->model->where('disabled', true)->count();

        // Get role statistics using JSON operations
        $byRole = [];

        // Get all users with roles
        $usersWithRoles = $this->model->whereNotNull('roles')->get();

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
    }

    /**
     * Verify user credentials.
     */
    public function verifyCredentials(string $identifier, string $password): ?User
    {
        $user = $this->findByUsernameOrEmail($identifier);

        if (!$user || $user->disabled || !$user->is_active) {
            return null;
        }

        if (!Hash::check($password, $user->password_hash)) {
            return null;
        }

        return $user;
    }

    /**
     * Get users created in date range.
     */
    public function getUsersByDateRange(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): Collection
    {
        return $this->model->whereBetween('created_at', [$startDate, $endDate])->get();
    }

    /**
     * Get users with specific permissions.
     */
    public function getUsersWithPermission(string $permission): Collection
    {
        return $this->model->where('disabled', false)
            ->whereJsonContains('permissions', $permission)
            ->get();
    }

    /**
     * Bulk update users.
     */
    public function bulkUpdate(array $userIds, array $data): int
    {
        return $this->model->whereIn('id', $userIds)->update($data);
    }

    /**
     * Get user permissions.
     */
    public function getUserPermissions(int $userId): array
    {
        $user = $this->findById($userId);

        if (!$user) {
            return [];
        }

        // Get permissions from roles
        $roles = $user->roles ?? [];
        $permissions = [];

        if (is_array($roles)) {
            foreach ($roles as $role) {
                $roleModel = \App\Models\Role::where('nombre', $role)->first();
                if ($roleModel) {
                    $rolePermissions = json_decode($roleModel->permisos, true) ?? [];
                    $permissions = array_merge($permissions, $rolePermissions);
                }
            }
        }

        return array_unique($permissions);
    }
}
