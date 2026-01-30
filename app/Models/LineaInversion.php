<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineaInversion extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lineas_inversion';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'linea_credito',
        'monto_maximo_pesos',
        'plazo_maximo',
        'tasas_interes_anual',
        'requisitos',
        'categoria',
        'descripcion',
        'estado'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_maximo_pesos' => 'decimal:2',
            'tasas_interes_anual' => 'json',
            'requisitos' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get solicitudes that use this investment line.
     */
    public function solicitudes()
    {
        return $this->hasMany(SolicitudCredito::class, 'linea_inversion_id');
    }

    /**
     * Scope to get active lines.
     */
    public function scopeActive($query)
    {
        return $query->where('estado', 'Activo');
    }

    /**
     * Scope to get lines by category.
     */
    public function scopeByCategory($query, string $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    /**
     * Scope to get lines ordered by max amount.
     */
    public function scopeOrderByMaxAmount($query, $direction = 'desc')
    {
        return $query->orderBy('monto_maximo_pesos', $direction);
    }

    /**
     * Find line by name.
     */
    public static function findByLinea(string $linea): ?self
    {
        return static::where('linea_credito', $linea)->first();
    }

    /**
     * Get formatted max amount.
     */
    public function getMontoMaximoFormattedAttribute(): string
    {
        return '$' . number_format($this->monto_maximo_pesos, 2, ',', '.');
    }

    /**
     * Get interest rates as array.
     */
    public function getTasasArray(): array
    {
        return $this->tasas_interes_anual ?? [];
    }

    /**
     * Get interest rate for specific category.
     */
    public function getTasaInteres(string $categoria): ?float
    {
        $tasas = $this->tasas_array;
        if (isset($tasas[$categoria])) {
            // Remove % sign and convert to decimal
            $rate = str_replace('%', '', $tasas[$categoria]);
            return (float) $rate;
        }
        return null;
    }

    /**
     * Get requirements as array.
     */
    public function getRequisitosArray(): array
    {
        return $this->requisitos ?? [];
    }

    /**
     * Check if line is active.
     */
    public function isActive(): bool
    {
        return $this->estado === 'Activo';
    }

    /**
     * Check if line is inactive.
     */
    public function isInactive(): bool
    {
        return $this->estado === 'Inactivo';
    }

    /**
     * Activate line.
     */
    public function activate(): void
    {
        $this->estado = 'Activo';
        $this->save();
    }

    /**
     * Deactivate line.
     */
    public function deactivate(): void
    {
        $this->estado = 'Inactivo';
        $this->save();
    }

    /**
     * Check if amount is within limit.
     */
    public function montoPermitido(float $monto): bool
    {
        return $monto <= $this->monto_maximo_pesos;
    }

    /**
     * Get available categories for interest rates.
     */
    public function getCategoriasDisponibles(): array
    {
        return array_keys($this->tasas_array);
    }

    /**
     * Get requirements count.
     */
    public function getCantidadRequisitosAttribute(): int
    {
        return count($this->requisitos_array);
    }

    /**
     * Get solicitudes count for this line.
     */
    public function getCantidadSolicitudesAttribute(): int
    {
        return $this->solicitudes()->count();
    }

    /**
     * Get approved solicitudes count.
     */
    public function getCantidadAprobadasAttribute(): int
    {
        return $this->solicitudes()
            ->where('estado_codigo', 'APROBADO')
            ->count();
    }

    /**
     * Get total amount of approved credits.
     */
    public function getMontoTotalAprobadoAttribute(): float
    {
        return $this->solicitudes()
            ->where('estado_codigo', 'APROBADO')
            ->sum('monto_aprobado') ?? 0;
    }

    /**
     * Get utilization percentage.
     */
    public function getPorcentajeUtilizacionAttribute(): float
    {
        $totalAprobado = $this->monto_total_aprobado;
        if ($this->monto_maximo_pesos == 0) {
            return 0;
        }

        return round(($totalAprobado / $this->monto_maximo_pesos) * 100, 2);
    }

    /**
     * Get available amount.
     */
    public function getMontoDisponibleAttribute(): float
    {
        return max(0, $this->monto_maximo_pesos - $this->monto_total_aprobado);
    }

    /**
     * Get formatted available amount.
     */
    public function getMontoDisponibleFormateadoAttribute(): string
    {
        return '$' . number_format($this->monto_disponible, 2, ',', '.');
    }

    /**
     * Check if line has available credit.
     */
    public function tieneCreditoDisponible(): bool
    {
        return $this->monto_disponible > 0;
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'linea_credito' => $this->linea_credito,
            'categoria' => $this->categoria,
            'descripcion' => $this->descripcion,
            'monto_maximo_pesos' => $this->monto_maximo_pesos,
            'monto_maximo_formateado' => $this->monto_maximo_formateado,
            'plazo_maximo' => $this->plazo_maximo,
            'tasas_interes_anual' => $this->tasas_array,
            'requisitos' => $this->requisitos_array,
            'cantidad_requisitos' => $this->cantidad_requisitos,
            'categorias_disponibles' => $this->getCategoriasDisponibles(),
            'estado' => $this->estado,
            'is_active' => $this->isActive(),
            'estadisticas' => [
                'cantidad_solicitudes' => $this->cantidad_solicitudes,
                'cantidad_aprobadas' => $this->cantidad_aprobadas,
                'monto_total_aprobado' => $this->monto_total_aprobado,
                'monto_disponible' => $this->monto_disponible,
                'monto_disponible_formateado' => $this->monto_disponible_formateado,
                'porcentaje_utilizacion' => $this->porcentaje_utilizacion,
                'tiene_credito_disponible' => $this->tieneCreditoDisponible()
            ],
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get all active lines for select.
     */
    public static function getForSelect(): array
    {
        return static::active()
            ->orderBy('linea_credito')
            ->get()
            ->mapWithKeys(function ($linea) {
                return [$linea->id => $linea->linea_credito];
            })
            ->toArray();
    }

    /**
     * Get lines by category for API.
     */
    public static function getByCategoryForApi(string $categoria): array
    {
        return static::active()
            ->byCategory($categoria)
            ->orderBy('monto_maximo_pesos', 'desc')
            ->get()
            ->map(function ($linea) {
                return [
                    'id' => $linea->id,
                    'linea_credito' => $linea->linea_credito,
                    'monto_maximo_formateado' => $linea->monto_maximo_formateado,
                    'plazo_maximo' => $linea->plazo_maximo,
                    'cantidad_requisitos' => $linea->cantidad_requisitos,
                    'tiene_credito_disponible' => $linea->tieneCreditoDisponible()
                ];
            })
            ->toArray();
    }

    /**
     * Get statistics summary for all lines.
     */
    public static function getEstadisticasGenerales(): array
    {
        $lineas = static::active()->get();

        $totalLineas = $lineas->count();
        $montoMaximoTotal = $lineas->sum('monto_maximo_pesos');
        $montoAprobadoTotal = $lineas->sum(function ($linea) {
            return $linea->monto_total_aprobado;
        });
        $solicitudesTotal = $lineas->sum(function ($linea) {
            return $linea->cantidad_solicitudes;
        });

        return [
            'total_lineas' => $totalLineas,
            'monto_maximo_total' => $montoMaximoTotal,
            'monto_aprobado_total' => $montoAprobadoTotal,
            'monto_disponible_total' => $montoMaximoTotal - $montoAprobadoTotal,
            'solicitudes_total' => $solicitudesTotal,
            'porcentaje_utilizacion_general' => $montoMaximoTotal > 0
                ? round(($montoAprobadoTotal / $montoMaximoTotal) * 100, 2)
                : 0
        ];
    }
}
