<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanApplication extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'loan_applications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'numero_solicitud',
        'owner_username',
        'monto_solicitado',
        'monto_aprobado',
        'plazo_meses',
        'tasa_interes',
        'destino_credito',
        'descripcion',
        'estado',
        'solicitante',
        'documentos_adjuntos',
        'timeline',
        'xml_filename',
        'payload',
        'aprobado_por',
        'fecha_aprobacion',
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
            'monto_solicitado' => 'float',
            'monto_aprobado' => 'float',
            'plazo_meses' => 'integer',
            'tasa_interes' => 'float',
            'estado' => 'string',
            'solicitante' => 'array',
            'documentos_adjuntos' => 'array',
            'timeline' => 'array',
            'payload' => 'array',
            'fecha_aprobacion' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'observaciones' => 'string'
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
     * Estados posibles para la loan application
     */
    const ESTADOS = [
        'DRAFT' => 'Borrador',
        'SUBMITTED' => 'Enviada',
        'UNDER_REVIEW' => 'En Revisión',
        'PRE_APPROVED' => 'Pre-Aprobada',
        'APPROVED' => 'Aprobada',
        'REJECTED' => 'Rechazada',
        'CANCELLED' => 'Cancelada',
        'DISBURSED' => 'Desembolsada'
    ];

    /**
     * Find by numero solicitud.
     */
    public static function findByNumeroSolicitud(string $numeroSolicitud): ?self
    {
        return static::where('numero_solicitud', $numeroSolicitud)->first();
    }

    /**
     * Find by owner username.
     */
    public static function findByOwner(string $username): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('owner_username', $username)->get();
    }

    /**
     * Add document adjunto.
     */
    public function addDocumentoAdjunto(array $documento): void
    {
        $documentos = $this->documentos_adjuntos ?? [];
        $documentos[] = array_merge($documento, [
            'fecha_subida' => now()->toISOString()
        ]);

        $this->documentos_adjuntos = $documentos;
        $this->save();
    }

    /**
     * Remove documento adjunto by name.
     */
    public function removeDocumentoAdjunto(string $nombre): bool
    {
        $documentos = $this->documentos_adjuntos ?? [];

        $filtered = array_filter($documentos, function ($doc) use ($nombre) {
            return ($doc['nombre'] ?? '') !== $nombre;
        });

        if (count($filtered) !== count($documentos)) {
            $this->documentos_adjuntos = array_values($filtered);
            $this->save();
            return true;
        }

        return false;
    }

    /**
     * Add timeline event.
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
     * Approve application.
     */
    public function approve(float $montoAprobado, float $tasaInteres, string $aprobadoPor, string $observaciones = ''): void
    {
        $this->update([
            'estado' => 'APPROVED',
            'monto_aprobado' => $montoAprobado,
            'tasa_interes' => $tasaInteres,
            'aprobado_por' => $aprobadoPor,
            'fecha_aprobacion' => now(),
            'observaciones' => $observaciones
        ]);

        $this->addTimelineEvent('APPROVED', 'Solicitud aprobada por ' . $aprobadoPor, $aprobadoPor);
    }

    /**
     * Reject application.
     */
    public function reject(string $rechazadoPor, string $motivo): void
    {
        $this->update([
            'estado' => 'REJECTED',
            'observaciones' => $motivo
        ]);

        $this->addTimelineEvent('REJECTED', 'Solicitud rechazada: ' . $motivo, $rechazadoPor);
    }

    /**
     * Get estado label.
     */
    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
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
     * Get formatted tasa interes.
     */
    public function getTasaInteresFormattedAttribute(): ?string
    {
        if ($this->tasa_interes) {
            return number_format($this->tasa_interes, 2, ',', '.') . '%';
        }
        return null;
    }

    /**
     * Get solicitante full name.
     */
    public function getSolicitanteFullNameAttribute(): string
    {
        return $this->solicitante['nombres_apellidos'] ?? 'N/A';
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
        return $this->solicitante['telefono_movil'] ?? 'N/A';
    }

    /**
     * Get solicitante document.
     */
    public function getSolicitanteDocumentAttribute(): string
    {
        $tipo = $this->solicitante['tipo_identificacion'] ?? '';
        $numero = $this->solicitante['numero_identificacion'] ?? '';
        return $tipo . ' ' . $numero;
    }

    /**
     * Get documents count.
     */
    public function getDocumentosCountAttribute(): int
    {
        return count($this->documentos_adjuntos ?? []);
    }

    /**
     * Get total documents size.
     */
    public function getTotalDocumentsSizeAttribute(): int
    {
        $total = 0;
        foreach ($this->documentos_adjuntos ?? [] as $doc) {
            $total += $doc['tamano'] ?? 0;
        }
        return $total;
    }

    /**
     * Get formatted total size.
     */
    public function getFormattedTotalSizeAttribute(): string
    {
        $bytes = $this->total_documents_size;

        if ($bytes < 1024) {
            return $bytes . ' bytes';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / 1048576, 2) . ' MB';
        }
    }

    /**
     * Check if can be modified.
     */
    public function canBeModified(): bool
    {
        return !in_array($this->estado, ['APPROVED', 'REJECTED', 'DISBURSED', 'CANCELLED']);
    }

    /**
     * Check if is approved.
     */
    public function isApproved(): bool
    {
        return $this->estado === 'APPROVED';
    }

    /**
     * Check if is rejected.
     */
    public function isRejected(): bool
    {
        return $this->estado === 'REJECTED';
    }

    /**
     * Check if is disbursed.
     */
    public function isDisbursed(): bool
    {
        return $this->estado === 'DISBURSED';
    }

    /**
     * Scope by estado.
     */
    public function scopeByEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope by owner.
     */
    public function scopeByOwner($query, string $username)
    {
        return $query->where('owner_username', $username);
    }

    /**
     * Scope pending review.
     */
    public function scopePendingReview($query)
    {
        return $query->whereIn('estado', ['SUBMITTED', 'UNDER_REVIEW']);
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
     * Calculate monthly payment.
     */
    public function calculateMonthlyPayment(): float
    {
        if (!$this->monto_aprobado || !$this->tasa_interes || !$this->plazo_meses) {
            return 0;
        }

        $principal = $this->monto_aprobado;
        $monthlyRate = $this->tasa_interes / 100 / 12;
        $months = $this->plazo_meses;

        if ($monthlyRate == 0) {
            return $principal / $months;
        }

        return $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    }

    /**
     * Get formatted monthly payment.
     */
    public function getFormattedMonthlyPaymentAttribute(): string
    {
        return '$' . number_format($this->calculateMonthlyPayment(), 2, ',', '.');
    }

    /**
     * Get total interest.
     */
    public function getTotalInterest(): float
    {
        $monthlyPayment = $this->calculateMonthlyPayment();
        return ($monthlyPayment * $this->plazo_meses) - $this->monto_aprobado;
    }

    /**
     * Get formatted total interest.
     */
    public function getFormattedTotalInterestAttribute(): string
    {
        return '$' . number_format($this->getTotalInterest(), 2, ',', '.');
    }
}
