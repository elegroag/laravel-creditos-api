<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'usuarios';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'full_name',
        'phone',
        'password_hash',
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'roles',
        'disabled',
        'last_login'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled' => 'boolean',
            'last_login' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'roles' => 'json'
        ];
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Set password attribute (hash it).
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_hash'] = Hash::make($value);
    }

    /**
     * Check if user has specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $roles = $this->roles ?? [];

        // Get permissions from roles table
        $rolePermissions = Role::whereIn('nombre', $roles)
            ->pluck('permisos')
            ->flatMap(function ($permisos) {
                return json_decode($permisos, true) ?? [];
            })
            ->toArray();

        return in_array($permission, $rolePermissions);
    }

    /**
     * Check if user has specific role.
     */
    public function hasRole(string $roleName): bool
    {
        $systemRoles = ['user_trabajador', 'user_empresa', 'adviser', 'administrator'];
        $userRoles = $this->roles ?? [];

        if (in_array($roleName, $systemRoles)) {
            return in_array($roleName, $userRoles);
        }

        return false;
    }

    /**
     * Check if user is administrator.
     */
    public function getIsAdministratorAttribute(): bool
    {
        return $this->hasRole('administrator');
    }

    /**
     * Check if user is adviser.
     */
    public function getIsAdviserAttribute(): bool
    {
        return $this->hasRole('adviser');
    }

    /**
     * Check if user is regular user.
     */
    public function getIsRegularUserAttribute(): bool
    {
        $hasUserRole = $this->hasRole('user_trabajador') || $this->hasRole('user_empresa');
        $notAdviserOrAdmin = !$this->is_adviser && !$this->is_administrator;

        return $hasUserRole && $notAdviserOrAdmin;
    }

    /**
     * Get username in lowercase.
     */
    public function getUsernameAttribute(string $value): string
    {
        return strtolower($value);
    }

    /**
     * Set username attribute (normalize to lowercase).
     */
    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = strtolower($value);
    }

    /**
     * Find user by username.
     */
    public static function findByUsername(string $username): ?self
    {
        return static::where('username', strtolower($username))->first();
    }

    /**
     * Find user by email.
     */
    public static function findByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }

    /**
     * Find user by document number.
     */
    public static function findByDocumentNumber(string $documentNumber): ?self
    {
        return static::where('numero_documento', $documentNumber)->first();
    }

    /**
     * Scope to get only active users.
     */
    public function scopeActive($query)
    {
        return $query->where('disabled', false);
    }

    /**
     * Scope to get users by role.
     */
    public function scopeByRole($query, string $role)
    {
        return $query->whereJsonContains('roles', $role);
    }

    /**
     * Get related solicitudes.
     */
    public function solicitudes()
    {
        return $this->hasMany(SolicitudCredito::class, 'owner_username', 'username');
    }

    /**
     * Get related entidad digital.
     */
    public function entidadDigital()
    {
        return $this->hasOne(EntidadDigital::class, 'username', 'username');
    }

    /**
     * Get related documentos postulantes.
     */
    public function documentosPostulantes()
    {
        return $this->hasMany(DocumentoPostulante::class, 'username', 'username');
    }
}
