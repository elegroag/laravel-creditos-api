<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmpresaConvenio extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'empresas_convenio';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nit',
        'razon_social',
        'fecha_convenio',
        'fecha_vencimiento',
        'estado',
        'representante_documento',
        'representante_nombre',
        'telefono',
        'correo',
        'direccion',
        'ciudad',
        'departamento',
        'sector_economico',
        'numero_empleados',
        'tipo_empresa',
        'descripcion',
        'notas_internas'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nit' => 'integer',
            'fecha_convenio' => 'date',
            'fecha_vencimiento' => 'date',
            'numero_empleados' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Scope to get only active agreements.
     */
    public function scopeActive($query)
    {
        return $query->where('estado', 'Activo');
    }

    /**
     * Scope to get agreements by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('estado', $status);
    }

    /**
     * Scope to get agreements expiring soon.
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('fecha_vencimiento', '<=', now()->addDays($days))
            ->where('fecha_vencimiento', '>', now());
    }

    /**
     * Scope to get expired agreements.
     */
    public function scopeExpired($query)
    {
        return $query->where('fecha_vencimiento', '<', now());
    }

    /**
     * Find agreement by NIT.
     */
    public static function findByNit(int $nit): ?self
    {
        return static::where('nit', $nit)->first();
    }

    /**
     * Check if agreement is active.
     */
    public function isActive(): bool
    {
        return $this->estado === 'Activo' &&
            $this->fecha_vencimiento &&
            $this->fecha_vencimiento >= now();
    }

    /**
     * Check if agreement is expired.
     */
    public function isExpired(): bool
    {
        return $this->fecha_vencimiento &&
            $this->fecha_vencimiento < now();
    }

    /**
     * Get days until expiration.
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->fecha_vencimiento) {
            return null;
        }

        return now()->diffInDays($this->fecha_vencimiento, false);
    }

    /**
     * Get related solicitudes.
     */
    public function solicitudes()
    {
        return $this->hasMany(SolicitudSolicitante::class, 'empresa_nit', 'nit');
    }

    /**
     * Get related postulaciones.
     */
    public function postulaciones()
    {
        return $this->hasMany(Postulacion::class, 'empresa_nit', 'nit');
    }

    /**
     * Get full address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->direccion,
            $this->ciudad,
            $this->departamento
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get representative full name.
     */
    public function getRepresentanteFullNameAttribute(): string
    {
        return $this->representante_nombre ?? '';
    }

    /**
     * Get status with color.
     */
    public function getStatusWithColorAttribute(): array
    {
        $colors = [
            'Activo' => '#10B981',
            'Inactivo' => '#6B7280',
            'Suspendido' => '#F59E0B',
            'Vencido' => '#EF4444'
        ];

        return [
            'status' => $this->estado,
            'color' => $colors[$this->estado] ?? '#6B7280'
        ];
    }
}
