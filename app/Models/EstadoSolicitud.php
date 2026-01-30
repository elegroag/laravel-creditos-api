<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Enums\EstadoSolicitud as EstadoSolicitudEnum;

class EstadoSolicitud extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'estados_solicitud';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'nombre',
        'descripcion',
        'orden',
        'color',
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
            'orden' => 'integer',
            'activo' => 'boolean',
            'color' => 'string'
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
     * Find by id.
     */
    public static function findById(string $id): ?self
    {
        return static::where('id', $id)->first();
    }

    /**
     * Find by nombre.
     */
    public static function findByNombre(string $nombre): ?self
    {
        return static::where('nombre', $nombre)->first();
    }

    /**
     * Get active states ordered by orden.
     */
    public static function getActiveOrdered(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('activo', true)->orderBy('orden', 'asc')->get();
    }

    /**
     * Get all states ordered by orden.
     */
    public static function getAllOrdered(): \Illuminate\Database\Eloquent\Collection
    {
        return static::orderBy('orden', 'asc')->get();
    }

    /**
     * Get next state in order.
     */
    public function getNextState(): ?self
    {
        return static::where('orden', '>', $this->orden)
                    ->where('activo', true)
                    ->orderBy('orden', 'asc')
                    ->first();
    }

    /**
     * Get previous state in order.
     */
    public function getPreviousState(): ?self
    {
        return static::where('orden', '<', $this->orden)
                    ->where('activo', true)
                    ->orderBy('orden', 'desc')
                    ->first();
    }

    /**
     * Check if can transition to another state.
     */
    public function canTransitionTo(string $targetStateId): bool
    {
        try {
            $currentStateEnum = EstadoSolicitudEnum::from($this->id);
            $targetStateEnum = EstadoSolicitudEnum::from($targetStateId);
            
            return $currentStateEnum->canTransitionTo($targetStateEnum);
        } catch (\ValueError $e) {
            return false;
        }
    }

    /**
     * Get enum instance.
     */
    public function getEnum(): ?EstadoSolicitudEnum
    {
        try {
            return EstadoSolicitudEnum::from($this->id);
        } catch (\ValueError $e) {
            return null;
        }
    }

    /**
     * Check if is initial state.
     */
    public function isInitialState(): bool
    {
        return $this->orden === 1;
    }

    /**
     * Check if is final state.
     */
    public function isFinalState(): bool
    {
        return in_array($this->id, [
            EstadoSolicitudEnum::FINALIZADO->value,
            EstadoSolicitudEnum::RECHAZADO->value,
            EstadoSolicitudEnum::DESISTE->value
        ]);
    }

    /**
     * Check if requires action.
     */
    public function requiresAction(): bool
    {
        return in_array($this->id, [
            EstadoSolicitudEnum::REQUIRE_CORRECCION->value,
            EstadoSolicitudEnum::PENDIENTE_FIRMADO->value,
            EstadoSolicitudEnum::ENVIADO_VALIDACION->value
        ]);
    }

    /**
     * Check if is active.
     */
    public function isActive(): bool
    {
        return $this->activo === true;
    }

    /**
     * Scope active.
     */
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope by orden.
     */
    public function scopeByOrden($query, int $orden)
    {
        return $query->where('orden', $orden);
    }

    /**
     * Scope final states.
     */
    public function scopeFinal($query)
    {
        return $query->whereIn('id', [
            EstadoSolicitudEnum::FINALIZADO->value,
            EstadoSolicitudEnum::RECHAZADO->value,
            EstadoSolicitudEnum::DESISTE->value
        ]);
    }

    /**
     * Activate state.
     */
    public function activate(): void
    {
        $this->activo = true;
        $this->save();
    }

    /**
     * Deactivate state.
     */
    public function deactivate(): void
    {
        $this->activo = false;
        $this->save();
    }

    /**
     * Get formatted color with #.
     */
    public function getFormattedColorAttribute(): string
    {
        $color = $this->color;
        
        // Ensure color starts with #
        if (!str_starts_with($color, '#')) {
            $color = '#' . $color;
        }
        
        return $color;
    }

    /**
     * Get text color based on background.
     */
    public function getTextColorAttribute(): string
    {
        // Simple logic to determine if text should be white or black based on background
        $color = $this->formatted_color;
        
        // Convert hex to RGB
        $hex = str_replace('#', '', $color);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Calculate luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        
        return $luminance > 0.5 ? 'black' : 'white';
    }

    /**
     * Get state type category.
     */
    public function getCategoryAttribute(): string
    {
        return match($this->id) {
            EstadoSolicitudEnum::POSTULADO->value => 'Inicial',
            EstadoSolicitudEnum::DOCUMENTOS_CARGADOS->value => 'Proceso',
            EstadoSolicitudEnum::ENVIADO_VALIDACION->value => 'Validación',
            EstadoSolicitudEnum::PENDIENTE_FIRMADO->value => 'Firma',
            EstadoSolicitudEnum::FIRMADO->value => 'Firma',
            EstadoSolicitudEnum::ENVIADO_PENDIENTE_APROBACION->value => 'Aprobación',
            EstadoSolicitudEnum::APROBADO->value => 'Aprobado',
            EstadoSolicitudEnum::DESEMBOLSADO->value => 'Desembolso',
            EstadoSolicitudEnum::FINALIZADO->value => 'Final',
            EstadoSolicitudEnum::RECHAZADO->value => 'Rechazado',
            EstadoSolicitudEnum::DESISTE->value => 'Cancelado',
            EstadoSolicitudEnum::REQUIRE_CORRECCION->value => 'Corrección',
            default => 'General'
        };
    }

    /**
     * Get transition states.
     */
    public function getTransitionStatesAttribute(): \Illuminate\Database\Eloquent\Collection
    {
        $enum = $this->getEnum();
        
        if (!$enum) {
            return collect([]);
        }
        
        $targetStates = [];
        
        foreach (EstadoSolicitudEnum::cases() as $state) {
            if ($enum->canTransitionTo($state)) {
                $targetStates[] = $state->value;
            }
        }
        
        return static::whereIn('id', $targetStates)->active()->get();
    }

    /**
     * Initialize states from enum.
     */
    public static function initializeFromEnum(): void
    {
        foreach (EstadoSolicitudEnum::cases() as $estado) {
            self::updateOrCreate(
                ['id' => $estado->value],
                [
                    'nombre' => $estado->getLabel(),
                    'descripcion' => $estado->getDescripcion(),
                    'orden' => $estado->getOrden(),
                    'color' => $estado->getColor(),
                    'activo' => true
                ]
            );
        }
    }

    /**
     * Get states for select options.
     */
    public static function getForSelect(): array
    {
        return self::active()->orderBy('orden', 'asc')->get()->mapWithKeys(function ($estado) {
            return [$estado->id => $estado->nombre];
        })->toArray();
    }

    /**
     * Get states with full data for API.
     */
    public static function getForApi(): array
    {
        return self::active()->orderBy('orden', 'asc')->get()->map(function ($estado) {
            return [
                'id' => $estado->id,
                'nombre' => $estado->nombre,
                'descripcion' => $estado->descripcion,
                'orden' => $estado->orden,
                'color' => $estado->formatted_color,
                'text_color' => $estado->text_color,
                'category' => $estado->category,
                'is_initial' => $estado->is_initial_state,
                'is_final' => $estado->is_final_state,
                'requires_action' => $estado->requires_action,
                'transitions' => $estado->transition_states->pluck('id')
            ];
        })->toArray();
    }
}
