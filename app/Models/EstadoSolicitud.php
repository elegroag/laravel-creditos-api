<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstadoSolicitud extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'estados_solicitud';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'codigo',
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Scope to get only active states.
     */
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope to get states ordered by order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('orden');
    }

    /**
     * Find state by code.
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('codigo', $code)->first();
    }

    /**
     * Get solicitudes with this state.
     */
    public function solicitudes()
    {
        return $this->hasMany(SolicitudCredito::class, 'estado_codigo', 'codigo');
    }

    /**
     * Get timeline entries with this state.
     */
    public function timelineEntries()
    {
        return $this->hasMany(SolicitudTimeline::class, 'estado_codigo', 'codigo');
    }

    /**
     * Get initial state.
     */
    public static function getInitialState(): ?self
    {
        return static::active()->orderBy('orden')->first();
    }

    /**
     * Get final states.
     */
    public static function getFinalStates(): \Illuminate\Database\Eloquent\Collection
    {
        $finalStateCodes = ['FINALIZADO', 'RECHAZADO', 'DESISTE'];
        return static::whereIn('codigo', $finalStateCodes)->active()->get();
    }

    /**
     * Get active states.
     */
    public static function getActiveStates(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()->ordered()->get();
    }

    /**
     * Check if this is an initial state.
     */
    public function isInitial(): bool
    {
        return $this->orden === 1;
    }

    /**
     * Check if this is a final state.
     */
    public function isFinal(): bool
    {
        $finalStateCodes = ['FINALIZADO', 'RECHAZADO', 'DESISTE'];
        return in_array($this->codigo, $finalStateCodes);
    }

    /**
     * Check if this state requires action.
     */
    public function requiresAction(): bool
    {
        $actionStates = ['ENVIADO_VALIDACION', 'REQUIRE_CORRECCION'];
        return in_array($this->codigo, $actionStates);
    }

    /**
     * Check if this state is active in the flow.
     */
    public function isActive(): bool
    {
        $inactiveStates = ['FINALIZADO', 'RECHAZADO', 'DESISTE'];
        return !in_array($this->codigo, $inactiveStates);
    }

    /**
     * Get next possible states.
     */
    public function getNextStates(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->where('orden', '>', $this->orden)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Get previous possible states.
     */
    public function getPreviousStates(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->where('orden', '<', $this->orden)
            ->orderBy('orden', 'desc')
            ->get();
    }

    /**
     * Check if can transition to another state.
     */
    public function canTransitionTo(self $targetState): bool
    {
        // Can always transition to the same state
        if ($this->codigo === $targetState->codigo) {
            return true;
        }

        // Final states cannot transition to other states
        if ($this->isFinal()) {
            return false;
        }

        // Can transition to any state with higher order
        return $targetState->orden > $this->orden;
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'orden' => $this->orden,
            'color' => $this->color,
            'activo' => $this->activo,
            'is_initial' => $this->isInitial(),
            'is_final' => $this->isFinal(),
            'requires_action' => $this->requiresAction(),
            'is_active' => $this->isActive(),
            'next_states' => $this->getNextStates()->map(function ($state) {
                return [
                    'codigo' => $state->codigo,
                    'nombre' => $state->nombre,
                    'color' => $state->color
                ];
            }),
            'previous_states' => $this->getPreviousStates()->map(function ($state) {
                return [
                    'codigo' => $state->codigo,
                    'nombre' => $state->nombre,
                    'color' => $state->color
                ];
            }),
            'solicitudes_count' => $this->solicitudes()->count(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }
}
