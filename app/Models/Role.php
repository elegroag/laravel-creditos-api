<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'permisos',
        'activo'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'permisos' => 'json'
        ];
    }

    /**
     * Scope to get only active roles.
     */
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Find role by name.
     */
    public static function findByName(string $name): ?self
    {
        return static::where('nombre', $name)->first();
    }

    /**
     * Check if role has specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = json_decode($this->permisos, true) ?? [];
        return in_array($permission, $permissions);
    }

    /**
     * Add permission to role.
     */
    public function addPermission(string $permission): void
    {
        $permissions = json_decode($this->permisos, true) ?? [];

        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permisos = json_encode($permissions);
            $this->save();
        }
    }

    /**
     * Remove permission from role.
     */
    public function removePermission(string $permission): void
    {
        $permissions = json_decode($this->permisos, true) ?? [];

        $key = array_search($permission, $permissions);
        if ($key !== false) {
            unset($permissions[$key]);
            $this->permisos = json_encode(array_values($permissions));
            $this->save();
        }
    }

    /**
     * Get all permissions as array.
     */
    public function getPermissionsArray(): array
    {
        return json_decode($this->permisos, true) ?? [];
    }

    /**
     * Get users with this role.
     */
    public function users()
    {
        return User::whereJsonContains('roles', $this->nombre);
    }
}
