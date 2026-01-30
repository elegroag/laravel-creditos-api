<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RolePermissionService
{
    /**
     * Roles predefinidos del sistema
     */
    public static function getSystemRoles(): array
    {
        return [
            'administrator' => [
                'name' => 'Administrator',
                'description' => 'Administrador del sistema con acceso completo',
                'permissions' => ['*'], // Todos los permisos
                'level' => 100
            ],
            'admin' => [
                'name' => 'Admin',
                'description' => 'Administrador con acceso amplio',
                'permissions' => [
                    'manage_users', 'view_users', 'create_users', 'update_users', 'delete_users',
                    'manage_solicitudes', 'view_solicitudes', 'create_solicitudes', 'update_solicitudes', 'delete_solicitudes',
                    'view_reports', 'export_data', 'manage_settings'
                ],
                'level' => 90
            ],
            'adviser' => [
                'name' => 'Adviser',
                'description' => 'Asesor de crédito',
                'permissions' => [
                    'view_solicitudes', 'create_solicitudes', 'update_solicitudes',
                    'view_reports', 'manage_documents', 'approve_solicitudes'
                ],
                'level' => 70
            ],
            'user_trabajador' => [
                'name' => 'User Trabajador',
                'description' => 'Trabajador con acceso a solicitudes',
                'permissions' => [
                    'view_own_solicitudes', 'create_solicitudes', 'update_own_solicitudes',
                    'manage_own_documents', 'view_reports'
                ],
                'level' => 50
            ],
            'user_empresa' => [
                'name' => 'User Empresa',
                'description' => 'Usuario de empresa',
                'permissions' => [
                    'view_own_solicitudes', 'create_solicitudes', 'update_own_solicitudes',
                    'manage_own_documents'
                ],
                'level' => 40
            ],
            'guest' => [
                'name' => 'Guest',
                'description' => 'Usuario invitado con acceso limitado',
                'permissions' => [
                    'view_public_info'
                ],
                'level' => 10
            ]
        ];
    }

    /**
     * Permisos predefinidos del sistema
     */
    public static function getSystemPermissions(): array
    {
        return [
            // Gestión de usuarios
            'manage_users' => 'Gestionar usuarios',
            'view_users' => 'Ver usuarios',
            'create_users' => 'Crear usuarios',
            'update_users' => 'Actualizar usuarios',
            'delete_users' => 'Eliminar usuarios',
            
            // Gestión de solicitudes
            'manage_solicitudes' => 'Gestionar solicitudes',
            'view_solicitudes' => 'Ver solicitudes',
            'create_solicitudes' => 'Crear solicitudes',
            'update_solicitudes' => 'Actualizar solicitudes',
            'delete_solicitudes' => 'Eliminar solicitudes',
            'approve_solicitudes' => 'Aprobar solicitudes',
            'reject_solicitudes' => 'Rechazar solicitudes',
            
            // Permisos propios
            'view_own_solicitudes' => 'Ver solicitudes propias',
            'update_own_solicitudes' => 'Actualizar solicitudes propias',
            'manage_own_documents' => 'Gestionar documentos propios',
            
            // Gestión de documentos
            'manage_documents' => 'Gestionar documentos',
            'upload_documents' => 'Subir documentos',
            'delete_documents' => 'Eliminar documentos',
            
            // Reportes y exportación
            'view_reports' => 'Ver reportes',
            'export_data' => 'Exportar datos',
            'generate_reports' => 'Generar reportes',
            
            // Configuración
            'manage_settings' => 'Gestionar configuración',
            'view_settings' => 'Ver configuración',
            
            // Información pública
            'view_public_info' => 'Ver información pública'
        ];
    }

    /**
     * Asignar rol a un usuario
     */
    public function assignRole(User $user, string $role): bool
    {
        try {
            $systemRoles = self::getSystemRoles();
            
            if (!isset($systemRoles[$role])) {
                Log::warning('Intento de asignar rol no existente', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'role' => $role
                ]);
                return false;
            }

            $currentRoles = $this->getUserRoles($user);
            
            // Evitar duplicados
            if (in_array($role, $currentRoles)) {
                Log::info('El usuario ya tiene el rol', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'role' => $role
                ]);
                return true;
            }

            $currentRoles[] = $role;
            
            $user->update([
                'roles' => json_encode($currentRoles),
                'updated_at' => now()
            ]);

            // Limpiar cache
            $this->clearUserCache($user->id);

            Log::info('Rol asignado exitosamente', [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $role,
                'new_roles' => $currentRoles
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error al asignar rol', [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $role,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Remover rol de un usuario
     */
    public function removeRole(User $user, string $role): bool
    {
        try {
            $currentRoles = $this->getUserRoles($user);
            
            if (!in_array($role, $currentRoles)) {
                Log::info('El usuario no tiene el rol a remover', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'role' => $role
                ]);
                return true;
            }

            $currentRoles = array_values(array_diff($currentRoles, [$role]));
            
            $user->update([
                'roles' => json_encode($currentRoles),
                'updated_at' => now()
            ]);

            // Limpiar cache
            $this->clearUserCache($user->id);

            Log::info('Rol removido exitosamente', [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $role,
                'new_roles' => $currentRoles
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error al remover rol', [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $role,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Asignar permisos personalizados a un usuario
     */
    public function assignPermissions(User $user, array $permissions): bool
    {
        try {
            $systemPermissions = array_keys(self::getSystemPermissions());
            
            // Validar que todos los permisos existan
            foreach ($permissions as $permission) {
                if (!in_array($permission, $systemPermissions)) {
                    Log::warning('Permiso no existente', [
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'permission' => $permission
                    ]);
                    return false;
                }
            }

            $user->update([
                'permissions' => json_encode($permissions),
                'updated_at' => now()
            ]);

            // Limpiar cache
            $this->clearUserCache($user->id);

            Log::info('Permisos asignados exitosamente', [
                'user_id' => $user->id,
                'username' => $user->username,
                'permissions' => $permissions
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error al asignar permisos', [
                'user_id' => $user->id,
                'username' => $user->username,
                'permissions' => $permissions,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener roles de un usuario
     */
    public function getUserRoles(User $user): array
    {
        $cacheKey = "user_roles_{$user->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($user) {
            if (isset($user->roles)) {
                return is_array($user->roles) ? $user->roles : json_decode($user->roles, true) ?? [];
            }
            return [];
        });
    }

    /**
     * Obtener permisos de un usuario
     */
    public function getUserPermissions(User $user): array
    {
        $cacheKey = "user_permissions_{$user->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $roles = $this->getUserRoles($user);
            $permissions = [];

            // Obtener permisos de los roles
            foreach ($roles as $role) {
                $rolePermissions = $this->getRolePermissions($role);
                $permissions = array_merge($permissions, $rolePermissions);
            }

            // Agregar permisos personalizados del usuario
            if (isset($user->permissions)) {
                $userPermissions = is_array($user->permissions) ? $user->permissions : json_decode($user->permissions, true) ?? [];
                $permissions = array_merge($permissions, $userPermissions);
            }

            // Eliminar duplicados y retornar
            return array_unique($permissions);
        });
    }

    /**
     * Verificar si un usuario tiene un rol específico
     */
    public function userHasRole(User $user, string $role): bool
    {
        return in_array($role, $this->getUserRoles($user));
    }

    /**
     * Verificar si un usuario tiene un permiso específico
     */
    public function userHasPermission(User $user, string $permission): bool
    {
        // Los administradores tienen todos los permisos
        if ($this->userHasRole($user, 'administrator') || $this->userHasRole($user, 'admin')) {
            return true;
        }

        return in_array($permission, $this->getUserPermissions($user));
    }

    /**
     * Obtener permisos de un rol específico
     */
    private function getRolePermissions(string $role): array
    {
        $systemRoles = self::getSystemRoles();
        
        if (isset($systemRoles[$role])) {
            $roleData = $systemRoles[$role];
            
            // Si el rol tiene todos los permisos
            if (in_array('*', $roleData['permissions'])) {
                return array_keys(self::getSystemPermissions());
            }
            
            return $roleData['permissions'];
        }
        
        return [];
    }

    /**
     * Limpiar cache de usuario
     */
    private function clearUserCache(int $userId): void
    {
        Cache::forget("user_roles_{$userId}");
        Cache::forget("user_permissions_{$userId}");
    }

    /**
     * Obtener estadísticas de roles y permisos
     */
    public function getRoleStatistics(): array
    {
        try {
            $users = User::all();
            $systemRoles = self::getSystemRoles();
            
            $stats = [
                'total_users' => $users->count(),
                'role_distribution' => [],
                'permission_coverage' => [],
                'most_common_roles' => [],
                'users_with_custom_permissions' => 0
            ];

            // Inicializar contadores
            foreach ($systemRoles as $roleKey => $roleData) {
                $stats['role_distribution'][$roleKey] = 0;
            }

            // Contar roles y permisos
            foreach ($users as $user) {
                $userRoles = $this->getUserRoles($user);
                $userPermissions = $this->getUserPermissions($user);

                // Contar roles
                foreach ($userRoles as $role) {
                    if (isset($stats['role_distribution'][$role])) {
                        $stats['role_distribution'][$role]++;
                    }
                }

                // Verificar permisos personalizados
                if (isset($user->permissions) && !empty($user->permissions)) {
                    $stats['users_with_custom_permissions']++;
                }
            }

            // Ordenar roles más comunes
            arsort($stats['role_distribution']);
            $stats['most_common_roles'] = array_slice($stats['role_distribution'], 0, 5, true);

            return $stats;

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de roles', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
