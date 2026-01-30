<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfGenerado extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pdfs_generados';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'solicitud_id',
        'path',
        'filename',
        'generado_en'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generado_en' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the solicitud that owns the PDF.
     */
    public function solicitud()
    {
        return $this->belongsTo(SolicitudCredito::class, 'solicitud_id', 'numero_solicitud');
    }

    /**
     * Find PDF by solicitud.
     */
    public static function findBySolicitud(string $solicitudId): ?self
    {
        return static::where('solicitud_id', $solicitudId)->first();
    }

    /**
     * Find PDF by filename.
     */
    public static function findByFilename(string $filename): ?self
    {
        return static::where('filename', $filename)->first();
    }

    /**
     * Check if PDF exists.
     */
    public function archivoExiste(): bool
    {
        return file_exists($this->path);
    }

    /**
     * Get file size.
     */
    public function getTamanoAttribute(): int
    {
        return $this->archivoExiste() ? filesize($this->path) : 0;
    }

    /**
     * Get formatted file size.
     */
    public function getTamanoFormateadoAttribute(): string
    {
        $bytes = $this->getTamanoAttribute();
        
        if ($bytes === 0) {
            return '0 bytes';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $factor), 2) . ' ' . $units[$factor];
    }

    /**
     * Get file URL.
     */
    public function getUrlAttribute(): string
    {
        return url($this->path);
    }

    /**
     * Get download URL.
     */
    public function getUrlDescargaAttribute(): string
    {
        return url('download/pdf/' . $this->id);
    }

    /**
     * Get generation date.
     */
    public function getFechaGeneracionAttribute(): ?string
    {
        if ($this->generado_en && isset($this->generado_en['$date'])) {
            return $this->generado_en['$date'];
        }
        
        return $this->created_at ? $this->created_at->toISOString() : null;
    }

    /**
     * Check if PDF was generated successfully.
     */
    public function fueGenerado(): bool
    {
        return !empty($this->path) && $this->archivoExiste();
    }

    /**
     * Regenerate PDF for solicitud.
     */
    public static function regenerarPdf(string $solicitudId, array $data): ?self
    {
        // Eliminar PDF existente si hay
        $existente = static::findBySolicitud($solicitudId);
        if ($existente) {
            $existente->delete();
        }
        
        // Generar nuevo PDF
        $filename = 'solicitud_' . $solicitudId . '_' . date('Ymd_His') . '.pdf';
        $path = storage_path('solicitudes/' . $solicitudId . '/' . $filename);
        
        // Asegurar que el directorio exista
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Aquí iría la lógica real de generación del PDF
        // Por ahora, creamos un archivo vacío como placeholder
        if (touch($path)) {
            return static::create([
                'solicitud_id' => $solicitudId,
                'path' => $path,
                'filename' => $filename,
                'generado_en' => [
                    '$date' => now()->toISOString(),
                    'generado_por' => 'sistema',
                    'version' => '1.0'
                ]
            ]);
        }
        
        return null;
    }

    /**
     * Delete PDF file and record.
     */
    public function eliminar(): bool
    {
        // Eliminar archivo físico
        if ($this->archivoExiste()) {
            unlink($this->path);
        }
        
        // Eliminar registro
        return $this->delete();
    }

    /**
     * Get PDF content as base64.
     */
    public function getContenidoBase64(): ?string
    {
        if (!$this->archivoExiste()) {
            return null;
        }
        
        $content = file_get_contents($this->path);
        return base64_encode($content);
    }

    /**
     * Download PDF to browser.
     */
    public function descargar(): \Symfony\Component\HttpFoundation\Response
    {
        if (!$this->archivoExiste()) {
            abort(404, 'PDF no encontrado');
        }
        
        return response()->download($this->path, $this->filename);
    }

    /**
     * Display PDF in browser.
     */
    public function mostrar(): \Symfony\Component\HttpFoundation\Response
    {
        if (!$this->archivoExiste()) {
            abort(404, 'PDF no encontrado');
        }
        
        return response()->file($this->path, 'application/pdf');
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'path' => $this->path,
            'filename' => $this->filename,
            'url' => $this->getUrlAttribute(),
            'url_descarga' => $this->getUrlDescargaAttribute(),
            'tamano' => $this->getTamanoAttribute(),
            'tamano_formateado' => $this->getTamanoFormateadoAttribute(),
            'fecha_generacion' => $this->getFechaGeneracionAttribute(),
            'generado_en' => $this->generado_en,
            'fue_generado' => $this->fueGenerado(),
            'archivo_existe' => $this->archivoExiste(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get PDF by solicitud for API.
     */
    public static function getBySolicitudForApi(string $solicitudId): ?array
    {
        $pdf = static::findBySolicitud($solicitudId);
        
        return $pdf ? $pdf->toApiArray() : null;
    }

    /**
     * Get all PDFs for API.
     */
    public static function getAllForApi(): array
    {
        return static::with('solicitud')->get()->map(function ($pdf) {
            return $pdf->toApiArray();
        })->toArray();
    }

    /**
     * Get PDF statistics.
     */
    public static function getStatistics(): array
    {
        $total = static::count();
        $existentes = 0;
        $tamanoTotal = 0;
        
        $pdfs = static::all();
        foreach ($pdfs as $pdf) {
            if ($pdf->archivoExiste()) {
                $existentes++;
                $tamanoTotal += $pdf->getTamanoAttribute();
            }
        }
        
        return [
            'total' => $total,
            'existentes' => $existentes,
            'no_existentes' => $total - $existentes,
            'tamano_total' => $tamanoTotal,
            'tamano_promedio' => $existentes > 0 ? round($tamanoTotal / $existentes, 2) : 0,
            'tamano_formateado_total' => $this->formatBytes($tamanoTotal)
        ];
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $factor), 2) . ' ' . $units[$factor];
    }

    /**
     * Clean up orphaned PDFs.
     */
    public static function limpiarHuerfanos(): int
    {
        $eliminados = 0;
        
        // Obtener todos los PDFs
        $pdfs = static::all();
        
        foreach ($pdfs as $pdf) {
            // Verificar si la solicitud existe
            $solicitud = SolicitudCredito::where('numero_solicitud', $pdf->solicitud_id)->first();
            
            if (!$solicitud) {
                $pdf->eliminar();
                $eliminados++;
            }
        }
        
        return $eliminados;
    }

    /**
     * Clean up missing files.
     */
    public static function limpiarArchivosFaltantes(): int
    {
        $eliminados = 0;
        
        $pdfs = static::all();
        foreach ($pdfs as $pdf) {
            if (!$pdf->archivoExiste()) {
                $pdf->delete();
                $eliminados++;
            }
        }
        
        return $eliminados;
    }
}
