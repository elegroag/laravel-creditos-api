<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntidadDigital extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entidad_digital';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'tipo_identificacion',
        'numero_identificacion',
        'documentos',
        'selfie',
        'clave_firma_hash',
        'estado',
        'metadata',
        'validaciones',
        'last_validation_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'documentos' => 'json',
            'metadata' => 'json',
            'validaciones' => 'json',
            'last_validation_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the user that owns the entidad digital.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    /**
     * Scope to get entities by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('estado', $status);
    }

    /**
     * Scope to get entities by identification type.
     */
    public function scopeByIdType($query, string $idType)
    {
        return $query->where('tipo_identificacion', $idType);
    }

    /**
     * Scope to get entities by identification number.
     */
    public function scopeByIdNumber($query, string $idNumber)
    {
        return $query->where('numero_identificacion', $idNumber);
    }

    /**
     * Find entity by username.
     */
    public static function findByUsername(string $username): ?self
    {
        return static::where('username', $username)->first();
    }

    /**
     * Find entity by identification.
     */
    public static function findByIdentification(string $tipo, string $numero): ?self
    {
        return static::where('tipo_identificacion', $tipo)
            ->where('numero_identificacion', $numero)
            ->first();
    }

    /**
     * Check if entity is verified.
     */
    public function isVerified(): bool
    {
        return $this->estado === 'VERIFICADO';
    }

    /**
     * Check if entity is pending verification.
     */
    public function isPending(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    /**
     * Check if entity is rejected.
     */
    public function isRejected(): bool
    {
        return $this->estado === 'RECHAZADO';
    }

    /**
     * Get document paths.
     */
    public function getDocumentPaths(): array
    {
        return $this->documentos ?? [];
    }

    /**
     * Get selfie path.
     */
    public function getSelfiePath(): ?string
    {
        return $this->selfie;
    }

    /**
     * Check if has all required documents.
     */
    public function hasRequiredDocuments(): bool
    {
        $documentos = $this->getDocumentPaths();
        return !empty($documentos['frente']) && !empty($documentos['reverso']) && !empty($this->selfie);
    }

    /**
     * Add document path.
     */
    public function addDocument(string $type, string $path): void
    {
        $documentos = $this->documentos ?? [];
        $documentos[$type] = $path;
        $this->documentos = $documentos;
        $this->save();
    }

    /**
     * Add selfie path.
     */
    public function addSelfie(string $path): void
    {
        $this->selfie = $path;
        $this->save();
    }

    /**
     * Add validation record.
     */
    public function addValidation(array $validationData): void
    {
        $validaciones = $this->validaciones ?? [];
        $validaciones[] = array_merge($validationData, [
            'fecha' => now()->toISOString(),
            'id' => uniqid()
        ]);

        $this->validaciones = $validaciones;
        $this->last_validation_at = now();
        $this->save();
    }

    /**
     * Get latest validation.
     */
    public function getLatestValidation(): ?array
    {
        $validaciones = $this->validaciones ?? [];
        return empty($validaciones) ? null : end($validaciones);
    }

    /**
     * Get validation history.
     */
    public function getValidationHistory(): array
    {
        return $this->validaciones ?? [];
    }

    /**
     * Set metadata value.
     */
    public function setMetadata(string $key, mixed $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * Get metadata value.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        $metadata = $this->metadata ?? [];
        return $metadata[$key] ?? $default;
    }

    /**
     * Update status.
     */
    public function updateStatus(string $newStatus, ?string $reason = null): void
    {
        $this->estado = $newStatus;

        if ($reason) {
            $this->setMetadata('cambio_estado_razon', $reason);
        }

        $this->save();
    }

    /**
     * Mark as verified.
     */
    public function markAsVerified(?string $verifiedBy = null): void
    {
        $this->updateStatus('VERIFICADO', $verifiedBy);
        $this->addValidation([
            'tipo' => 'verificacion',
            'resultado' => 'aprobado',
            'verificado_por' => $verifiedBy,
            'observaciones' => 'Entidad verificada exitosamente'
        ]);
    }

    /**
     * Mark as rejected.
     */
    public function markAsRejected(string $reason, ?string $rejectedBy = null): void
    {
        $this->updateStatus('RECHAZADO', $reason);
        $this->addValidation([
            'tipo' => 'verificacion',
            'resultado' => 'rechazado',
            'verificado_por' => $rejectedBy,
            'observaciones' => $reason
        ]);
    }

    /**
     * Get status with color.
     */
    public function getStatusWithColorAttribute(): array
    {
        $colors = [
            'PENDIENTE' => '#F59E0B',
            'VERIFICADO' => '#10B981',
            'RECHAZADO' => '#EF4444',
            'EXPIRADO' => '#6B7280'
        ];

        return [
            'status' => $this->estado,
            'color' => $colors[$this->estado] ?? '#6B7280'
        ];
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'identificacion' => [
                'tipo' => $this->tipo_identificacion,
                'numero' => $this->numero_identificacion
            ],
            'documentos' => $this->getDocumentPaths(),
            'selfie' => $this->getSelfiePath(),
            'clave_firma_hash' => $this->clave_firma_hash,
            'estado' => $this->getStatusWithColorAttribute(),
            'metadata' => $this->metadata,
            'validaciones' => $this->getValidationHistory(),
            'ultima_validacion' => $this->getLatestValidation(),
            'last_validation_at' => $this->last_validation_at?->toISOString(),
            'tiene_documentos_completos' => $this->hasRequiredDocuments(),
            'is_verified' => $this->isVerified(),
            'is_pending' => $this->isPending(),
            'is_rejected' => $this->isRejected(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'full_name' => $this->user->full_name,
                'email' => $this->user->email
            ] : null,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get statistics for entities.
     */
    public static function getStatistics(): array
    {
        $total = static::count();
        $verified = static::where('estado', 'VERIFICADO')->count();
        $pending = static::where('estado', 'PENDIENTE')->count();
        $rejected = static::where('estado', 'RECHAZADO')->count();

        return [
            'total' => $total,
            'verified' => $verified,
            'pending' => $pending,
            'rejected' => $rejected,
            'verification_rate' => $total > 0 ? round(($verified / $total) * 100, 2) : 0,
            'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 2) : 0
        ];
    }
}
