<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudTimeline extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'solicitud_timeline';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'solicitud_id',
        'estado_codigo',
        'detalle',
        'usuario_username',
        'automatico',
        'fecha'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'automatico' => 'boolean',
            'fecha' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the solicitud that owns the timeline entry.
     */
    public function solicitud()
    {
        return $this->belongsTo(SolicitudCredito::class, 'solicitud_id');
    }

    /**
     * Get the estado for the timeline entry.
     */
    public function estado()
    {
        return $this->belongsTo(EstadoSolicitud::class, 'estado_codigo', 'codigo');
    }

    /**
     * Get the user that made the change.
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_username', 'username');
    }

    /**
     * Scope to get manual entries (not automatic).
     */
    public function scopeManual($query)
    {
        return $query->where('automatico', false);
    }

    /**
     * Scope to get automatic entries.
     */
    public function scopeAutomatic($query)
    {
        return $query->where('automatico', true);
    }

    /**
     * Scope to get entries by user.
     */
    public function scopeByUser($query, string $username)
    {
        return $query->where('usuario_username', $username);
    }

    /**
     * Scope to get entries by state.
     */
    public function scopeByState($query, string $estadoCodigo)
    {
        return $query->where('estado_codigo', $estadoCodigo);
    }

    /**
     * Scope to get entries in date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('fecha', [$startDate, $endDate]);
    }

    /**
     * Get formatted date.
     */
    public function getFechaFormattedAttribute(): string
    {
        return $this->fecha->format('d/m/Y H:i:s');
    }

    /**
     * Get relative time (e.g., "hace 2 horas").
     */
    public function getTiempoRelativoAttribute(): string
    {
        return $this->fecha->diffForHumans();
    }

    /**
     * Get status with color.
     */
    public function getEstadoConColorAttribute(): array
    {
        return [
            'codigo' => $this->estado_codigo,
            'nombre' => $this->estado?->nombre ?? $this->estado_codigo,
            'color' => $this->estado?->color ?? '#6B7280'
        ];
    }

    /**
     * Check if entry was made by system.
     */
    public function esAutomatico(): bool
    {
        return $this->automatico;
    }

    /**
     * Check if entry was made by user.
     */
    public function esManual(): bool
    {
        return !$this->automatico;
    }

    /**
     * Get entry type label.
     */
    public function getTipoEtiquetaAttribute(): string
    {
        return $this->automatico ? 'Automático' : 'Manual';
    }

    /**
     * Get entry type color.
     */
    public function getTipoColorAttribute(): string
    {
        return $this->automatico ? '#10B981' : '#3B82F6';
    }

    /**
     * Create timeline entry.
     */
    public static function crearEntrada(
        int $solicitudId,
        string $estadoCodigo,
        string $detalle,
        ?string $usuarioUsername = null,
        bool $automatico = false
    ): self {
        return static::create([
            'solicitud_id' => $solicitudId,
            'estado_codigo' => $estadoCodigo,
            'detalle' => $detalle,
            'usuario_username' => $usuarioUsername,
            'automatico' => $automatico,
            'fecha' => now()
        ]);
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'estado' => $this->estado_con_color,
            'detalle' => $this->detalle,
            'usuario' => $this->usuario ? [
                'username' => $this->usuario->username,
                'full_name' => $this->usuario->full_name
            ] : null,
            'automatico' => $this->automatico,
            'tipo_etiqueta' => $this->tipo_etiqueta,
            'tipo_color' => $this->tipo_color,
            'fecha' => $this->fecha->toISOString(),
            'fecha_formatted' => $this->fecha_formatted,
            'tiempo_relativo' => $this->tiempo_relativo,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get timeline for solicitud.
     */
    public static function obtenerTimelineSolicitud(int $solicitudId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('solicitud_id', $solicitudId)
            ->with(['estado', 'usuario'])
            ->orderBy('fecha', 'desc')
            ->get();
    }

    /**
     * Get latest entry for solicitud.
     */
    public static function obtenerUltimaEntrada(int $solicitudId): ?self
    {
        return static::where('solicitud_id', $solicitudId)
            ->orderBy('fecha', 'desc')
            ->first();
    }

    /**
     * Get entries count by state for solicitud.
     */
    public static function obtenerConteoPorEstado(int $solicitudId): array
    {
        return static::where('solicitud_id', $solicitudId)
            ->join('estados_solicitud', 'solicitud_timeline.estado_codigo', '=', 'estados_solicitud.codigo')
            ->groupBy('estados_solicitud.codigo', 'estados_solicitud.nombre', 'estados_solicitud.color')
            ->selectRaw('
                estados_solicitud.codigo,
                estados_solicitud.nombre,
                estados_solicitud.color,
                COUNT(*) as cantidad,
                MAX(solicitud_timeline.fecha) as ultima_vez
            ')
            ->orderBy('ultima_vez', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'estado' => [
                        'codigo' => $item->codigo,
                        'nombre' => $item->nombre,
                        'color' => $item->color
                    ],
                    'cantidad' => $item->cantidad,
                    'ultima_vez' => $item->ultima_vez
                ];
            })
            ->toArray();
    }
}
