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
        'color',
        'orden',
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
            'orden' => 'integer',
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
    public static function findByNombre(string $nombre): ?self
    {
        return static::where('nombre', $nombre)->first();
    }

    /**
     * Check if role is active.
     */
    public function isActive(): bool
    {
        return $this->activo;
    }

    /**
     * Get users with this role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }

    /**
     * Check if role has specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissionsArray());
    }

    /**
     * Add permission to role.
     */
    public function addPermission(string $permission): void
    {
        $permissions = $this->getPermissionsArray();

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
        $permissions = $this->getPermissionsArray();
        $key = array_search($permission, $permissions);

        if ($key !== false) {
            unset($permissions[$key]);
            $this->permisos = json_encode(array_values($permissions));
            $this->save();
        }
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'permisos' => $this->getPermissionsArray(),
            'color' => $this->color,
            'orden' => $this->orden,
            'activo' => $this->activo,
            'users_count' => $this->users()->count(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get all active roles for API.
     */
    public static function getAllActiveForApi(): array
    {
        return static::active()->ordered()->get()->map(function ($role) {
            return $role->toApiArray();
        })->toArray();
    }
}
