<?php

namespace App\Services;

use App\Models\SolicitudCredito;
use App\Models\NumeroSolicitud;
use App\Models\EstadoSolicitud;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class SolicitudService extends EloquentService
{
    /**
     * Create a new solicitud.
     */
    public function create(array $data, string $ownerUsername): SolicitudCredito
    {
        try {
            // Generate unique solicitud number
            $numeroSolicitud = $this->generarNumeroSolicitud();

            // Prepare solicitud data
            $solicitudData = array_merge([
                'numero_solicitud' => $numeroSolicitud,
                'owner_username' => $ownerUsername,
                'estado' => 'PENDIENTE',
                'created_at' => now(),
                'updated_at' => now()
            ], $data);

            return SolicitudCredito::create($solicitudData);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'creación de solicitud');
            throw new \Exception('Error al crear solicitud');
        }
    }

    /**
     * Upload documents for solicitud.
     */
    public function uploadDocuments(string $solicitudId, array $documents): array
    {
        try {
            $solicitud = $this->getById($solicitudId);

            if (!$solicitud) {
                throw new ValidationException('Solicitud no encontrada');
            }

            $uploadedDocuments = [];
            $solicitudDir = "solicitudes/{$solicitudId}";

            foreach ($documents as $tipo => $file) {
                if ($file instanceof UploadedFile) {
                    // Validate file
                    $this->validateDocumentFile($file);

                    // Store file
                    $filename = $this->generateDocumentFilename($file, $tipo);
                    $path = $file->storeAs($solicitudDir, $filename, 'public');

                    $uploadedDocuments[$tipo] = [
                        'nombre_original' => $file->getClientOriginalName(),
                        'nombre_guardado' => $filename,
                        'ruta' => $path,
                        'tipo_mime' => $file->getMimeType(),
                        'tamano_bytes' => $file->getSize(),
                        'fecha_subida' => now()->toISOString()
                    ];
                }
            }

            // Update solicitud with documents
            $documentosExistentes = json_decode($solicitud->documentos ?? '[]', true);
            $nuevosDocumentos = array_merge($documentosExistentes, $uploadedDocuments);

            $solicitud->update(['documentos' => json_encode($nuevosDocumentos)]);

            $this->log('Documents uploaded successfully', [
                'solicitud_id' => $solicitudId,
                'documents_count' => count($uploadedDocuments)
            ]);

            return $uploadedDocuments;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'subida de documentos');
            throw new \Exception('Error al subir documentos: ' . $e->getMessage());
        }
    }

    /**
     * Get available transitions for solicitud.
     */
    public function getAvailableTransitions(string $solicitudId): array
    {
        try {
            $solicitud = $this->getById($solicitudId);

            if (!$solicitud) {
                return [];
            }

            // Define available transitions based on current state
            $transitions = match ($solicitud->estado) {
                'PENDIENTE' => ['EN_REVISION', 'RECHAZADO'],
                'EN_REVISION' => ['APROBADO', 'RECHAZADO', 'REQUIERE_INFO'],
                'APROBADO' => ['FINALIZADO', 'CANCELADO'],
                'RECHAZADO' => ['POSTULADO'],
                'REQUIERE_INFO' => ['EN_REVISION', 'RECHAZADO'],
                default => []
            };

            return $transitions;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de transiciones disponibles');
            return [];
        }
    }

    /**
     * Get solicitudes by date range.
     */
    public function getByDateRange(string $startDate, string $endDate, int $skip = 0, int $limit = 50): array
    {
        try {
            $solicitudes = SolicitudCredito::whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->skip($skip)
                ->limit($limit)
                ->get();

            $total = SolicitudCredito::whereBetween('created_at', [$startDate, $endDate])->count();

            return [
                'solicitudes' => $this->transformCollectionForApi($solicitudes),
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'total' => $total,
                    'has_more' => ($skip + $limit) < $total
                ]
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de solicitudes por rango de fechas');
            return [
                'solicitudes' => [],
                'pagination' => ['skip' => $skip, 'limit' => $limit, 'total' => 0, 'has_more' => false]
            ];
        }
    }

    /**
     * Validate uploaded document file.
     */
    private function validateDocumentFile(UploadedFile $file): void
    {
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if (!in_array($file->getMimeType(), $allowedTypes)) {
            throw new ValidationException('Tipo de archivo no permitido. Solo se aceptan PDF, JPG y PNG.');
        }

        if ($file->getSize() > $maxSize) {
            throw new ValidationException('El archivo excede el tamaño máximo permitido de 10MB.');
        }
    }

    /**
     * Generate document filename.
     */
    private function generateDocumentFilename(UploadedFile $file, string $tipo): string
    {
        $timestamp = now()->format('YmdHis');
        $extension = $file->getClientOriginalExtension();
        return "{$tipo}_{$timestamp}.{$extension}";
    }

    /**
     * Transform solicitud for API response.
     */
    public function transformForApi($solicitud): array
    {
        return [
            'id' => $solicitud->id,
            'numero_solicitud' => $solicitud->numero_solicitud,
            'owner_username' => $solicitud->owner_username,
            'monto_solicitado' => $solicitud->monto_solicitado,
            'monto_solicitado_formatted' => number_format($solicitud->monto_solicitado, 0, ',', '.'),
            'monto_aprobado' => $solicitud->monto_aprobado,
            'monto_aprobado_formatted' => $solicitud->monto_aprobado ? number_format($solicitud->monto_aprobado, 0, ',', '.') : null,
            'plazo_meses' => $solicitud->plazo_meses,
            'tasa_interes' => $solicitud->tasa_interes,
            'destino_credito' => $solicitud->destino_credito,
            'descripcion' => $solicitud->descripcion,
            'estado' => $solicitud->estado,
            'estado_label' => match ($solicitud->estado) {
                'PENDIENTE' => 'Pendiente',
                'EN_REVISION' => 'En Revisión',
                'APROBADO' => 'Aprobado',
                'RECHAZADO' => 'Rechazado',
                'FINALIZADO' => 'Finalizado',
                'CANCELADO' => 'Cancelado',
                'REQUIERE_INFO' => 'Requiere Información',
                default => $solicitud->estado
            },
            'documentos' => json_decode($solicitud->documentos ?? '[]', true),
            'created_at' => $solicitud->created_at->toISOString(),
            'updated_at' => $solicitud->updated_at->toISOString(),
            'requires_action' => in_array($solicitud->estado, ['REQUIERE_INFO']),
            'is_final_state' => in_array($solicitud->estado, ['APROBADO', 'RECHAZADO', 'FINALIZADO', 'CANCELADO']),
            'can_be_modified' => !in_array($solicitud->estado, ['APROBADO', 'RECHAZADO', 'FINALIZADO', 'CANCELADO'])
        ];
    }

    /**
     * Get solicitud by ID.
     */
    public function getById(string $id): ?SolicitudCredito
    {
        try {
            return SolicitudCredito::find($id);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de solicitud');
            return null;
        }
    }

    /**
     * Transform collection for API response.
     */
    public function transformCollectionForApi($collection): array
    {
        return $collection->map(fn($solicitud) => $this->transformForApi($solicitud))->toArray();
    }

    /**
     * Find solicitud by ID.
     */
    public function findById(int $id): ?SolicitudCredito
    {
        try {
            return SolicitudCredito::find($id);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de solicitud');
            return null;
        }
    }

    /**
     * Find solicitud by number.
     */
    public function findByNumero(string $numero): ?SolicitudCredito
    {
        try {
            return SolicitudCredito::where('numero_solicitud', $numero)->first();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda por número');
            return null;
        }
    }

    /**
     * Update solicitud.
     */
    public function update(int $id, array $data): bool
    {
        try {
            $solicitud = SolicitudCredito::find($id);
            if (!$solicitud) {
                return false;
            }
            return $solicitud->update($data);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'actualización de solicitud');
            return false;
        }
    }

    /**
     * Update solicitud status.
     */
    public function updateEstado(int $id, string $estado): bool
    {
        try {
            $solicitud = SolicitudCredito::find($id);
            if (!$solicitud) {
                return false;
            }
            return $solicitud->update(['estado' => $estado]);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'actualización de estado');
            return false;
        }
    }

    /**
     * Delete solicitud.
     */
    public function delete(int $id): bool
    {
        try {
            $solicitud = SolicitudCredito::find($id);
            if (!$solicitud) {
                return false;
            }
            return $solicitud->delete();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'eliminación de solicitud');
            return false;
        }
    }

    /**
     * Get solicitudes by user.
     */
    public function getByUser(string $username): \Illuminate\Support\Collection
    {
        try {
            return SolicitudCredito::where('owner_username', $username)
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de solicitudes por usuario');
            return collect([]);
        }
    }

    /**
     * Get solicitudes by status.
     */
    public function getByEstado(string $estado): \Illuminate\Support\Collection
    {
        try {
            return SolicitudCredito::where('estado', $estado)
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de solicitudes por estado');
            return collect([]);
        }
    }

    /**
     * Get all solicitudes.
     */
    public function getAll(): \Illuminate\Support\Collection
    {
        try {
            return SolicitudCredito::orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de solicitudes');
            return collect([]);
        }
    }

    /**
     * Generate unique solicitud number.
     */
    private function generarNumeroSolicitud(): string
    {
        do {
            $numero = 'SOL-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (SolicitudCredito::where('numero_solicitud', $numero)->exists());

        return $numero;
    }
}
