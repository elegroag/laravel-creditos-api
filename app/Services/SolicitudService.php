<?php

namespace App\Services;

use App\Models\SolicitudCredito;
use App\Models\NumeroSolicitud;
use App\Models\EstadoSolicitud;
use App\Exceptions\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
     * Search solicitudes by term with filters.
     */
    public function buscar(string $termino, int $limit = 50, ?string $estado = null, ?string $username = null): array
    {
        try {
            $query = SolicitudCredito::query();

            // Filter by username if provided
            if ($username) {
                $query->where('owner_username', $username);
            }

            // Filter by estado if provided
            if ($estado) {
                $query->where('estado', $estado);
            }

            // Search in multiple fields
            $query->where(function ($q) use ($termino) {
                $q->where('numero_solicitud', 'like', '%' . $termino . '%')
                    ->orWhere('owner_username', 'like', '%' . $termino . '%')
                    ->orWhere('numero_documento', 'like', '%' . $termino . '%')
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(payload, "$.solicitud.tipcre")) LIKE ?', ['%' . $termino . '%'])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(payload, "$.postulante.primer_nombre")) LIKE ?', ['%' . $termino . '%'])
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(payload, "$.postulante.primer_apellido")) LIKE ?', ['%' . $termino . '%']);
            });

            // Order by relevance (numero_solicitud first, then created_at)
            $query->orderByRaw('CASE WHEN numero_solicitud LIKE ? THEN 1 ELSE 2 END', ['%' . $termino . '%'])
                ->orderBy('created_at', 'desc');

            // Apply limit
            $solicitudes = $query->limit($limit)->get();

            return [
                'solicitudes' => $solicitudes->toArray(),
                'count' => $solicitudes->count(),
                'termino' => $termino,
                'estado' => $estado,
                'username' => $username,
                'limit' => $limit
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de solicitudes');
            return [
                'solicitudes' => [],
                'count' => 0,
                'termino' => $termino,
                'estado' => $estado,
                'username' => $username,
                'limit' => $limit
            ];
        }
    }

    /**
     * Get statistics for solicitudes.
     */
    public function getEstadisticas(?string $username = null): array
    {
        try {
            $query = SolicitudCredito::query();

            // Filter by username if provided
            if ($username) {
                $query->where('owner_username', $username);
            }

            // Total solicitudes
            $total = $query->count();

            // By estado
            $estadosQuery = SolicitudCredito::query();
            if ($username) {
                $estadosQuery->where('owner_username', $username);
            }
            $porEstado = $estadosQuery->selectRaw('estado, COUNT(*) as count')
                ->groupBy('estado')
                ->pluck('count', 'estado')
                ->toArray();

            // By month (last 6 months)
            $mesesQuery = SolicitudCredito::query();
            if ($username) {
                $mesesQuery->where('owner_username', $username);
            }
            $porMes = $mesesQuery->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mes, COUNT(*) as count')
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('mes')
                ->orderBy('mes')
                ->pluck('count', 'mes')
                ->toArray();

            // By tipo credito
            $tiposQuery = SolicitudCredito::query();
            if ($username) {
                $tiposQuery->where('owner_username', $username);
            }
            $porTipo = $tiposQuery->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(payload, "$.solicitud.tipcre")) as tipo, COUNT(*) as count')
                ->whereNotNull('payload')
                ->groupBy('tipo')
                ->orderBy('count', 'desc')
                ->pluck('count', 'tipo')
                ->toArray();

            return [
                'total' => $total,
                'por_estado' => $porEstado,
                'por_mes' => $porMes,
                'por_tipo' => $porTipo,
                'username' => $username
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de estadísticas de solicitudes');
            return [
                'total' => 0,
                'por_estado' => [],
                'por_mes' => [],
                'por_tipo' => [],
                'username' => $username
            ];
        }
    }

    /**
     * Get available estados for solicitudes.
     */
    public function getEstadosDisponibles(): array
    {
        try {
            // Get distinct estados from database
            $estados = SolicitudCredito::distinct('estado')
                ->pluck('estado')
                ->filter()
                ->toArray();

            // Default estados if none found
            if (empty($estados)) {
                $estados = [
                    'NUEVA',
                    'ENVIADO_VALIDACION',
                    'EN_VALIDACION',
                    'ENVIADO_PENDIENTE_APROBACION',
                    'PENDIENTE_APROBACION',
                    'APROBADA',
                    'RECHAZADA',
                    'DESESTIMADA',
                    'CANCELADA',
                    'DESISTE'
                ];
            }

            return $estados;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de estados disponibles');
            return [
                'NUEVA',
                'ENVIADO_VALIDACION',
                'EN_VALIDACION',
                'ENVIADO_PENDIENTE_APROBACION',
                'PENDIENTE_APROBACION',
                'APROBADA',
                'RECHAZADA',
                'DESESTIMADA',
                'CANCELADA',
                'DESISTE'
            ];
        }
    }

    /**
     * List all solicitudes with pagination and filters.
     */
    public function list(int $skip = 0, int $limit = 50, array $filters = []): array
    {
        try {
            $query = SolicitudCredito::query();

            // Apply filters if provided
            if (!empty($filters)) {
                foreach ($filters as $field => $value) {
                    if (is_array($value)) {
                        $query->whereIn($field, $value);
                    } else {
                        $query->where($field, $value);
                    }
                }
            }

            // Apply ordering
            $query->orderBy('created_at', 'desc');

            // Apply pagination
            $solicitudes = $query->skip($skip)->limit($limit)->get();

            return [
                'solicitudes' => $solicitudes->toArray(),
                'count' => $solicitudes->count(),
                'skip' => $skip,
                'limit' => $limit
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'listado de solicitudes');
            return [
                'solicitudes' => [],
                'count' => 0,
                'skip' => $skip,
                'limit' => $limit
            ];
        }
    }

    /**
     * Advanced search for solicitudes with multiple filters.
     */
    public function advancedSearch(array $filters, int $skip = 0, int $limit = 50): array
    {
        try {
            $query = SolicitudCredito::query();

            // Filter by owner username
            if (isset($filters['owner_username'])) {
                $query->where('owner_username', $filters['owner_username']);
            }

            // Filter by estado
            if (isset($filters['estado'])) {
                $query->where('estado', $filters['estado']);
            }

            // Filter by multiple estados
            if (isset($filters['estados']) && is_array($filters['estados'])) {
                $query->whereIn('estado', $filters['estados']);
            }

            // Filter by numero_solicitud
            if (isset($filters['numero_solicitud'])) {
                $query->where('numero_solicitud', 'like', '%' . $filters['numero_solicitud'] . '%');
            }

            // Filter by numero_documento
            if (isset($filters['numero_documento'])) {
                $query->where('numero_documento', $filters['numero_documento']);
            }

            // Filter by nombre_usuario
            if (isset($filters['nombre_usuario'])) {
                $query->where('owner_username', 'like', '%' . $filters['nombre_usuario'] . '%');
            }

            // Filter by monto range
            if (isset($filters['monto_minimo'])) {
                $query->where('monto_solicitado', '>=', $filters['monto_minimo']);
            }
            if (isset($filters['monto_maximo'])) {
                $query->where('monto_solicitado', '<=', $filters['monto_maximo']);
            }

            // Apply ordering
            $ordenarPor = $filters['ordenar_por'] ?? 'created_at';
            $ordenDireccion = $filters['orden_direccion'] ?? 'desc';
            $query->orderBy($ordenarPor, $ordenDireccion);

            // Apply pagination
            $solicitudes = $query->skip($skip)->limit($limit)->get();

            return $solicitudes->toArray();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda avanzada de solicitudes');
            return [];
        }
    }

    /**
     * Get solicitudes by owner.
     */
    public function getByOwner(string $username, int $skip = 0, int $limit = 50, ?string $estado = null): array
    {
        try {
            $query = SolicitudCredito::where('owner_username', $username);

            // Filter by estado if provided
            if ($estado) {
                $query->where('estado', $estado);
            }

            $solicitudes = $query->orderBy('created_at', 'desc')
                ->skip($skip)
                ->limit($limit)
                ->get();

            return [
                'solicitudes' => $solicitudes->toArray(),
                'count' => $solicitudes->count()
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de solicitudes por propietario');
            return [
                'solicitudes' => [],
                'count' => 0
            ];
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

    public function contarSolicitudesPorEstado(?string $username = null): array
    {
        try {
            $query = SolicitudCredito::query();

            if ($username) {
                $query->where('owner_username', $username);
            }

            $resultados = $query->select('estado', DB::raw('count(*) as count'))
                ->groupBy('estado')
                ->get()
                ->toArray();

            return $resultados;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'conteo de solicitudes por estado');
            return [];
        }
    }


    public function listarSolicitudesCreditoPaginado($limit, $offset, $estado, ?string $username = null): array
    {
        try {
            $query = SolicitudCredito::query();

            if ($username) {
                $query->where('owner_username', $username);
            }

            if ($estado) {
                $query->where('estado', $estado);
            }

            $resultados = $query->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get()
                ->toArray();

            return $resultados;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'listado de solicitudes paginadas');
            return [];
        }
    }

    /**
     * Generar número de solicitud si es necesario
     */
    public function generarNumeroSolicitudSiEsNecesario(array &$solicitudPayload): string
    {
        $numeroSolicitud = $solicitudPayload['numero_solicitud'] ?? '';
        $numeroSolicitudService = new NumeroSolicitudService();

        if (!is_string($numeroSolicitud) || empty(trim($numeroSolicitud))) {
            // Obtener línea de crédito para generar el número
            $lineaCredito = $solicitudPayload['tipcre'] ?? '03';

            if (is_string($lineaCredito) && !empty(trim($lineaCredito))) {
                $numeroSolicitud = $numeroSolicitudService->generarNumeroSolicitud(trim($lineaCredito));
                $solicitudPayload['numero_solicitud'] = $numeroSolicitud;
            } else {
                $numeroSolicitud = '';
            }
        } else {
            $numeroSolicitud = trim($numeroSolicitud);
        }

        return $numeroSolicitud;
    }

    /**
     * Guardar solicitud en base de datos
     */
    public function guardarSolicitudEnBaseDatos(array $data, string $numeroSolicitud, string $username): string
    {
        $now = Carbon::now();

        // Preparar datos del solicitante
        $solicitantePayload = $data['solicitante'] ?? [];
        $solicitudPayload = $data['solicitud'] ?? [];

        $montoSolicitado = $solicitudPayload['valor_solicitado'] ?? $solicitudPayload['valor_solicitud'] ?? 0;

        $plazoMeses = $solicitudPayload['plazo_meses'] ?? 0;
        $estado = 'POSTULADO';

        // Buscar solicitud existente
        $query = SolicitudCredito::where('owner_username', $username);

        if (!empty($numeroSolicitud)) {
            $query->where('numero_solicitud', $numeroSolicitud);
        }

        $existing = $query->first(['numero_solicitud', 'estado']);
        $estadoDoc = $existing?->estado ?? $estado;

        // Preparar datos para actualización/creación
        $updateData = [
            'owner_username' => $username,
            'numero_solicitud' => $numeroSolicitud,
            'monto_solicitado' => $montoSolicitado,
            'plazo_meses' => is_numeric($plazoMeses) ? (int)$plazoMeses : 0,
            'solicitante' => [
                'tipo_identificacion' => $solicitantePayload['tipo_identificacion'] ?? null,
                'numero_identificacion' => $solicitantePayload['numero_identificacion'] ?? null,
                'nombres_apellidos' => $solicitantePayload['nombres_apellidos'] ?? null,
                'email' => $solicitantePayload['email'] ?? null,
                'telefono_movil' => $solicitantePayload['telefono_movil'] ?? null,
            ],
            'xml_filename' => '',
            'payload' => $data,
            'updated_at' => $now,
            'tasa_interes' => 0.10,
        ];

        if ($existing) {
            // Actualizar solicitud existente
            $existing->update($updateData);

            // Agregar al timeline
            $timeline = $existing->timeline ?? [];
            $timeline[] = [
                'estado' => $estadoDoc,
                'fecha' => $now->toISOString(),
                'detalle' => 'Actualización por guardado de solicitud'
            ];
            $existing->update(['timeline' => $timeline]);
        } else {
            // Crear nueva solicitud
            $updateData['created_at'] = $now;
            $updateData['documentos'] = [];
            $updateData['estado'] = $estado;
            $updateData['timeline'] = [
                [
                    'estado' => $estadoDoc,
                    'fecha' => $now->toISOString(),
                    'detalle' => 'Creación por guardado de solicitud'
                ]
            ];

            $solicitud = SolicitudCredito::create($updateData);
        }

        return $solicitud->numero_solicitud;
    }
}
