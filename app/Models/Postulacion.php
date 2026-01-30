<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Postulacion extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'postulaciones';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'solicitante',
        'monto_solicitado',
        'plazo_meses',
        'descripcion',
        'estado',
        'timeline'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_solicitado' => 'float',
            'plazo_meses' => 'integer',
            'estado' => 'string',
            'solicitante' => 'array',
            'timeline' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'descripcion' => 'string'
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
     * Estados posibles para la postulación
     */
    const ESTADOS = [
        'POSTULADO' => 'Postulado',
        'EN_REVISION' => 'En Revisión',
        'APROBADO' => 'Aprobado',
        'RECHAZADO' => 'Rechazado',
        'EN_ESTUDIO' => 'En Estudio',
        'PRE_APROBADO' => 'Pre-Aprobado'
    ];

    /**
     * Add event to timeline.
     */
    public function addTimelineEvent(string $estado, string $detalle, ?string $usuario = null): void
    {
        $timeline = $this->timeline ?? [];
        $timeline[] = [
            'estado' => $estado,
            'fecha' => now()->toISOString(),
            'detalle' => $detalle,
            'usuario' => $usuario
        ];

        $this->timeline = $timeline;
        $this->estado = $estado;
        $this->save();
    }

    /**
     * Get formatted monto solicitado.
     */
    public function getMontoSolicitadoFormattedAttribute(): string
    {
        return '$' . number_format($this->monto_solicitado, 2, ',', '.');
    }

    /**
     * Get estado label.
     */
    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Scope by estado.
     */
    public function scopeByEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Get latest timeline event.
     */
    public function getLatestTimelineEventAttribute(): ?array
    {
        $timeline = $this->timeline ?? [];
        return empty($timeline) ? null : end($timeline);
    }

    /**
     * Check if postulacion can be modified.
     */
    public function canBeModified(): bool
    {
        return !in_array($this->estado, ['APROBADO', 'RECHAZADO']);
    }

    /**
     * Get solicitante full name.
     */
    public function getSolicitanteFullNameAttribute(): string
    {
        return $this->solicitante['nombres_apellidos'] ?? $this->solicitante['full_name'] ?? 'N/A';
    }

    /**
     * Get solicitante document.
     */
    public function getSolicitanteDocumentAttribute(): string
    {
        if (isset($this->solicitante['tipo_identificacion']) && isset($this->solicitante['numero_identificacion'])) {
            return $this->solicitante['tipo_identificacion'] . ' ' . $this->solicitante['numero_identificacion'];
        }
        return 'N/A';
    }

    /**
     * Get solicitante email.
     */
    public function getSolicitanteEmailAttribute(): string
    {
        return $this->solicitante['email'] ?? 'N/A';
    }

    /**
     * Get solicitante phone.
     */
    public function getSolicitantePhoneAttribute(): string
    {
        return $this->solicitante['telefono_movil'] ?? $this->solicitante['phone'] ?? 'N/A';
    }

    /**
     * Update estado with automatic timeline event.
     */
    public function updateEstado(string $nuevoEstado, string $detalle = 'Cambio de estado', ?string $usuario = null): bool
    {
        $this->addTimelineEvent($nuevoEstado, $detalle, $usuario);
        return true;
    }

    /**
     * Get timeline count.
     */
    public function getTimelineCountAttribute(): int
    {
        return count($this->timeline ?? []);
    }

    /**
     * Get formatted plazo.
     */
    public function getPlazoFormattedAttribute(): string
    {
        return $this->plazo_meses . ' meses';
    }
}
