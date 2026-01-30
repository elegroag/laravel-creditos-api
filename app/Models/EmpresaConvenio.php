<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmpresaConvenio extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'empresas_convenios';

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
        'documentos_adjuntos',
        'condiciones_comerciales',
        'notas_internas',
        'created_at',
        'updated_at'
    ];

    protected function casts(): array
    {
        return [
            'nit' => 'integer',
            'fecha_convenio' => 'datetime',
            'fecha_vencimiento' => 'datetime',
            'estado' => 'string',
            'numero_empleados' => 'integer',
            'documentos_adjuntos' => 'array',
            'condiciones_comerciales' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    protected $primaryKey = '_id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Find agreement by NIT.
     */
    public static function findByNit(int $nit): ?self
    {
        return static::where('nit', $nit)->first();
    }

    /**
     * Find active agreement by NIT.
     */
    public static function findActiveByNit(int $nit): ?self
    {
        return static::where('nit', $nit)->where('estado', 'Activo')->first();
    }

    /**
     * Scope for active agreements.
     */
    public function scopeActive($query)
    {
        return $query->where('estado', 'Activo');
    }

    /**
     * Scope for inactive agreements.
     */
    public function scopeInactive($query)
    {
        return $query->where('estado', '!=', 'Activo');
    }

    /**
     * Scope for agreements expiring soon.
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('estado', 'Activo')
            ->where('fecha_vencimiento', '>', now())
            ->where('fecha_vencimiento', '<=', now()->addDays($days));
    }

    /**
     * Scope for expired agreements.
     */
    public function scopeExpired($query)
    {
        return $query->where('estado', 'Activo')
            ->where('fecha_vencimiento', '<', now());
    }

    /**
     * Check if agreement is active.
     */
    public function isActive(): bool
    {
        return $this->estado === 'Activo';
    }

    /**
     * Check if agreement is expired.
     */
    public function isExpired(): bool
    {
        return $this->fecha_vencimiento && $this->fecha_vencimiento->isPast();
    }

    /**
     * Check if agreement is expiring soon.
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->isActive() &&
            $this->fecha_vencimiento &&
            $this->fecha_vencimiento->between(now(), now()->addDays($days));
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
     * Get formatted NIT.
     */
    public function getNitFormattedAttribute(): string
    {
        return number_format($this->nit, 0, '.', '.');
    }

    /**
     * Get formatted dates.
     */
    public function getFechaConvenioFormattedAttribute(): string
    {
        return $this->fecha_convenio ? $this->fecha_convenio->format('d/m/Y') : '';
    }

    public function getFechaVencimientoFormattedAttribute(): string
    {
        return $this->fecha_vencimiento ? $this->fecha_vencimiento->format('d/m/Y') : '';
    }

    /**
     * Get status label.
     */
    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            'Activo' => 'Activo',
            'Inactivo' => 'Inactivo',
            'Suspendido' => 'Suspendido',
            'Vencido' => 'Vencido',
            default => ucfirst(strtolower($this->estado))
        };
    }

    /**
     * Get status color.
     */
    public function getEstadoColorAttribute(): string
    {
        return match ($this->estado) {
            'Activo' => '#10B981',      // green
            'Inactivo' => '#6B7280',     // gray
            'Suspendido' => '#F59E0B',   // yellow
            'Vencido' => '#EF4444',       // red
            default => '#6B7280'
        };
    }

    /**
     * Get full address.
     */
    public function getDireccionCompletaAttribute(): string
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
    public function getRepresentanteNombreCompletoAttribute(): string
    {
        return trim($this->representante_nombre ?? '');
    }

    /**
     * Check if agreement can be renewed.
     */
    public function canBeRenewed(): bool
    {
        return $this->isExpired() || $this->isExpiringSoon(30);
    }

    /**
     * Get renewal date suggestion.
     */
    public function getRenewalDateSuggestion(): string
    {
        if (!$this->fecha_vencimiento) {
            return now()->addYear()->format('Y-m-d');
        }

        return $this->fecha_vencimiento->addYear()->format('Y-m-d');
    }

    /**
     * Add document to agreement.
     */
    public function addDocumento(string $tipo, string $ruta, string $nombre = null): void
    {
        $documentos = $this->documentos_adjuntos ?? [];

        $documentos[] = [
            'id' => uniqid(),
            'tipo' => $tipo,
            'nombre' => $nombre ?: $tipo,
            'ruta' => $ruta,
            'fecha_subida' => now()->toISOString(),
            'activo' => true
        ];

        $this->update(['documentos_adjuntos' => $documentos]);
    }

    /**
     * Remove document from agreement.
     */
    public function removeDocumento(string $documentoId): bool
    {
        $documentos = $this->documentos_adjuntos ?? [];

        $filtered = array_filter($documentos, function ($doc) use ($documentoId) {
            return $doc['id'] !== $documentoId;
        });

        if (count($filtered) === count($documentos)) {
            return false;
        }

        $this->update(['documentos_adjuntos' => array_values($filtered)]);
        return true;
    }

    /**
     * Get active documents.
     */
    public function getDocumentosActivosAttribute(): array
    {
        return array_filter($this->documentos_adjuntos ?? [], function ($doc) {
            return $doc['activo'] ?? true;
        });
    }

    /**
     * Add commercial condition.
     */
    public function addCondicionComercial(string $concepto, string $valor, string $descripcion = null): void
    {
        $condiciones = $this->condiciones_comerciales ?? [];

        $condiciones[] = [
            'id' => uniqid(),
            'concepto' => $concepto,
            'valor' => $valor,
            'descripcion' => $descripcion,
            'fecha_creacion' => now()->toISOString(),
            'activo' => true
        ];

        $this->update(['condiciones_comerciales' => $condiciones]);
    }

    /**
     * Get active commercial conditions.
     */
    public function getCondicionesComercialesActivasAttribute(): array
    {
        return array_filter($this->condiciones_comerciales ?? [], function ($cond) {
            return $cond['activo'] ?? true;
        });
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'nit' => $this->nit,
            'nit_formatted' => $this->nit_formatted,
            'razon_social' => $this->razon_social,
            'fecha_convenio' => $this->fecha_convenio?->toISOString(),
            'fecha_convenio_formatted' => $this->fecha_convenio_formatted,
            'fecha_vencimiento' => $this->fecha_vencimiento?->toISOString(),
            'fecha_vencimiento_formatted' => $this->fecha_vencimiento_formatted,
            'estado' => $this->estado,
            'estado_label' => $this->estado_label,
            'estado_color' => $this->estado_color,
            'representante_documento' => $this->representante_documento,
            'representante_nombre' => $this->representante_nombre,
            'representante_nombre_completo' => $this->representante_nombre_completo,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'direccion' => $this->direccion,
            'direccion_completa' => $this->direccion_completa,
            'ciudad' => $this->ciudad,
            'departamento' => $this->departamento,
            'sector_economico' => $this->sector_economico,
            'numero_empleados' => $this->numero_empleados,
            'tipo_empresa' => $this->tipo_empresa,
            'descripcion' => $this->descripcion,
            'documentos_adjuntos' => $this->documentos_adjuntos,
            'documentos_activos' => $this->documentos_activos,
            'condiciones_comerciales' => $this->condiciones_comerciales,
            'condiciones_comerciales_activas' => $this->condiciones_comerciales_activas,
            'notas_internas' => $this->notas_internas,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'can_be_renewed' => $this->canBeRenewed(),
            'renewal_date_suggestion' => $this->getRenewalDateSuggestion(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get statistics for agreements.
     */
    public static function getStatistics(): array
    {
        $total = static::count();
        $activos = static::active()->count();
        $inactivos = static::inactive()->count();
        $expirando = static::expiringSoon()->count();
        $vencidos = static::expired()->count();

        return [
            'total' => $total,
            'activos' => $activos,
            'inactivos' => $inactivos,
            'expirando' => $expirando,
            'vencidos' => $vencidos,
            'tasa_activacion' => $total > 0 ? round(($activos / $total) * 100, 2) : 0,
            'tasa_vencimiento' => $total > 0 ? round(($vencidos / $total) * 100, 2) : 0
        ];
    }
}
