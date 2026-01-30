<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentoPostulante extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'documentos_postulantes';

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
            'documentos' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'ruta_archivos' => 'string'
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
     * Find by username.
     */
    public static function findByUsername(string $username): ?self
    {
        return static::where('username', $username)->first();
    }

    /**
     * Find by document.
     */
    public static function findByDocument(string $tipoIdentificacion, string $numeroIdentificacion): ?self
    {
        return static::where('tipo_identificacion', $tipoIdentificacion)
            ->where('numero_identificacion', $numeroIdentificacion)
            ->first();
    }

    /**
     * Get document by type.
     */
    public function getDocumentoByType(string $tipo): ?string
    {
        $documentos = $this->documentos ?? [];
        return $documentos[$tipo] ?? null;
    }

    /**
     * Add document.
     */
    public function addDocumento(string $tipo, string $ruta): void
    {
        $documentos = $this->documentos ?? [];
        $documentos[$tipo] = $ruta;
        $this->documentos = $documentos;
        $this->save();
    }

    /**
     * Remove document.
     */
    public function removeDocumento(string $tipo): void
    {
        $documentos = $this->documentos ?? [];
        unset($documentos[$tipo]);
        $this->documentos = $documentos;
        $this->save();
    }

    /**
     * Check if has document type.
     */
    public function hasDocumento(string $tipo): bool
    {
        $documentos = $this->documentos ?? [];
        return !empty($documentos[$tipo]);
    }

    /**
     * Check if has selfie.
     */
    public function hasSelfie(): bool
    {
        return !empty($this->selfie);
    }

    /**
     * Check if has any documents.
     */
    public function hasDocuments(): bool
    {
        return !empty($this->documentos);
    }

    /**
     * Check if is complete (has at least one document or selfie).
     */
    public function isComplete(): bool
    {
        return $this->hasDocuments() || $this->hasSelfie();
    }

    /**
     * Get document count.
     */
    public function getDocumentCountAttribute(): int
    {
        return count($this->documentos ?? []);
    }

    /**
     * Get document types list.
     */
    public function getDocumentTypesAttribute(): array
    {
        return array_keys($this->documentos ?? []);
    }

    /**
     * Get full identification.
     */
    public function getFullIdentificationAttribute(): string
    {
        return $this->tipo_identificacion . ' ' . $this->numero_identificacion;
    }

    /**
     * Get storage path.
     */
    public function getStoragePathAttribute(): string
    {
        return $this->ruta_archivos ?? 'storage/documentos/' . $this->username;
    }

    /**
     * Get document URL.
     */
    public function getDocumentoUrl(string $tipo): ?string
    {
        $ruta = $this->getDocumentoByType($tipo);
        if ($ruta) {
            return asset($ruta);
        }
        return null;
    }

    /**
     * Get selfie URL.
     */
    public function getSelfieUrlAttribute(): ?string
    {
        if ($this->selfie) {
            return asset($this->selfie);
        }
        return null;
    }

    /**
     * Set selfie.
     */
    public function setSelfie(string $ruta): void
    {
        $this->selfie = $ruta;
        $this->save();
    }

    /**
     * Remove selfie.
     */
    public function removeSelfie(): void
    {
        $this->selfie = null;
        $this->save();
    }

    /**
     * Get all files paths.
     */
    public function getAllFilesPathsAttribute(): array
    {
        $paths = [];

        // Add documents
        foreach ($this->documentos ?? [] as $tipo => $ruta) {
            $paths[] = $ruta;
        }

        // Add selfie
        if ($this->selfie) {
            $paths[] = $this->selfie;
        }

        return $paths;
    }

    /**
     * Get file size total (if metadata available).
     */
    public function getTotalFileSizeAttribute(): int
    {
        $total = 0;
        $metadata = $this->metadata ?? [];

        foreach ($metadata['files'] ?? [] as $file) {
            $total += $file['size'] ?? 0;
        }

        return $total;
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->total_file_size;

        if ($bytes < 1024) {
            return $bytes . ' bytes';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / 1048576, 2) . ' MB';
        }
    }

    /**
     * Update metadata.
     */
    public function updateMetadata(array $data): void
    {
        $this->metadata = array_merge($this->metadata ?? [], $data);
        $this->save();
    }

    /**
     * Add file metadata.
     */
    public function addFileMetadata(string $filename, int $size, string $type): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['files'][$filename] = [
            'size' => $size,
            'type' => $type,
            'uploaded_at' => now()->toISOString()
        ];

        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * Scope by username.
     */
    public function scopeByUsername($query, string $username)
    {
        return $query->where('username', $username);
    }

    /**
     * Scope by document.
     */
    public function scopeByDocument($query, string $tipoIdentificacion, string $numeroIdentificacion)
    {
        return $query->where('tipo_identificacion', $tipoIdentificacion)
            ->where('numero_identificacion', $numeroIdentificacion);
    }

    /**
     * Create from array data.
     */
    public static function createFromArray(array $data): self
    {
        return static::create([
            'username' => $data['username'],
            'tipo_identificacion' => $data['tipo_identificacion'],
            'numero_identificacion' => $data['numero_identificacion'],
            'documentos' => $data['documentos'] ?? [],
            'selfie' => $data['selfie'] ?? null,
            'ruta_archivos' => $data['ruta_archivos'] ?? 'storage/documentos/' . $data['username'],
            'metadata' => $data['metadata'] ?? []
        ]);
    }
}
