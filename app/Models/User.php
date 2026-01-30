<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'users';

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
        'password',
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'roles',
        'disabled',
        'is_active',
        'last_login',
        'permissions',
        'role_ids'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'password_hash'
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
            'password' => 'hashed',
            'password_hash' => 'hashed',
            'disabled' => 'boolean',
            'is_active' => 'boolean',
            'last_login' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'role_ids' => 'array',
            'roles' => 'array',
            'permissions' => 'array'
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
     * Check if user has specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Check if user has specific role.
     */
    public function hasRole(string $roleName): bool
    {
        $systemRoles = ['user_trabajador', 'user_empresa', 'adviser', 'administrator'];

        if (in_array($roleName, $systemRoles)) {
            return in_array($roleName, $this->roles ?? []);
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
     * Set password attribute (hash it).
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::make($value);
        $this->attributes['password_hash'] = Hash::make($value);
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
     * Find user by document number.
     */
    public static function findByDocumentNumber(string $documentNumber): ?self
    {
        return static::where('numero_documento', $documentNumber)->first();
    }
}
