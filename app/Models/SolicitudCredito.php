<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class SolicitudCredito extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'solicitudes_credito';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_username',
        'numero_solicitud',
        'monto_solicitado',
        'monto_aprobado',
        'plazo_meses',
        'tasa_interes',
        'destino_credito',
        'descripcion',
        'estado',
        'solicitante',
        'documentos',
        'timeline',
        'xml_filename',
        'payload'
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
            'monto_aprobado' => 'float',
            'plazo_meses' => 'integer',
            'tasa_interes' => 'float',
            'estado' => 'string',
            'solicitante' => 'array',
            'documentos' => 'array',
            'timeline' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'payload' => 'array'
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
     * Estados posibles para la solicitud (usando enum)
     */
    const ESTADOS = [
        'POSTULADO' => 'Postulado',
        'DOCUMENTOS_CARGADOS' => 'Documentos cargados',
        'ENVIADO_VALIDACION' => 'Enviado para validación',
        'PENDIENTE_FIRMADO' => 'Pendiente de firmado',
        'FIRMADO' => 'Firmado',
        'ENVIADO_PENDIENTE_APROBACION' => 'Enviado (pendiente de aprobación)',
        'APROBADO' => 'Aprobado',
        'DESEMBOLSADO' => 'Desembolsado',
        'FINALIZADO' => 'Finalizado',
        'RECHAZADO' => 'Rechazado',
        'DESISTE' => 'Desiste',
        'REQUIRE_CORRECCION' => 'Requiere correccion'
    ];

    /**
     * Find solicitud by owner username.
     */
    public static function findByOwner(string $username): ?self
    {
        return static::where('owner_username', $username)->first();
    }

    /**
     * Find solicitud by numero_solicitud.
     */
    public static function findByNumeroSolicitud(string $numeroSolicitud): ?self
    {
        return static::where('numero_solicitud', $numeroSolicitud)->first();
    }

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
     * Get formatted monto aprobado.
     */
    public function getMontoAprobadoFormattedAttribute(): ?string
    {
        if ($this->monto_aprobado) {
            return '$' . number_format($this->monto_aprobado, 2, ',', '.');
        }
        return null;
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
     * Scope by owner username.
     */
    public function scopeByOwner($query, string $username)
    {
        return $query->where('owner_username', $username);
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
     * Check if solicitud can be modified.
     */
    public function canBeModified(): bool
    {
        return !in_array($this->estado, ['APROBADO', 'DESEMBOLSADO', 'CANCELADO']);
    }

    /**
     * Get solicitante full name.
     */
    public function getSolicitanteFullNameAttribute(): string
    {
        return $this->solicitante['nombres_apellidos'] ?? 'N/A';
    }

    /**
     * Get solicitante document.
     */
    public function getSolicitanteDocumentAttribute(): string
    {
        return ($this->solicitante['tipo_identificacion'] ?? '') . ' ' . ($this->solicitante['numero_identificacion'] ?? '');
    }

    /**
     * Get estado enum instance.
     */
    public function getEstadoEnumAttribute(): ?\App\Enums\EstadoSolicitud
    {
        try {
            return \App\Enums\EstadoSolicitud::from($this->estado);
        } catch (\ValueError $e) {
            return null;
        }
    }

    /**
     * Get estado color from enum.
     */
    public function getEstadoColorAttribute(): string
    {
        $enum = $this->estado_enum;
        return $enum ? $enum->getColor() : '#6B7280';
    }

    /**
     * Check if can transition to new state.
     */
    public function canTransitionTo(string $newEstado): bool
    {
        $enum = $this->estado_enum;
        return $enum && $enum->canTransitionTo(\App\Enums\EstadoSolicitud::from($newEstado));
    }

    /**
     * Generate and assign numero_solicitud.
     */
    public function generateNumeroSolicitud(): void
    {
        if (empty($this->numero_solicitud)) {
            $this->numero_solicitud = NumeroSolicitud::generateNextNumber();
            $this->save();
        }
    }

    /**
     * Create solicitud with automatic number generation.
     */
    public static function createWithNumber(array $attributes): self
    {
        $solicitud = new static($attributes);
        $solicitud->generateNumeroSolicitud();

        // Add initial timeline event
        $solicitud->addTimelineEvent('POSTULADO', 'Creación de solicitud');

        return $solicitud;
    }

    /**
     * Update estado with validation.
     */
    public function updateEstadoValidado(string $nuevoEstado, string $detalle = 'Cambio de estado', ?string $usuario = null): bool
    {
        if (!$this->canTransitionTo($nuevoEstado)) {
            throw new \InvalidArgumentException("No se puede transitionar del estado {$this->estado} a {$nuevoEstado}");
        }

        $this->addTimelineEvent($nuevoEstado, $detalle, $usuario);
        return true;
    }

    /**
     * Get available transitions.
     */
    public function getAvailableTransitions(): array
    {
        $enum = $this->estado_enum;

        if (!$enum) {
            return [];
        }

        $transitions = [];

        foreach (\App\Enums\EstadoSolicitud::cases() as $estado) {
            if ($enum->canTransitionTo($estado)) {
                $transitions[] = [
                    'value' => $estado->value,
                    'label' => $estado->getLabel(),
                    'color' => $estado->getColor(),
                    'description' => $estado->getDescripcion()
                ];
            }
        }

        return $transitions;
    }

    /**
     * Check if requires action.
     */
    public function requiresAction(): bool
    {
        $enum = $this->estado_enum;
        return $enum ? $enum->requiresAction() : false;
    }

    /**
     * Check if is final state.
     */
    public function isFinalState(): bool
    {
        $enum = $this->estado_enum;
        return $enum ? $enum->isFinal() : false;
    }

    /**
     * Check if is active state.
     */
    public function isActiveState(): bool
    {
        $enum = $this->estado_enum;
        return $enum ? $enum->isActive() : false;
    }

    /**
     * Get estado metadata.
     */
    public function getEstadoMetadataAttribute(): array
    {
        $enum = $this->estado_enum;

        if (!$enum) {
            return [
                'value' => $this->estado,
                'label' => $this->estado_label,
                'color' => $this->estado_color,
                'description' => '',
                'orden' => 0,
                'is_initial' => false,
                'is_final' => false,
                'requires_action' => false,
                'is_active' => false
            ];
        }

        return [
            'value' => $enum->value,
            'label' => $enum->getLabel(),
            'color' => $enum->getColor(),
            'description' => $enum->getDescripcion(),
            'orden' => $enum->getOrden(),
            'is_initial' => $enum->getOrden() === 1,
            'is_final' => $enum->isFinal(),
            'requires_action' => $enum->requiresAction(),
            'is_active' => $enum->isActive(),
            'available_transitions' => $this->available_transitions
        ];
    }
}
