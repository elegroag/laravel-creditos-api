<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentoPostulante extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'documentos_postulantes';

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
        'ruta_archivos',
        'metadata'
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'ruta_archivos' => 'string'
        ];
    }

    /**
     * Get the user that owns the documents.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    /**
     * Find by username.
     */
    public static function findByUsername(string $username): ?self
    {
        return static::where('username', $username)->first();
    }

    /**
     * Find by identification.
     */
    public static function findByIdentification(string $tipoId, string $numeroId): ?self
    {
        return static::where('tipo_identificacion', $tipoId)
                    ->where('numero_identificacion', $numeroId)
                    ->first();
    }

    /**
     * Scope by document type.
     */
    public function scopeByDocumentType($query, string $tipo)
    {
        return $query->where('tipo_identificacion', $tipo);
    }

    /**
     * Scope by username.
     */
    public function scopeByUsername($query, string $username)
    {
        return $query->where('username', $username);
    }

    /**
     * Check if has required documents.
     */
    public function hasRequiredDocuments(): bool
    {
        $requiredDocs = ['cedula', 'selfie', 'comprobante_domicilio'];
        $documentos = $this->documentos ?? [];
        
        foreach ($requiredDocs as $doc) {
            if (!isset($documentos[$doc]) || empty($documentos[$doc])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Add document.
     */
    public function addDocument(string $tipo, string $ruta, array $metadata = []): void
    {
        $documentos = $this->documentos ?? [];
        
        $documentos[$tipo] = [
            'ruta' => $ruta,
            'fecha_subida' => now()->toISOString(),
            'metadata' => $metadata
        ];
        
        $this->documentos = $documentos;
        $this->save();
    }

    /**
     * Remove document.
     */
    public function removeDocument(string $tipo): void
    {
        $documentos = $this->documentos ?? [];
        
        if (isset($documentos[$tipo])) {
            unset($documentos[$tipo]);
            $this->documentos = $documentos;
            $this->save();
        }
    }

    /**
     * Get document path.
     */
    public function getDocumentPath(string $tipo): ?string
    {
        $documentos = $this->documentos ?? [];
        
        return $documentos[$tipo]['ruta'] ?? null;
    }

    /**
     * Set selfie path.
     */
    public function setSelfie(string $ruta): void
    {
        $this->selfie = $ruta;
        $this->save();
    }

    /**
     * Get selfie path.
     */
    public function getSelfiePath(): ?string
    {
        return $this->selfie;
    }

    /**
     * Check if has selfie.
     */
    public function hasSelfie(): bool
    {
        return !empty($this->selfie);
    }

    /**
     * Get documents count.
     */
    public function getDocumentsCountAttribute(): int
    {
        return count($this->documentos ?? []);
    }

    /**
     * Get documents list.
     */
    public function getDocumentsListAttribute(): array
    {
        return array_keys($this->documentos ?? []);
    }

    /**
     * Set metadata.
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata ?? [], $metadata);
        $this->save();
    }

    /**
     * Get metadata value.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        $metadata = $this->metadata ?? [];
        return $metadata[$key] ?? $default;
    }

    /**
     * Validate documents.
     */
    public function validateDocuments(): array
    {
        $errors = [];
        $documentos = $this->documentos ?? [];
        
        if (!$this->hasSelfie()) {
            $errors[] = 'Selfie es requerida';
        }
        
        $requiredDocs = ['cedula', 'comprobante_domicilio'];
        foreach ($requiredDocs as $doc) {
            if (!isset($documentos[$doc]) || empty($documentos[$doc])) {
                $errors[] = "Documento {$doc} es requerido";
            }
        }
        
        return $errors;
    }

    /**
     * Get verification status.
     */
    public function getVerificationStatusAttribute(): string
    {
        if ($this->hasRequiredDocuments() && $this->hasSelfie()) {
            return 'completo';
        }
        
        return 'incompleto';
    }

    /**
     * Soft delete documents.
     */
    public function softDelete(): void
    {
        $this->delete();
    }

    /**
     * Restore documents.
     */
    public function restoreDocuments(): void
    {
        $this->restore();
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'tipo_identificacion' => $this->tipo_identificacion,
            'numero_identificacion' => $this->numero_identificacion,
            'documentos' => $this->documentos,
            'selfie' => $this->selfie,
            'ruta_archivos' => $this->ruta_archivos,
            'metadata' => $this->metadata,
            'documents_count' => $this->documents_count,
            'documents_list' => $this->documents_list,
            'verification_status' => $this->verification_status,
            'has_selfie' => $this->hasSelfie(),
            'has_required_documents' => $this->hasRequiredDocuments(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get all documents for API.
     */
    public static function getAllForApi(): array
    {
        return static::with(['user'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($documento) {
                return $documento->toApiArray();
            })
            ->toArray();
    }

    /**
     * Get documents by username for API.
     */
    public static function getByUsernameForApi(string $username): array
    {
        return static::where('username', $username)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($documento) {
                return $documento->toApiArray();
            })
            ->toArray();
    }

    /**
     * Create documents for user.
     */
    public static function createForUser(string $username, string $tipoId, string $numeroId): self
    {
        return static::create([
            'username' => $username,
            'tipo_identificacion' => $tipoId,
            'numero_identificacion' => $numeroId,
            'documentos' => [],
            'metadata' => []
        ]);
    }
}
