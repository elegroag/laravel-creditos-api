<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Rol extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'permisos',
        'activo',
        'createdAt',
        'updatedAt'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permisos' => 'array',
            'activo' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime'
        ];
    }

    /**
     * Get the MongoDB primary key.
     */
    protected $primaryKey = '_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Find by nombre.
     */
    public static function findByNombre(string $nombre): ?self
    {
        return static::where('nombre', $nombre)->first();
    }

    /**
     * Find active roles.
     */
    public static function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('activo', true)->get();
    }

    /**
     * Check if has specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permisos ?? []);
    }

    /**
     * Add permission.
     */
    public function addPermission(string $permission): void
    {
        $permisos = $this->permisos ?? [];
        
        if (!in_array($permission, $permisos)) {
            $permisos[] = $permission;
            $this->permisos = $permisos;
            $this->save();
        }
    }

    /**
     * Remove permission.
     */
    public function removePermission(string $permission): void
    {
        $permisos = $this->permisos ?? [];
        $key = array_search($permission, $permisos);
        
        if ($key !== false) {
            unset($permisos[$key]);
            $this->permisos = array_values($permisos);
            $this->save();
        }
    }

    /**
     * Get permissions count.
     */
    public function getPermissionsCountAttribute(): int
    {
        return count($this->permisos ?? []);
    }

    /**
     * Scope active.
     */
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope by permission.
     */
    public function scopeByPermission($query, string $permission)
    {
        return $query->where('permisos', $permission);
    }

    /**
     * Get formatted permissions list.
     */
    public function getFormattedPermissionsAttribute(): array
    {
        return array_map(function($permission) {
            return [
                'name' => $permission,
                'label' => $this->formatPermissionLabel($permission),
                'category' => $this->getPermissionCategory($permission)
            ];
        }, $this->permisos ?? []);
    }

    /**
     * Format permission label.
     */
    private function formatPermissionLabel(string $permission): string
    {
        return match($permission) {
            'users.create' => 'Crear usuarios',
            'users.edit' => 'Editar usuarios',
            'users.delete' => 'Eliminar usuarios',
            'users.view' => 'Ver usuarios',
            'applications.create' => 'Crear solicitudes',
            'applications.edit' => 'Editar solicitudes',
            'applications.delete' => 'Eliminar solicitudes',
            'applications.view_all' => 'Ver todas las solicitudes',
            'applications.view_own' => 'Ver solicitudes propias',
            'applications.approve' => 'Aprobar solicitudes',
            'applications.reject' => 'Rechazar solicitudes',
            'roles.manage' => 'Gestionar roles',
            'system.admin' => 'Administración del sistema',
            default => ucfirst(str_replace('.', ' ', $permission))
        };
    }

    /**
     * Get permission category.
     */
    private function getPermissionCategory(string $permission): string
    {
        if (str_starts_with($permission, 'users.')) {
            return 'Usuarios';
        } elseif (str_starts_with($permission, 'applications.')) {
            return 'Solicitudes';
        } elseif (str_starts_with($permission, 'roles.')) {
            return 'Roles';
        } elseif (str_starts_with($permission, 'system.')) {
            return 'Sistema';
        }
        
        return 'General';
    }

    /**
     * Check if is system administrator.
     */
    public function isSystemAdministrator(): bool
    {
        return $this->nombre === 'administrator' || $this->hasPermission('system.admin');
    }

    /**
     * Check if is adviser.
     */
    public function isAdviser(): bool
    {
        return $this->nombre === 'adviser' || $this->hasPermission('applications.approve');
    }

    /**
     * Check if is regular user.
     */
    public function isRegularUser(): bool
    {
        return in_array($this->nombre, ['user_empresa', 'user_trabajador']);
    }

    /**
     * Get role type label.
     */
    public function getRoleTypeLabelAttribute(): string
    {
        return match($this->nombre) {
            'administrator' => 'Administrador del Sistema',
            'adviser' => 'Asesor de Crédito',
            'user_empresa' => 'Usuario Empresa',
            'user_trabajador' => 'Usuario Trabajador',
            default => 'Otro'
        };
    }

    /**
     * Get role color for UI.
     */
    public function getRoleColorAttribute(): string
    {
        return match($this->nombre) {
            'administrator' => 'red',
            'adviser' => 'blue',
            'user_empresa' => 'green',
            'user_trabajador' => 'purple',
            default => 'gray'
        };
    }

    /**
     * Activate role.
     */
    public function activate(): void
    {
        $this->activo = true;
        $this->save();
    }

    /**
     * Deactivate role.
     */
    public function deactivate(): void
    {
        $this->activo = false;
        $this->save();
    }

    /**
     * Update permissions.
     */
    public function updatePermissions(array $permissions): void
    {
        $this->permisos = $permissions;
        $this->save();
    }

    /**
     * Get all available permissions grouped by category.
     */
    public static function getAllAvailablePermissions(): array
    {
        return [
            'Usuarios' => [
                'users.create' => 'Crear usuarios',
                'users.edit' => 'Editar usuarios',
                'users.delete' => 'Eliminar usuarios',
                'users.view' => 'Ver usuarios'
            ],
            'Solicitudes' => [
                'applications.create' => 'Crear solicitudes',
                'applications.edit' => 'Editar solicitudes',
                'applications.delete' => 'Eliminar solicitudes',
                'applications.view_all' => 'Ver todas las solicitudes',
                'applications.view_own' => 'Ver solicitudes propias',
                'applications.approve' => 'Aprobar solicitudes',
                'applications.reject' => 'Rechazar solicitudes'
            ],
            'Roles' => [
                'roles.manage' => 'Gestionar roles'
            ],
            'Sistema' => [
                'system.admin' => 'Administración del sistema'
            ]
        ];
    }
}
