<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolicitudDocumento extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'solicitud_documentos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'solicitud_id',
        'documento_uuid',
        'nombre_archivo',
        'tipo_documento',
        'ruta_archivo',
        'tamano_bytes',
        'mime_type',
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
            'tamano_bytes' => 'integer',
            'activo' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the solicitud that owns the documento.
     */
    public function solicitud()
    {
        return $this->belongsTo(SolicitudCredito::class, 'solicitud_id');
    }

    /**
     * Scope to get only active documents.
     */
    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope to get documents by type.
     */
    public function scopeByType($query, string $tipo)
    {
        return $query->where('tipo_documento', $tipo);
    }

    /**
     * Get formatted file size.
     */
    public function getTamanoFormattedAttribute(): string
    {
        $bytes = $this->tamano_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get file extension.
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->nombre_archivo, PATHINFO_EXTENSION);
    }

    /**
     * Check if file is an image.
     */
    public function isImage(): bool
    {
        $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($this->mime_type, $imageMimes);
    }

    /**
     * Check if file is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get file icon based on type.
     */
    public function getFileIconAttribute(): string
    {
        if ($this->isImage()) {
            return 'image';
        }
        
        if ($this->isPdf()) {
            return 'pdf';
        }
        
        $extension = strtolower($this->extension);
        
        return match($extension) {
            'doc', 'docx' => 'word',
            'xls', 'xlsx' => 'excel',
            'ppt', 'pptx' => 'powerpoint',
            'txt' => 'text',
            'zip', 'rar', '7z' => 'archive',
            default => 'file'
        };
    }

    /**
     * Get download URL.
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('solicitudes.documentos.download', [
            'solicitud' => $this->solicitud_id,
            'documento' => $this->id
        ]);
    }

    /**
     * Get preview URL (for images).
     */
    public function getPreviewUrlAttribute(): ?string
    {
        if (!$this->isImage()) {
            return null;
        }
        
        return route('solicitudes.documentos.preview', [
            'solicitud' => $this->solicitud_id,
            'documento' => $this->id
        ]);
    }

    /**
     * Soft delete the document.
     */
    public function softDelete(): void
    {
        $this->activo = false;
        $this->save();
        $this->delete();
    }

    /**
     * Restore the document.
     */
    public function restoreDocument(): void
    {
        $this->restore();
        $this->activo = true;
        $this->save();
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'documento_uuid' => $this->documento_uuid,
            'nombre_archivo' => $this->nombre_archivo,
            'tipo_documento' => $this->tipo_documento,
            'ruta_archivo' => $this->ruta_archivo,
            'tamano_bytes' => $this->tamano_bytes,
            'tamano_formatted' => $this->tamano_formatted,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'file_icon' => $this->file_icon,
            'is_image' => $this->isImage(),
            'is_pdf' => $this->isPdf(),
            'activo' => $this->activo,
            'download_url' => $this->download_url,
            'preview_url' => $this->preview_url,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }
}
