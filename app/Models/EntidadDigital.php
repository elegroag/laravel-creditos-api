<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntidadDigital extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'entidades_digitales';

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
            'documentos' => 'array',
            'metadata' => 'array',
            'validaciones' => 'array',
            'last_validation_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'estado' => 'string'
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
     * Estados posibles para la entidad digital
     */
    const ESTADOS = [
        'activa' => 'Activa',
        'suspendida' => 'Suspendida',
        'revocada' => 'Revocada',
        'en_validacion' => 'En Validación',
        'bloqueada' => 'Bloqueada'
    ];

    /**
     * Find entidad digital by username.
     */
    public static function findByUsername(string $username): ?self
    {
        return static::where('username', $username)->first();
    }

    /**
     * Find entidad digital by document.
     */
    public static function findByDocument(string $tipoIdentificacion, string $numeroIdentificacion): ?self
    {
        return static::where('tipo_identificacion', $tipoIdentificacion)
            ->where('numero_identificacion', $numeroIdentificacion)
            ->first();
    }

    /**
     * Check if entidad exists by document.
     */
    public static function existsByDocument(string $tipoIdentificacion, string $numeroIdentificacion): bool
    {
        return static::where('tipo_identificacion', $tipoIdentificacion)
            ->where('numero_identificacion', $numeroIdentificacion)
            ->exists();
    }

    /**
     * Add validation record.
     */
    public function addValidation(string $tipoValidacion, string $resultado, array $detalles = []): void
    {
        $validaciones = $this->validaciones ?? [];
        $validaciones[] = [
            'tipo' => $tipoValidacion, // "acceso", "firma", "verificacion"
            'resultado' => $resultado, // "exitosa", "fallida"
            'fecha' => now()->toISOString(),
            'detalles' => $detalles
        ];

        $this->validaciones = $validaciones;
        $this->last_validation_at = now();
        $this->save();
    }

    /**
     * Get estado label.
     */
    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Get document front URL.
     */
    public function getDocumentoFrenteUrlAttribute(): ?string
    {
        return $this->documentos['frente'] ?? null;
    }

    /**
     * Get document back URL.
     */
    public function getDocumentoReversoUrlAttribute(): ?string
    {
        return $this->documentos['reverso'] ?? null;
    }

    /**
     * Check if has both document sides.
     */
    public function hasCompleteDocuments(): bool
    {
        return !empty($this->documentos['frente']) && !empty($this->documentos['reverso']);
    }

    /**
     * Check if has selfie.
     */
    public function hasSelfie(): bool
    {
        return !empty($this->selfie);
    }

    /**
     * Check if is complete (documents + selfie).
     */
    public function isComplete(): bool
    {
        return $this->hasCompleteDocuments() && $this->hasSelfie();
    }

    /**
     * Check if is active.
     */
    public function isActive(): bool
    {
        return $this->estado === 'activa';
    }

    /**
     * Activate entidad.
     */
    public function activate(): void
    {
        $this->estado = 'activa';
        $this->save();
    }

    /**
     * Suspend entidad.
     */
    public function suspend(): void
    {
        $this->estado = 'suspendida';
        $this->save();
    }

    /**
     * Revoke entidad.
     */
    public function revoke(): void
    {
        $this->estado = 'revocada';
        $this->save();
    }

    /**
     * Get validation count.
     */
    public function getValidationCountAttribute(): int
    {
        return count($this->validaciones ?? []);
    }

    /**
     * Get successful validations count.
     */
    public function getSuccessfulValidationsCountAttribute(): int
    {
        return collect($this->validaciones ?? [])
            ->where('resultado', 'exitosa')
            ->count();
    }

    /**
     * Get failed validations count.
     */
    public function getFailedValidationsCountAttribute(): int
    {
        return collect($this->validaciones ?? [])
            ->where('resultado', 'fallida')
            ->count();
    }

    /**
     * Get latest validation.
     */
    public function getLatestValidationAttribute(): ?array
    {
        $validaciones = $this->validaciones ?? [];
        return empty($validaciones) ? null : end($validaciones);
    }

    /**
     * Scope by estado.
     */
    public function scopeByEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope active.
     */
    public function scopeActive($query)
    {
        return $query->where('estado', 'activa');
    }

    /**
     * Get full identification.
     */
    public function getFullIdentificationAttribute(): string
    {
        return $this->tipo_identificacion . ' ' . $this->numero_identificacion;
    }
}
