<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Auth;

trait HasAuthHelpers
{
    /**
     * Obtiene el usuario autenticado actual
     */
    protected function getCurrentUser()
    {
        return Auth::user();
    }

    /**
     * Obtiene el username del usuario autenticado
     */
    protected function getCurrentUsername(): ?string
    {
        $user = $this->getCurrentUser();
        return $user ? $user->username : null;
    }

    /**
     * Obtiene los roles del usuario autenticado
     */
    protected function getCurrentUserRoles(): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return [];
        }

        return $this->getUserRolesFromUser($user);
    }

    /**
     * Obtiene los permisos del usuario autenticado
     */
    protected function getCurrentUserPermissions(): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return [];
        }

        return $this->getUserPermissionsFromUser($user);
    }

    /**
     * Verifica si el usuario actual es administrador
     */
    protected function isAdmin(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $this->checkIfUserIsAdmin($user) : false;
    }

    /**
     * Verifica si el usuario actual es asesor
     */
    protected function isAdviser(): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $this->checkIfUserIsAdviser($user) : false;
    }

    /**
     * Verifica si el usuario actual tiene un rol específico
     */
    protected function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $this->hasRoleForUser($user, $role) : false;
    }

    /**
     * Verifica si el usuario actual tiene un permiso específico
     */
    protected function hasPermission(string $permission): bool
    {
        $user = $this->getCurrentUser();
        return $user ? $this->hasPermissionForUser($user, $permission) : false;
    }

    /**
     * Verifica si el usuario actual tiene alguno de los roles especificados
     */
    protected function hasAnyRole(array $roles): bool
    {
        $userRoles = $this->getCurrentUserRoles();
        return !empty(array_intersect($roles, $userRoles));
    }

    /**
     * Verifica si el usuario actual tiene alguno de los permisos especificados
     */
    protected function hasAnyPermission(array $permissions): bool
    {
        // Los administradores tienen todos los permisos
        if ($this->isAdmin()) {
            return true;
        }

        $userPermissions = $this->getCurrentUserPermissions();
        return !empty(array_intersect($permissions, $userPermissions));
    }

    /**
     * Verifica si el usuario actual tiene todos los roles especificados
     */
    protected function hasAllRoles(array $roles): bool
    {
        $userRoles = $this->getCurrentUserRoles();
        return empty(array_diff($roles, $userRoles));
    }

    /**
     * Verifica si el usuario actual tiene todos los permisos especificados
     */
    protected function hasAllPermissions(array $permissions): bool
    {
        // Los administradores tienen todos los permisos
        if ($this->isAdmin()) {
            return true;
        }

        $userPermissions = $this->getCurrentUserPermissions();
        return empty(array_diff($permissions, $userPermissions));
    }

    // Métodos privados auxiliares

    /**
     * Obtiene los roles de un usuario
     */
    private function getUserRolesFromUser($user): array
    {
        if (isset($user->roles)) {
            return is_array($user->roles) ? $user->roles : json_decode($user->roles, true) ?? [];
        }
        return [];
    }

    /**
     * Obtiene los permisos de un usuario
     */
    private function getUserPermissionsFromUser($user): array
    {
        if (isset($user->permissions)) {
            return is_array($user->permissions) ? $user->permissions : json_decode($user->permissions, true) ?? [];
        }
        return [];
    }

    /**
     * Verifica si un usuario es administrador
     */
    private function checkIfUserIsAdmin($user): bool
    {
        return $this->hasRoleForUser($user, 'administrator') || $this->hasRoleForUser($user, 'admin');
    }

    /**
     * Verifica si un usuario es asesor
     */
    private function checkIfUserIsAdviser($user): bool
    {
        return $this->hasRoleForUser($user, 'adviser') || $this->hasRoleForUser($user, 'user_trabajador');
    }

    /**
     * Verifica si un usuario tiene un rol específico
     */
    private function hasRoleForUser($user, string $role): bool
    {
        $userRoles = $this->getUserRolesFromUser($user);
        return in_array($role, $userRoles);
    }

    /**
     * Verifica si un usuario tiene un permiso específico
     */
    private function hasPermissionForUser($user, string $permission): bool
    {
        // Los administradores tienen todos los permisos
        if ($this->checkIfUserIsAdmin($user)) {
            return true;
        }

        $userPermissions = $this->getUserPermissionsFromUser($user);
        return in_array($permission, $userPermissions);
    }
}
