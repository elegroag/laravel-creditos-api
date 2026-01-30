<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Postulacion extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'postulaciones';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'tipo_postulante',
        'empresa_nit',
        'empresa_razon_social',
        'datos_personales',
        'datos_laborales',
        'datos_financieros',
        'estado',
        'observaciones'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'datos_personales' => 'json',
            'datos_laborales' => 'json',
            'datos_financieros' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime'
        ];
    }

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
     * Scope by monto range.
     */
    public function scopeByMontoRange($query, float $min, float $max)
    {
        return $query->whereBetween('monto_solicitado', [$min, $max]);
    }

    /**
     * Scope by plazo range.
     */
    public function scopeByPlazoRange($query, int $min, int $max)
    {
        return $query->whereBetween('plazo_meses', [$min, $max]);
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

    /**
     * Get postulacion age in days.
     */
    public function getAgeInDaysAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Check if is recent (less than 7 days).
     */
    public function isRecent(): bool
    {
        return $this->age_in_days < 7;
    }

    /**
     * Get estado with color for UI.
     */
    public function getEstadoWithColorAttribute(): array
    {
        $colors = [
            'POSTULADO' => '#3B82F6',
            'EN_REVISION' => '#F59E0B',
            'APROBADO' => '#10B981',
            'RECHAZADO' => '#EF4444',
            'EN_ESTUDIO' => '#8B5CF6',
            'PRE_APROBADO' => '#06B6D4'
        ];

        return [
            'estado' => $this->estado,
            'label' => $this->estado_label,
            'color' => $colors[$this->estado] ?? '#6B7280'
        ];
    }

    /**
     * Get monthly payment estimate.
     */
    public function getMonthlyPaymentEstimateAttribute(): float
    {
        // Simple calculation: 1% monthly interest rate
        $monthlyRate = 0.01;
        $months = $this->plazo_meses;
        $principal = $this->monto_solicitado;

        if ($months > 0) {
            return $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
        }

        return 0;
    }

    /**
     * Get formatted monthly payment.
     */
    public function getMonthlyPaymentFormattedAttribute(): string
    {
        return '$' . number_format($this->monthly_payment_estimate, 2, ',', '.');
    }

    /**
     * Validate postulacion data.
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->monto_solicitado <= 0) {
            $errors[] = 'El monto solicitado debe ser mayor a 0';
        }

        if ($this->plazo_meses <= 0 || $this->plazo_meses > 120) {
            $errors[] = 'El plazo debe estar entre 1 y 120 meses';
        }

        if (empty($this->descripcion)) {
            $errors[] = 'La descripción es requerida';
        }

        if (!in_array($this->estado, array_keys(self::ESTADOS))) {
            $errors[] = 'El estado no es válido';
        }

        return $errors;
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'solicitante' => $this->solicitante,
            'solicitante_full_name' => $this->solicitante_full_name,
            'solicitante_document' => $this->solicitante_document,
            'solicitante_email' => $this->solicitante_email,
            'solicitante_phone' => $this->solicitante_phone,
            'monto_solicitado' => $this->monto_solicitado,
            'monto_solicitado_formatted' => $this->monto_solicitado_formatted,
            'plazo_meses' => $this->plazo_meses,
            'plazo_formatted' => $this->plazo_formatted,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado,
            'estado_label' => $this->estado_label,
            'estado_with_color' => $this->estado_with_color,
            'timeline' => $this->timeline,
            'timeline_count' => $this->timeline_count,
            'latest_timeline_event' => $this->latest_timeline_event,
            'can_be_modified' => $this->canBeModified(),
            'monthly_payment_estimate' => $this->monthly_payment_estimate,
            'monthly_payment_formatted' => $this->monthly_payment_formatted,
            'age_in_days' => $this->age_in_days,
            'is_recent' => $this->isRecent(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get all postulaciones for API.
     */
    public static function getAllForApi(): array
    {
        return static::orderBy('created_at', 'desc')
            ->get()
            ->map(function ($postulacion) {
                return $postulacion->toApiArray();
            })
            ->toArray();
    }

    /**
     * Get postulaciones by estado for API.
     */
    public static function getByEstadoForApi(string $estado): array
    {
        return static::where('estado', $estado)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($postulacion) {
                return $postulacion->toApiArray();
            })
            ->toArray();
    }

    /**
     * Create new postulacion.
     */
    public static function createNew(array $solicitante, float $monto, int $plazo, string $descripcion): self
    {
        return static::create([
            'solicitante' => $solicitante,
            'monto_solicitado' => $monto,
            'plazo_meses' => $plazo,
            'descripcion' => $descripcion,
            'estado' => 'POSTULADO',
            'timeline' => [[
                'estado' => 'POSTULADO',
                'fecha' => now()->toISOString(),
                'detalle' => 'Postulación creada',
                'usuario' => null
            ]]
        ]);
    }

    /**
     * Get statistics by estado.
     */
    public static function getStatisticsByEstado(): array
    {
        $stats = [];

        foreach (array_keys(self::ESTADOS) as $estado) {
            $count = static::where('estado', $estado)->count();
            $totalMonto = static::where('estado', $estado)->sum('monto_solicitado');

            $stats[$estado] = [
                'estado' => $estado,
                'label' => self::ESTADOS[$estado],
                'count' => $count,
                'total_monto' => $totalMonto,
                'total_monto_formatted' => '$' . number_format($totalMonto, 2, ',', '.'),
                'average_monto' => $count > 0 ? $totalMonto / $count : 0,
                'average_monto_formatted' => $count > 0 ? '$' . number_format($totalMonto / $count, 2, ',', '.') : '$0'
            ];
        }

        return $stats;
    }
}
