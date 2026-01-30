<?php

namespace App\Services;

use App\Models\SolicitudCredito;
use App\Repositories\SolicitudRepository;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SolicitudesService extends BaseService
{
    private string $storageDir;

    public function __construct(SolicitudRepository $solicitudRepository)
    {
        parent::__construct($solicitudRepository);
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
                throw new ValidationException('Formato no permitido. Solo PDF, JPG y PNG.');
            }

            // Prepare directory
            $solicitudDir = "{$this->storageDir}/{$solicitudId}";

            // Generate unique filename
            $documentoId = Str::uuid()->toString();
            $safeFilename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $extension = $file->getClientOriginalExtension();
            $uniqueFilename = "{$documentoId}_{$safeFilename}.{$extension}";

            // Store file
            $path = $file->storeAs($solicitudDir, $uniqueFilename, 'public');

            // Get file size
            $fileSize = $file->getSize();

            // Create document record
            $nuevoDocumento = [
                'id' => $documentoId,
                'documento_requerido_id' => $documentoRequeridoId,
                'nombre_original' => $file->getClientOriginalName(),
                'saved_filename' => $uniqueFilename,
                'tipo_mime' => $file->getMimeType(),
                'tamano_bytes' => $fileSize,
                'created_at' => now()->toISOString()
            ];

            $this->log('Document saved successfully', [
                'solicitud_id' => $solicitudId,
                'documento_id' => $documentoId,
                'filename' => $uniqueFilename
            ]);

            return $nuevoDocumento;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error saving document file', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Error al guardar documento: ' . $e->getMessage());
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
            $this->logError('Error deleting document file', [
                'solicitud_id' => $solicitudId,
                'filename' => $savedFilename,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Serve document file for download.
     */
    public function servirArchivoDocumento(string $solicitudId, string $savedFilename): array
    {
        try {
            $filePath = "{$this->storageDir}/{$solicitudId}/{$savedFilename}";

            if (!Storage::disk('public')->exists($filePath)) {
                throw new ValidationException('Archivo no encontrado');
            }

            $fullPath = Storage::disk('public')->path($filePath);
            $mimeType = Storage::disk('public')->mimeType($filePath);

            return [
                'path' => $fullPath,
                'filename' => $savedFilename,
                'mime_type' => $mimeType,
                'size' => Storage::disk('public')->size($filePath),
                'url' => Storage::disk('public')->url($filePath)
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error serving document file', [
                'solicitud_id' => $solicitudId,
                'filename' => $savedFilename,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Error al servir archivo: ' . $e->getMessage());
        }
    }

    /**
     * Serialize solicitud for frontend.
     */
    public function serializeForFrontend(SolicitudCredito $solicitud): array
    {
        $payload = $solicitud->payload ?? [];

        // Extract línea de crédito from payload
        $lineaCredito = $payload['linea_credito'] ?? [];

        // If no línea de crédito data, build from solicitud data
        if (empty($lineaCredito)) {
            $solicitudData = $payload['solicitud'] ?? [];
            $lineaCredito = [
                'tipcre' => $solicitudData['tipcre'] ?? null,
                'modxml4' => $solicitudData['modxml4'] ?? null,
                'detalle' => $solicitudData['detalle_modalidad'] ?? $solicitudData['categoria'] ?? null,
                'codigo_cre' => $solicitudData['codigo_cre'] ?? null,
                'codigo_cap' => $solicitudData['codigo_cap'] ?? null,
                'codigo_ser' => $solicitudData['codigo_ser'] ?? null,
                'codigo_int' => $solicitudData['codigo_int'] ?? null,
                'codigo_mor' => $solicitudData['codigo_mor'] ?? null,
                'codigo_con' => $solicitudData['codigo_con'] ?? null,
                'codigo_cen' => $solicitudData['codigo_cen'] ?? null,
                'numero_cuotas' => $solicitudData['numero_cuotas'] ?? null,
                'estado' => $solicitudData['estado'] ?? null,
                'auxest' => $solicitudData['auxest'] ?? null,
                'estcre' => $solicitudData['estcre'] ?? null,
                'pagseg' => $solicitudData['pagseg'] ?? null,
                'repdcr' => $solicitudData['repdcr'] ?? null,
                'tipfin' => $solicitudData['tipfin'] ?? null,
                'documentos' => []
            ];
        }

        return [
            'id' => $solicitud->id,
            'estado' => $solicitud->estado ?? 'Postulado',
            'lineaCredito' => $lineaCredito,
            'formData' => $payload,
            'xml_filename' => $solicitud->xml_filename,
            'documentosCargados' => $solicitud->documentos ?? [],
            'created_at' => $solicitud->created_at?->toISOString(),
            'updated_at' => $solicitud->updated_at?->toISOString(),
            'numero_solicitud' => $solicitud->numero_solicitud,
            'timeline' => $solicitud->timeline ?? [],
            'owner_username' => $solicitud->owner_username,
            'monto_solicitado' => $solicitud->monto_solicitado,
            'plazo_meses' => $solicitud->plazo_meses,
            'destino_credito' => $solicitud->destino_credito,
            'solicitante' => $solicitud->solicitante
        ];
    }

    /**
     * Get solicitudes by user with frontend serialization.
     */
    public function getSolicitudesByUser(string $username, int $skip = 0, int $limit = 50): array
    {
        try {
            $solicitudes = $this->repository->getByUserPaginated($username, $limit, ['skip' => $skip]);

            $serializedSolicitudes = $solicitudes->getCollection()->map(function ($solicitud) {
                return $this->serializeForFrontend($solicitud);
            })->toArray();

            return [
                'solicitudes' => $serializedSolicitudes,
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'total' => $solicitudes->total(),
                    'has_more' => ($skip + $limit) < $solicitudes->total()
                ]
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting solicitudes by user', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return [
                'solicitudes' => [],
                'pagination' => ['skip' => $skip, 'limit' => $limit, 'total' => 0, 'has_more' => false]
            ];
        }
    }

    /**
     * Get all solicitudes with filters and frontend serialization.
     */
    public function getAllSolicitudes(array $filters = [], int $skip = 0, int $limit = 50): array
    {
        try {
            $query = SolicitudCredito::query();

            // Apply filters
            if (isset($filters['estado'])) {
                $query->where('estado', $filters['estado']);
            }

            if (isset($filters['owner_username'])) {
                $query->where('owner_username', $filters['owner_username']);
            }

            if (isset($filters['numero_solicitud'])) {
                $query->where('numero_solicitud', 'like', '%' . $filters['numero_solicitud'] . '%');
            }

            $solicitudes = $query->orderBy('created_at', 'desc')
                ->skip($skip)
                ->limit($limit)
                ->get();

            $total = $query->count();

            $serializedSolicitudes = $solicitudes->map(function ($solicitud) {
                return $this->serializeForFrontend($solicitud);
            })->toArray();

            return [
                'solicitudes' => $serializedSolicitudes,
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'total' => $total,
                    'has_more' => ($skip + $limit) < $total
                ]
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting all solicitudes', ['error' => $e->getMessage()]);
            return [
                'solicitudes' => [],
                'pagination' => ['skip' => $skip, 'limit' => $limit, 'total' => 0, 'has_more' => false]
            ];
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

            $documentosPorEstado = SolicitudCredito::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => [
                        '_id' => '$estado',
                        'count' => ['$sum' => 1],
                        'with_docs' => [
                            '$sum' => ['$cond' => [
                                ['$gt' => [['$size' => ['$ifNull' => ['$documentos', []]]], 0]],
                                1,
                                0
                            ]]
                        ]
                    ]],
                    ['$sort' => ['count' => -1]]
                ]);
            });

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
            $this->logError('Error getting document statistics', ['error' => $e->getMessage()]);
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
                foreach ($solicitud->documentos ?? [] as $documento) {
                    $size = $documento['tamano_bytes'] ?? 0;
                    $mimeType = $documento['tipo_mime'] ?? 'unknown';

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
            $this->logError('Error getting storage statistics', ['error' => $e->getMessage()]);
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
                $solicitud = SolicitudCredito::find($solicitudId);

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
            $this->logError('Error cleaning up orphaned files', ['error' => $e->getMessage()]);
            return [
                'cleaned_files' => [],
                'count' => 0
            ];
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

    /**
     * Ensure MongoDB indexes.
     */
    public function ensureIndexes(): bool
    {
        try {
            // Create indexes for better performance
            SolicitudCredito::createIndex(['owner_username' => 1, 'created_at' => -1]);
            SolicitudCredito::createIndex(['estado' => 1]);
            SolicitudCredito::createIndex(['numero_solicitud' => 1]);

            $this->log('MongoDB indexes ensured for solicitudes');

            return true;
        } catch (\Exception $e) {
            $this->logError('Error ensuring MongoDB indexes', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
