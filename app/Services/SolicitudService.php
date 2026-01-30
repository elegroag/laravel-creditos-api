<?php

namespace App\Services;

use App\Models\SolicitudCredito;
use App\Models\NumeroSolicitud;
use App\Models\EstadoSolicitud;
use App\Repositories\BaseRepository;
use App\Validators\SolicitudValidators;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class SolicitudService extends BaseService
{
    protected BaseRepository $estadoRepository;

    public function __construct(BaseRepository $solicitudRepository, BaseRepository $estadoRepository = null)
    {
        parent::__construct($solicitudRepository);
        $this->estadoRepository = $estadoRepository ?: new BaseRepository(new EstadoSolicitud());
    }

    /**
     * Create a new solicitud.
     */
    public function create(array $data, string $ownerUsername): SolicitudCredito
    {
        // Validate data
        $validator = SolicitudValidators::validateCreate($data);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }

        $validated = $validator->validated();

        try {
            // Create solicitud with automatic number generation
            $solicitud = SolicitudCredito::createWithNumber(array_merge($validated, [
                'owner_username' => $ownerUsername,
                'estado_codigo' => 'POSTULADO'
            ]));

            // Add initial timeline event
            $solicitud->addTimelineEntry('POSTULADO', 'Creación de solicitud', $ownerUsername);

            $this->log('Solicitud created successfully', [
                'solicitud_id' => $solicitud->numero_solicitud,
                'numero_solicitud' => $solicitud->numero_solicitud,
                'owner_username' => $ownerUsername
            ]);

            return $solicitud;
        } catch (\Exception $e) {
            $this->logError('Error creating solicitud', ['error' => $e->getMessage()]);
            throw new \Exception('Error al crear solicitud: ' . $e->getMessage());
        }
    }

    /**
     * Get solicitud by ID.
     */
    public function getById(string $id): ?SolicitudCredito
    {
        try {
            return $this->repository->findById($id);
        } catch (\Exception $e) {
            $this->logError('Error getting solicitud by ID', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get solicitudes by owner.
     */
    public function getByOwner(string $ownerUsername, int $skip = 0, int $limit = 50, ?string $estado = null): array
    {
        try {
            $query = SolicitudCredito::where('owner_username', $ownerUsername);

            if ($estado) {
                $query->where('estado_codigo', $estado);
            }

            $solicitudes = $query->orderBy('created_at', 'desc')
                ->skip($skip)
                ->limit($limit)
                ->get();

            $total = SolicitudCredito::where('owner_username', $ownerUsername)
                ->when($estado, fn($q) => $q->where('estado_codigo', $estado))
                ->count();

            return [
                'solicitudes' => $solicitudes->toArray(),
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'total' => $total,
                    'has_more' => ($skip + $limit) < $total
                ]
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting solicitudes by owner', ['owner_username' => $ownerUsername, 'error' => $e->getMessage()]);
            return [
                'solicitudes' => [],
                'pagination' => ['skip' => $skip, 'limit' => $limit, 'total' => 0, 'has_more' => false]
            ];
        }
    }

    /**
     * Update solicitud.
     */
    public function update(string $id, array $data): SolicitudCredito
    {
        // Validate data
        $validator = SolicitudValidators::validateUpdate($data);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }

        $validated = $validator->validated();

        // Ensure solicitud exists
        $solicitud = $this->ensureExists($id, 'Solicitud');

        try {
            $solicitud->update($validated);

            $this->log('Solicitud updated successfully', [
                'solicitud_id' => $id,
                'changes' => array_keys($validated)
            ]);

            return $solicitud->fresh();
        } catch (\Exception $e) {
            $this->logError('Error updating solicitud', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \Exception('Error al actualizar solicitud: ' . $e->getMessage());
        }
    }

    /**
     * Update solicitud estado.
     */
    public function updateEstado(string $id, string $nuevoEstado, string $detalle = 'Cambio de estado', ?string $usuario = null): SolicitudCredito
    {
        // Ensure solicitud exists
        $solicitud = $this->ensureExists($id, 'Solicitud');

        try {
            $solicitud->updateEstadoValidado($nuevoEstado, $detalle, $usuario);

            $this->log('Solicitud estado updated', [
                'solicitud_id' => $id,
                'estado_anterior' => $solicitud->getOriginal('estado'),
                'estado_nuevo' => $nuevoEstado,
                'detalle' => $detalle,
                'usuario' => $usuario
            ]);

            return $solicitud->fresh();
        } catch (\Exception $e) {
            $this->logError('Error updating solicitud estado', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \Exception('Error al actualizar estado: ' . $e->getMessage());
        }
    }

    /**
     * Delete solicitud (soft delete - change to DESISTE).
     */
    public function delete(string $id): bool
    {
        // Ensure solicitud exists
        $solicitud = $this->ensureExists($id, 'Solicitud');

        try {
            // Change state to DESISTE instead of physical deletion
            $this->updateEstado($id, 'DESISTE', 'Solicitud eliminada por el usuario');

            $this->log('Solicitud deleted (DESISTE)', ['solicitud_id' => $id]);

            return true;
        } catch (\Exception $e) {
            $this->logError('Error deleting solicitud', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \Exception('Error al eliminar solicitud: ' . $e->getMessage());
        }
    }

    /**
     * List solicitudes with filters.
     */
    public function list(int $skip = 0, int $limit = 50, array $filters = []): array
    {
        try {
            $query = SolicitudCredito::query();

            // Apply filters
            if (isset($filters['estado'])) {
                $query->where('estado_codigo', $filters['estado']);
            }

            if (isset($filters['numero_solicitud'])) {
                $query->where('numero_solicitud', 'like', '%' . $filters['numero_solicitud'] . '%');
            }

            if (isset($filters['owner_username'])) {
                $query->where('owner_username', $filters['owner_username']);
            }

            if (isset($filters['numero_documento'])) {
                $query->whereHas('solicitante', function ($q) use ($filters) {
                    $q->where('numero_documento', 'like', '%' . $filters['numero_documento'] . '%');
                });
            }

            if (isset($filters['nombre_usuario'])) {
                $query->whereHas('solicitante', function ($q) use ($filters) {
                    $q->whereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')) like ?", ['%' . $filters['nombre_usuario'] . '%']);
                });
            }

            // Apply sorting
            $orderBy = $filters['ordenar_por'] ?? 'created_at';
            $orderDirection = $filters['orden_direccion'] ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            $solicitudes = $query->skip($skip)->limit($limit)->get();
            $total = $query->count();

            return [
                'solicitudes' => $solicitudes->toArray(),
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'total' => $total,
                    'has_more' => ($skip + $limit) < $total
                ]
            ];
        } catch (\Exception $e) {
            $this->logError('Error listing solicitudes', ['error' => $e->getMessage()]);
            return [
                'solicitudes' => [],
                'pagination' => ['skip' => $skip, 'limit' => $limit, 'total' => 0, 'has_more' => false]
            ];
        }
    }

    /**
     * Advanced search with multiple filters.
     */
    public function advancedSearch(array $filters, int $skip = 0, int $limit = 50): array
    {
        try {
            $query = SolicitudCredito::query();

            // Multiple estados filter
            if (isset($filters['estados']) && is_array($filters['estados'])) {
                if (count($filters['estados']) === 1) {
                    $query->where('estado_codigo', $filters['estados'][0]);
                } else {
                    $query->whereIn('estado_codigo', $filters['estados']);
                }
            }

            // Document number filter
            if (isset($filters['numero_documento'])) {
                $query->whereHas('solicitante', function ($q) use ($filters) {
                    $q->where('numero_documento', 'like', '%' . $filters['numero_documento'] . '%');
                });
            }

            // Name filter
            if (isset($filters['nombre_usuario'])) {
                $query->whereHas('solicitante', function ($q) use ($filters) {
                    $q->whereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')) like ?", ['%' . $filters['nombre_usuario'] . '%']);
                });
            }

            // Owner filter
            if (isset($filters['owner_username'])) {
                $query->where('owner_username', $filters['owner_username']);
            }

            // Amount range filter
            if (isset($filters['monto_min'])) {
                $query->where('monto_solicitado', '>=', $filters['monto_min']);
            }
            if (isset($filters['monto_max'])) {
                $query->where('monto_solicitado', '<=', $filters['monto_max']);
            }

            // Date range filter
            if (isset($filters['fecha_desde'])) {
                $query->where('created_at', '>=', $filters['fecha_desde']);
            }
            if (isset($filters['fecha_hasta'])) {
                $query->where('created_at', '<=', $filters['fecha_hasta']);
            }

            // Sorting
            $orderBy = $filters['ordenar_por'] ?? 'created_at';
            $orderDirection = $filters['orden_direccion'] ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            $solicitudes = $query->skip($skip)->limit($limit)->get();
            $total = $query->count();

            return [
                'solicitudes' => $solicitudes->toArray(),
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'total' => $total,
                    'has_more' => ($skip + $limit) < $total
                ]
            ];
        } catch (\Exception $e) {
            $this->logError('Error in advanced search', ['error' => $e->getMessage()]);
            return [
                'solicitudes' => [],
                'pagination' => ['skip' => $skip, 'limit' => $limit, 'total' => 0, 'has_more' => false]
            ];
        }
    }

    /**
     * Get solicitud statistics.
     */
    public function getStatistics(): array
    {
        try {
            $total = SolicitudCredito::count();

            $byEstado = SolicitudCredito::join('estados_solicitud', 'solicitudes_credito.estado_codigo', '=', 'estados_solicitud.codigo')
                ->groupBy('estados_solicitud.codigo', 'estados_solicitud.nombre')
                ->selectRaw('estados_solicitud.codigo as _id, COUNT(*) as count')
                ->orderBy('count', 'desc')
                ->pluck('count', '_id')
                ->toArray();

            $byOwner = SolicitudCredito::groupBy('owner_username')
                ->selectRaw('owner_username as _id, COUNT(*) as count')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', '_id')
                ->toArray();

            return [
                'total' => $total,
                'by_estado' => $byEstado,
                'by_owner' => $byOwner
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting solicitud statistics', ['error' => $e->getMessage()]);
            return [
                'total' => 0,
                'by_estado' => [],
                'by_owner' => []
            ];
        }
    }

    /**
     * Upload documents for solicitud.
     */
    public function uploadDocuments(string $solicitudId, array $documents): array
    {
        $solicitud = $this->ensureExists($solicitudId, 'Solicitud');

        try {
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
            $documentosExistentes = $solicitud->documentos()->where('activo', true)->get()->keyBy('tipo_documento')->toArray();
            $nuevosDocumentos = array_merge($documentosExistentes, $uploadedDocuments);

            // Update existing documents and create new ones
            foreach ($uploadedDocuments as $tipo => $documentoData) {
                $solicitud->documentos()->updateOrCreate(
                    ['tipo_documento' => $tipo],
                    array_merge($documentoData, ['activo' => true])
                );
            }

            $this->log('Documents uploaded successfully', [
                'solicitud_id' => $solicitudId,
                'documents_count' => count($uploadedDocuments)
            ]);

            return $uploadedDocuments;
        } catch (\Exception $e) {
            $this->logError('Error uploading documents', ['solicitud_id' => $solicitudId, 'error' => $e->getMessage()]);
            throw new \Exception('Error al subir documentos: ' . $e->getMessage());
        }
    }

    /**
     * Get available transitions for solicitud.
     */
    public function getAvailableTransitions(string $solicitudId): array
    {
        $solicitud = $this->ensureExists($solicitudId, 'Solicitud');

        try {
            return $solicitud->available_transitions;
        } catch (\Exception $e) {
            $this->logError('Error getting available transitions', ['solicitud_id' => $solicitudId, 'error' => $e->getMessage()]);
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
                'solicitudes' => $solicitudes->toArray(),
                'pagination' => [
                    'skip' => $skip,
                    'limit' => $limit,
                    'total' => $total,
                    'has_more' => ($skip + $limit) < $total
                ]
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting solicitudes by date range', ['error' => $e->getMessage()]);
            return [
                'solicitudes' => [],
                'pagination' => ['skip' => $skip, 'limit' => $limit, 'total' => 0, 'has_more' => false]
            ];
        }
    }

    /**
     * Transform solicitud for API response.
     */
    public function transformForApi($solicitud): array
    {
        $estado = $solicitud->estado;
        $solicitante = $solicitud->solicitante;

        return [
            'id' => $solicitud->numero_solicitud,
            'numero_solicitud' => $solicitud->numero_solicitud,
            'owner_username' => $solicitud->owner_username,
            'monto_solicitado' => $solicitud->monto_solicitado,
            'monto_solicitado_formatted' => $solicitud->monto_solicitado_formatted,
            'monto_aprobado' => $solicitud->monto_aprobado,
            'monto_aprobado_formatted' => $solicitud->monto_aprobado_formatted,
            'plazo_meses' => $solicitud->plazo_meses,
            'tasa_interes' => $solicitud->tasa_interes,
            'destino_credito' => $solicitud->destino_credito,
            'descripcion' => $solicitud->descripcion,
            'estado' => $solicitud->estado_codigo,
            'estado_label' => $estado?->nombre ?? $solicitud->estado_codigo,
            'estado_color' => $estado?->color ?? '#6B7280',
            'estado_metadata' => [
                'codigo' => $solicitud->estado_codigo,
                'nombre' => $estado?->nombre,
                'color' => $estado?->color,
                'orden' => $estado?->orden
            ],
            'solicitante' => $solicitante ? $solicitante->toApiArray() : null,
            'solicitante_full_name' => $solicitante?->nombre_completo,
            'solicitante_email' => $solicitante?->email,
            'solicitante_phone' => $solicitante?->telefono,
            'solicitante_document' => $solicitante?->numero_documento,
            'documentos' => $solicitud->documentos()->where('activo', true)->get()->map(fn($doc) => $doc->toApiArray())->toArray(),
            'timeline' => $solicitud->timeline()->with(['estado', 'usuario'])->orderBy('fecha', 'desc')->get()->map(fn($entry) => $entry->toApiArray())->toArray(),
            'xml_filename' => $solicitud->xml_filename,
            'payload' => $solicitud->payload ? $solicitud->payload->toApiArray() : null,
            'created_at' => $solicitud->created_at->toISOString(),
            'updated_at' => $solicitud->updated_at->toISOString(),
            'requires_action' => $estado?->requiresAction() ?? false,
            'is_final_state' => $estado?->isFinal() ?? false,
            'is_active_state' => $estado?->isActive() ?? false,
            'can_be_modified' => !($estado?->isFinal() ?? false)
        ];
    }

    /**
     * Transform collection for API response.
     */
    public function transformCollectionForApi($solicitudes): array
    {
        return $solicitudes->map(fn($solicitud) => $this->transformForApi($solicitud))->toArray();
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
}
