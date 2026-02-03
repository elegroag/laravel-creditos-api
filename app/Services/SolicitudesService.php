<?php

namespace App\Services;

use App\Models\SolicitudCredito;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SolicitudesService extends EloquentService
{
    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = 'solicitudes';
    }

    /**
     * Save document file for solicitud.
     */
    public function guardarDocumentoArchivo(string $solicitudId, UploadedFile $file, string $documentoRequeridoId): array
    {
        try {
            // Validate file type
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($file->getMimeType(), $allowedTypes)) {
                throw new ValidationException('Tipo de archivo no permitido');
            }

            // Generate unique filename
            $uniqueFilename = $documentoRequeridoId . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $filePath = "{$this->storageDir}/{$solicitudId}/{$uniqueFilename}";

            // Store file
            $file->storeAs($this->storageDir . '/' . $solicitudId, $uniqueFilename, 'public');

            $nuevoDocumento = [
                'documento_id' => $documentoRequeridoId,
                'filename' => $uniqueFilename,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'path' => $filePath,
                'url' => Storage::disk('public')->url($filePath)
            ];

            $this->log('Document saved successfully', [
                'solicitud_id' => $solicitudId,
                'filename' => $uniqueFilename
            ]);

            return $nuevoDocumento;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'guardado de archivo de documento');
            throw new \Exception('Error al guardar documento: ' . $e->getMessage());
        }
    }

    /**
     * Get document statistics.
     */
    public function getDocumentStatistics(): array
    {
        try {
            $totalSolicitudes = SolicitudCredito::count();
            $solicitudesConDocumentos = SolicitudCredito::where('documentos', '!=', '')->count();

            $documentosPorEstado = SolicitudCredito::selectRaw('estado, COUNT(*) as count,
                SUM(CASE WHEN documentos IS NOT NULL AND documentos != \'\' THEN 1 ELSE 0 END) as with_docs')
                ->groupBy('estado')
                ->orderBy('count', 'desc')
                ->get();

            return [
                'total_solicitudes' => $totalSolicitudes,
                'solicitudes_con_documentos' => $solicitudesConDocumentos,
                'solicitudes_sin_documentos' => $totalSolicitudes - $solicitudesConDocumentos,
                'porcentaje_con_documentos' => $totalSolicitudes > 0
                    ? round(($solicitudesConDocumentos / $totalSolicitudes) * 100, 2)
                    : 0,
                'documentos_por_estado' => $documentosPorEstado->toArray()
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de estadísticas de documentos');
            return [
                'total_solicitudes' => 0,
                'solicitudes_con_documentos' => 0,
                'solicitudes_sin_documentos' => 0,
                'porcentaje_con_documentos' => 0,
                'documentos_por_estado' => []
            ];
        }
    }

    /**
     * Get storage usage statistics.
     */
    public function getStorageStatistics(): array
    {
        try {
            $totalSize = 0;
            $totalFiles = 0;
            $fileTypes = [];

            $solicitudes = SolicitudCredito::where('documentos', '!=', '')->get();

            foreach ($solicitudes as $solicitud) {
                $documentos = is_array($solicitud->documentos) ? $solicitud->documentos : json_decode($solicitud->documentos, true) ?? [];

                foreach ($documentos as $documento) {
                    $size = $documento['size'] ?? $documento['tamano_bytes'] ?? 0;
                    $mimeType = $documento['mime_type'] ?? $documento['tipo_mime'] ?? 'unknown';

                    $totalSize += $size;
                    $totalFiles++;

                    // Group by file type
                    if (!isset($fileTypes[$mimeType])) {
                        $fileTypes[$mimeType] = [
                            'count' => 0,
                            'size' => 0
                        ];
                    }

                    $fileTypes[$mimeType]['count']++;
                    $fileTypes[$mimeType]['size'] += $size;
                }
            }

            return [
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'total_files' => $totalFiles,
                'file_types' => $fileTypes,
                'average_file_size' => $totalFiles > 0 ? round($totalSize / $totalFiles) : 0
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de estadísticas de almacenamiento');
            return [
                'total_size' => 0,
                'total_size_formatted' => '0 B',
                'total_files' => 0,
                'file_types' => [],
                'average_file_size' => 0
            ];
        }
    }

    /**
     * Clean up orphaned files.
     */
    public function cleanupOrphanedFiles(): array
    {
        try {
            $cleanedFiles = [];
            $directories = Storage::disk('public')->directories($this->storageDir);

            foreach ($directories as $directory) {
                $solicitudId = basename($directory);

                // Check if solicitud exists
                $solicitud = SolicitudCredito::where('numero_solicitud', $solicitudId)->first();

                if (!$solicitud) {
                    // Delete entire directory
                    $files = Storage::disk('public')->files($directory);

                    foreach ($files as $file) {
                        Storage::disk('public')->delete($file);
                        $cleanedFiles[] = $file;
                    }

                    Storage::disk('public')->deleteDirectory($directory);
                }
            }

            $this->log('Orphaned files cleaned up', [
                'cleaned_files_count' => count($cleanedFiles)
            ]);

            return [
                'cleaned_files' => $cleanedFiles,
                'count' => count($cleanedFiles)
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'limpieza de archivos huérfanos');
            return [
                'cleaned_files' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Delete document file.
     */
    public function eliminarDocumentoArchivo(string $solicitudId, string $savedFilename): bool
    {
        try {
            $filePath = "{$this->storageDir}/{$solicitudId}/{$savedFilename}";

            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);

                $this->log('Document file deleted', [
                    'solicitud_id' => $solicitudId,
                    'filename' => $savedFilename
                ]);
            }

            // Check if directory is empty and remove it
            $directory = "{$this->storageDir}/{$solicitudId}";
            $files = Storage::disk('public')->files($directory);

            if (empty($files)) {
                Storage::disk('public')->deleteDirectory($directory);
            }

            return true;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'eliminación de archivo de documento');
            return false;
        }
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
    public function getSolicitudesByUser(string $username, int $skip = 0, int $limit = 50): array
    {
        try {
            $solicitudes = SolicitudCredito::where('owner_username', $username)
                ->orderBy('created_at', 'desc')
                ->skip($skip)
                ->take($limit)
                ->get();

            $serializedSolicitudes = $solicitudes->map(function ($solicitud) {
                return $this->serializeForFrontend($solicitud);
            })->toArray();

            return [
                'solicitudes' => $serializedSolicitudes,
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'total' => $solicitudes->count(),
                    'has_more' => false
                ]
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de solicitudes por usuario');
            return [
                'solicitudes' => [],
                'pagination' => ['skip' => $skip, 'limit' => $limit, 'total' => 0, 'has_more' => false]
            ];
        }
    }

    /**
     * Get all solicitudes.
     */
    public function getAllSolicitudes(int $skip = 0, int $limit = 50): array
    {
        try {
            $solicitudes = SolicitudCredito::orderBy('created_at', 'desc')
                ->skip($skip)
                ->take($limit)
                ->get();

            $serializedSolicitudes = $solicitudes->map(function ($solicitud) {
                return $this->serializeForFrontend($solicitud);
            })->toArray();

            return [
                'solicitudes' => $serializedSolicitudes,
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'total' => $solicitudes->count(),
                    'has_more' => false
                ]
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de solicitudes');
            return [
                'solicitudes' => [],
                'pagination' => ['skip' => $skip, 'limit' => $limit, 'total' => 0, 'has_more' => false]
            ];
        }
    }

    /**
     * Serialize solicitud for frontend.
     */
    private function serializeForFrontend($solicitud): array
    {
        return [
            'id' => $solicitud->id,
            'numero_solicitud' => $solicitud->numero_solicitud,
            'estado' => $solicitud->estado,
            'monto_solicitado' => $solicitud->monto_solicitado,
            'plazo_meses' => $solicitud->plazo_meses,
            'tipo_credito' => $solicitud->tipo_credito,
            'created_at' => $solicitud->created_at,
            'updated_at' => $solicitud->updated_at,
            'owner_username' => $solicitud->owner_username,
            'solicitante' => $solicitud->solicitante ?? null
        ];
    }
}
