<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SolicitudCredito extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'solicitudes_credito';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'numero_solicitud';

    /**
     * The "keyType" for the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'numero_solicitud',
        'owner_username',
        'xml_filename',
        'monto_solicitado',
        'monto_aprobado',
        'plazo_meses',
        'tasa_interes',
        'destino_credito',
        'descripcion',
        'estado',
        'pdf_generado'  // Agregar campo pdf_generado
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_solicitado' => 'decimal:2',
            'monto_aprobado' => 'decimal:2',
            'plazo_meses' => 'integer',
            'tasa_interes' => 'decimal:2',
            'pdf_generado' => 'json',  // Cast para campo JSON
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the user that owns the solicitud.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'owner_username', 'username');
    }

    /**
     * Get the estado for the solicitud.
     */
    public function estado()
    {
        return $this->belongsTo(EstadoSolicitud::class, 'estado', 'codigo');
    }

    /**
     * Get the documentos for the solicitud.
     */
    public function documentos()
    {
        return $this->hasMany(SolicitudDocumento::class, 'solicitud_id');
    }

    /**
     * Get the payload for the solicitud.
     */
    public function payload()
    {
        return $this->hasOne(SolicitudPayload::class, 'solicitud_id');
    }

    /**
     * Get the timeline for the solicitud.
     */
    public function timeline()
    {
        return $this->hasMany(SolicitudTimeline::class, 'solicitud_id')->orderBy('fecha');
    }

    /**
     * Get the solicitante data for the solicitud.
     */
    public function solicitante()
    {
        return $this->hasOne(SolicitudSolicitante::class, 'solicitud_id');
    }

    /**
     * Get the firmantes for the solicitud.
     */
    public function firmantes()
    {
        return $this->hasMany(FirmanteSolicitud::class, 'solicitud_id')->orderBy('orden');
    }

    /**
     * Get the PDF generated for the solicitud.
     */
    public function pdfGenerado()
    {
        return $this->hasOne(PdfGenerado::class, 'solicitud_id');
    }

    /**
     * Get the firmantes for the solicitud.
     */
    public function solicitudFirmantes()
    {
        return $this->hasMany(FirmanteSolicitud::class, 'solicitud_id')->orderBy('orden');
    }

    /**
     * Scope to get solicitudes by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('estado', $status);
    }

    /**
     * Scope to get solicitudes by user.
     */
    public function scopeByUser($query, string $username)
    {
        return $query->where('owner_username', $username);
    }

    /**
     * Scope to get solicitudes by amount range.
     */
    public function scopeByAmountRange($query, float $min, float $max = null)
    {
        $query->where('monto_solicitado', '>=', $min);

        if ($max !== null) {
            $query->where('monto_solicitado', '<=', $max);
        }

        return $query;
    }

    /**
     * Find solicitud by number.
     */
    public static function findByNumber(string $numero): ?self
    {
        return static::where('numero_solicitud', $numero)->first();
    }

    /**
     * Check if solicitud is approved.
     */
    public function isApproved(): bool
    {
        return $this->estado === 'APROBADO';
    }

    /**
     * Check if solicitud is rejected.
     */
    public function isRejected(): bool
    {
        return $this->estado === 'RECHAZADO';
    }

    /**
     * Check if solicitud is active (not finalized).
     */
    public function isActive(): bool
    {
        $finalStates = ['FINALIZADO', 'RECHAZADO', 'DESISTE'];
        return !in_array($this->estado, $finalStates);
    }

    /**
     * Get monthly payment calculation.
     */
    public function getMonthlyPayment(): float
    {
        if (!$this->monto_aprobado || !$this->plazo_meses || !$this->tasa_interes) {
            return 0;
        }

        $principal = $this->monto_aprobado;
        $months = $this->plazo_meses;
        $monthlyRate = $this->tasa_interes / 100 / 12;

        if ($monthlyRate == 0) {
            return $principal / $months;
        }

        return $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    }

    /**
     * Get total interest calculation.
     */
    public function getTotalInterest(): float
    {
        $monthlyPayment = $this->getMonthlyPayment();
        return ($monthlyPayment * $this->plazo_meses) - $this->monto_aprobado;
    }

    /**
     * Get total to pay calculation.
     */
    public function getTotalToPay(): float
    {
        return $this->monto_aprobado + $this->getTotalInterest();
    }

    /**
     * Add timeline entry.
     */
    public function addTimelineEntry(string $estado, string $detalle, string $usuarioUsername = null, bool $automatico = false): void
    {
        $this->timeline()->create([
            'estado' => $estado,
            'detalle' => $detalle,
            'usuario_username' => $usuarioUsername,
            'automatico' => $automatico
        ]);
    }

    /**
     * Change status with timeline entry.
     */
    public function changeStatus(string $newEstado, string $detalle, string $usuarioUsername = null): bool
    {
        $oldEstado = $this->estado;

        if ($oldEstado === $newEstado) {
            return false;
        }

        $this->update(['estado' => $newEstado]);
        $this->addTimelineEntry($newEstado, $detalle, $usuarioUsername, false);

        return true;
    }

    /**
     * Get formatted amount.
     */
    public function getMontoSolicitadoFormattedAttribute(): string
    {
        return '$' . number_format($this->monto_solicitado, 2, ',', '.');
    }

    public function getMontoAprobadoFormattedAttribute(): string
    {
        return '$' . number_format($this->monto_aprobado, 2, ',', '.');
    }

    /**
     * Get status with color.
     */
    public function getStatusWithColorAttribute(): array
    {
        return [
            'codigo' => $this->estado,
            'nombre' => $this->estado?->nombre ?? $this->estado,
            'color' => $this->estado?->color ?? '#6B7280'
        ];
    }

    /**
     * Create solicitud with automatic number generation.
     */
    public static function createWithNumber(array $data): self
    {
        // Generate next solicitud number
        $numeroSolicitud = NumeroSolicitud::generateNextNumber();

        return static::create(array_merge($data, [
            'numero_solicitud' => $numeroSolicitud
        ]));
    }

    /**
     * Update estado with validation and timeline.
     */
    public function updateEstadoValidado(string $nuevoEstado, string $detalle, ?string $usuario = null): void
    {
        $estadoActual = $this->estado;

        if ($estadoActual === $nuevoEstado) {
            return; // No change needed
        }

        // Validate state transition
        $estadoActualModel = EstadoSolicitud::findByCode($estadoActual);
        $nuevoEstadoModel = EstadoSolicitud::findByCode($nuevoEstado);

        if (!$nuevoEstadoModel) {
            throw new \Exception("Estado '{$nuevoEstado}' no es válido");
        }

        if ($estadoActualModel && !$estadoActualModel->canTransitionTo($nuevoEstadoModel)) {
            throw new \Exception("No se puede transicionar del estado '{$estadoActual}' al '{$nuevoEstado}'");
        }

        // Update estado
        $this->update(['estado' => $nuevoEstado]);

        // Add timeline entry
        $this->addTimelineEntry($nuevoEstado, $detalle, $usuario, false);
    }

    /**
     * Get available transitions for current state.
     */
    public function getAvailableTransitionsAttribute(): array
    {
        $estadoActual = $this->estado;

        if (!$estadoActual) {
            return [];
        }

        return $estadoActual->getNextStates()->map(function ($estado) {
            return [
                'codigo' => $estado->codigo,
                'nombre' => $estado->nombre,
                'color' => $estado->color,
                'requires_action' => $estado->requiresAction()
            ];
        })->toArray();
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'numero_solicitud' => $this->numero_solicitud,
            'owner_username' => $this->owner_username,
            'user' => $this->user?->only(['id', 'username', 'full_name', 'email']),
            'monto_solicitado' => $this->monto_solicitado,
            'monto_solicitado_formatted' => $this->monto_solicitado_formatted,
            'monto_aprobado' => $this->monto_aprobado,
            'monto_aprobado_formatted' => $this->monto_aprobado_formatted,
            'plazo_meses' => $this->plazo_meses,
            'tasa_interes' => $this->tasa_interes,
            'destino_credito' => $this->destino_credito,
            'descripcion' => $this->descripcion,
            'estado' => $this->getStatusWithColorAttribute(),
            'xml_filename' => $this->xml_filename,
            'monthly_payment' => $this->getMonthlyPayment(),
            'total_interest' => $this->getTotalInterest(),
            'total_to_pay' => $this->getTotalToPay(),
            'is_approved' => $this->isApproved(),
            'is_rejected' => $this->isRejected(),
            'is_active' => $this->isActive(),
            'documentos_count' => $this->documentos()->where('activo', true)->count(),
            'timeline_count' => $this->timeline()->count(),
            'solicitante' => $this->solicitante,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get statistics for solicitudes.
     */
    public static function getStatistics(): array
    {
        $total = static::count();
        $byStatus = EstadoSolicitud::leftJoin('solicitudes_credito', 'estados_solicitud.codigo', '=', 'solicitudes_credito.estado')
            ->groupBy('estados_solicitud.codigo', 'estados_solicitud.nombre', 'estados_solicitud.color')
            ->selectRaw('
                estados_solicitud.codigo,
                estados_solicitud.nombre,
                estados_solicitud.color,
                COUNT(solicitudes_credito.numero_solicitud) as cantidad,
                COALESCE(SUM(solicitudes_credito.monto_solicitado), 0) as total_monto
            ')
            ->get();

        return [
            'total' => $total,
            'by_status' => $byStatus->map(function ($status) use ($total) {
                return [
                    'codigo' => $status->codigo,
                    'nombre' => $status->nombre,
                    'color' => $status->color,
                    'cantidad' => $status->cantidad,
                    'total_monto' => $status->total_monto,
                    'porcentaje' => $total > 0 ? round(($status->cantidad / $total) * 100, 2) : 0
                ];
            })
        ];
    }
}
