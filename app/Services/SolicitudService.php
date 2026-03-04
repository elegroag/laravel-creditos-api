<?php

namespace App\Services;

use App\Exceptions\DebugException;
use App\Models\SolicitudCredito;
use App\Models\EstadoSolicitud;
use App\Models\SolicitudPayload;
use App\Models\SolicitudSolicitante;
use App\Models\SolicitudTimeline;
use App\Models\FirmanteSolicitud;
use App\Models\EmpresaConvenio;
use App\Exceptions\ValidationException;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SolicitudService extends EloquentService
{
    protected $externalApiService;

    public function __construct()
    {
        $this->externalApiService = new ExternalApiService();
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
        // Get distinct estados from database
        return  EstadoSolicitud::all()
            ->pluck('nombre')
            ->filter()
            ->toArray();
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
            $solicitudes = $query->skip($skip)->limit($limit)->with('payload')->get();

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
                $query->where('valor_solicitud', '>=', $filters['monto_minimo']);
            }
            if (isset($filters['monto_maximo'])) {
                $query->where('valor_solicitud', '<=', $filters['monto_maximo']);
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
                ->with('payload')
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
     * List solicitudes in a lightweight summary format (no payload relation).
     */
    public function listResumen(int $skip = 0, int $limit = 20, array $filters = []): array
    {
        try {
            $query = SolicitudCredito::query();

            if (!empty($filters)) {
                foreach ($filters as $field => $value) {
                    if (is_array($value)) {
                        $query->whereIn($field, $value);
                    } else {
                        $query->where($field, $value);
                    }
                }
            }

            $query->orderBy('created_at', 'desc');

            $columns = [
                'numero_solicitud',
                'detalle_modalidad',
                'valor_solicitud',
                'estado',
                'created_at',
            ];

            $items = $query
                ->select($columns)
                ->skip($skip)
                ->limit($limit)
                ->get();

            return [
                'items' => $items->toArray(),
                'count' => $items->count(),
                'skip' => $skip,
                'limit' => $limit,
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'listado resumen de solicitudes');
            return [
                'items' => [],
                'count' => 0,
                'skip' => $skip,
                'limit' => $limit,
            ];
        }
    }

    /**
     * Get solicitudes by owner in a lightweight summary format (no payload relation).
     */
    public function getResumenByOwner(string $username, int $skip = 0, int $limit = 20): array
    {
        return $this->listResumen($skip, $limit, ['owner_username' => $username]);
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
     * Get solicitud by ID.
     */
    public function getById(string $key): ?SolicitudCredito
    {
        try {
            return SolicitudCredito::where("numero_solicitud", $key)
                ->with('payload', 'documentos', 'solicitante', 'timeline', 'firmantes')
                ->first();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de solicitud');
            return null;
        }
    }

    /**
     * Find solicitud by ID (numero_solicitud).
     */
    public function findById(string $id): ?SolicitudCredito
    {
        try {
            return SolicitudCredito::where('numero_solicitud', $id)->first();
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
     * Update solicitud by numero_solicitud.
     */
    public function update(string $id, array $data): bool
    {
        try {
            $solicitud = SolicitudCredito::where('numero_solicitud', $id)->first();
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
    public function updateEstado(string $id, string $estado): bool
    {
        try {
            $solicitud = SolicitudCredito::where('numero_solicitud', $id)->first();
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
     * Delete solicitud by numero_solicitud.
     */
    public function delete(string $id): bool
    {
        try {
            $solicitud = SolicitudCredito::where('numero_solicitud', $id)->first();
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

            if ($estado && $estado !== '@') {
                $query->where('estado', $estado);
            }

            $resultados = $query->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->with("solicitante", "payload")
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
    public function generarNumeroSolicitudSiEsNecesario(?string $tipcre = null): string
    {
        $numeroSolicitudService = new NumeroSolicitudService();

        if (is_string($tipcre) && !empty(trim($tipcre))) {
            return $numeroSolicitudService->generarNumeroSolicitud(trim($tipcre));
        }

        return '';
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
        $lineaCredito = $data['linea_credito'] ?? [];

        $valorSolicitud = $solicitudPayload['valor_solicitud'] ?? 0;
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
            'valor_solicitud' => $valorSolicitud,
            'plazo_meses' => is_numeric($plazoMeses) ? (int)$plazoMeses : 0,
            'tasa_interes' => $lineaCredito['tasa_interes'] ?? 0,
            'estado' => $estadoDoc,
            'fecha_radicado' => $data['fecha_radicado'] ?? now(),
            'producto_tipo' => $solicitudPayload['producto_tipo'] ?? null,
            'ha_tenido_credito' => $solicitudPayload['ha_tenido_credito'] ?? null,
            'detalle_modalidad' => $solicitudPayload['detalle_modalidad'] ?? null,
            'tipo_credito' => $solicitudPayload['tipcre'] ?? null,
            'moneda' => $solicitudPayload['moneda'] ?? null,
            'cuota_mensual' => $solicitudPayload['cuota_mensual'] ?? null,
            'rol_en_solicitud' => $solicitudPayload['rol_en_solicitud'] ?? null,
            'updated_at' => $now,
            'pdf_generado' => null
        ];

        if ($existing) {
            // Actualizar solicitud existente
            $existing->update($updateData);

            // Actualizar SolicitudPayload
            $this->guardarOActualizarPayload($numeroSolicitud, $data);

            // Actualizar SolicitudSolicitante
            $this->guardarOActualizarSolicitante($numeroSolicitud, $solicitantePayload);

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
            $updateData['numero_solicitud'] = $numeroSolicitud;
            $updateData['created_at'] = $now;
            $updateData['documentos'] = [];
            $updateData['estado'] = $estado;
            $updateData['fecha_radicado'] = $data['fecha_radicado'] ?? now();

            $solicitud = SolicitudCredito::create($updateData);

            // Crear SolicitudPayload
            $this->guardarOActualizarPayload($numeroSolicitud, $data);

            // Crear SolicitudSolicitante
            $this->guardarOActualizarSolicitante($numeroSolicitud, $solicitantePayload);

            // Crear timeline inicial
            $this->crearTimelineInicial($numeroSolicitud, $username);

            // Registrar el firmante numero 1 que es el postulante
            $this->guardarFirmante($numeroSolicitud, $solicitantePayload);
        }

        return $solicitud->numero_solicitud;
    }

    /**
     * Guardar o actualizar SolicitudPayload
     */
    private function guardarOActualizarPayload(string $numeroSolicitud, array $data): void
    {
        $externalApiService = new ExternalApiService();
        $response = $externalApiService->post('/creditos/tipo-creditos');
        $isSuccess = ($response['status'] ?? true) && !isset($response['error']);

        $tasa_mes = 0;
        $tasa_facfin = 0;
        $tasa_facmor = 0;
        if ($isSuccess) {
            $collection = collect($response['data']);
            $lineaCredito = $collection->where('tipcre', $data['linea_credito']['tipcre'])->first();
            $categorias = $lineaCredito['categorias'];

            $collectionCategorias = collect($categorias);
            $categoria = $collectionCategorias->where('codcat', $data['solicitante']['codigo_categoria'])->first();
            $tasa_mes = round($categoria['facfin'] / 12, 2) - 0.01;
            $tasa_facfin = $categoria['facfin'];
            $tasa_facmor = $categoria['facmor'];
        }

        $payloadData = [
            'solicitud_id' => $numeroSolicitud,
            'version' => '1.0',
            'informacion_laboral' => $data['informacion_laboral'] ?? null,
            'ingresos_descuentos' => $data['ingresos_descuentos'] ?? null,
            'informacion_economica' => $data['informacion_economica'] ?? null,
            'propiedades' => $data['propiedades'] ?? null,
            'deudas' => $data['deudas'] ?? null,
            'referencias' => $data['referencias'] ?? null,
            'linea_credito' => [
                ...$data['linea_credito'],
                'tasa_mes' => $tasa_mes,
                'tasa_facfin' => $tasa_facfin,
                'tasa_facmor' => $tasa_facmor
            ],
        ];

        // Buscar payload existente
        $existingPayload = SolicitudPayload::where('solicitud_id', $numeroSolicitud)
            ->where('version', '1.0')
            ->first();

        if ($existingPayload) {
            $existingPayload->update($payloadData);
        } else {
            SolicitudPayload::create($payloadData);
        }
    }

    /**
     * Guardar o actualizar SolicitudSolicitante
     */
    private function guardarOActualizarSolicitante(string $numeroSolicitud, array $solicitantePayload): void
    {
        if (empty($solicitantePayload)) {
            return;
        }

        $antiguedadMeses = $solicitantePayload['antiguedad_meses'] ?? null;

        $solicitanteData = [
            'solicitud_id' => $numeroSolicitud,
            'tipo_persona' => $solicitantePayload['tipo_persona'] ?? 'natural',
            'tipo_documento' => $solicitantePayload['tipo_documento'] ?? null,
            'numero_documento' => $solicitantePayload['numero_documento'] ?? null,
            'nombres' => $solicitantePayload['nombres'] ?? null,
            'apellidos' => $solicitantePayload['apellidos'] ?? null,
            'razon_social' => $solicitantePayload['razon_social'] ?? null,
            'nit' => $solicitantePayload['nit'] ?? null,
            'fecha_nacimiento' => $solicitantePayload['fecha_nacimiento'] ?? null,
            'genero' => $solicitantePayload['genero'] ?? null,
            'estado_civil' => $solicitantePayload['estado_civil'] ?? null,
            'nivel_educativo' => $solicitantePayload['nivel_educativo'] ?? null,
            'profesion' => $solicitantePayload['profesion'] ?? null,
            'email' => $solicitantePayload['email'] ?? null,
            'telefono' => $solicitantePayload['telefono'] ?? null,
            'celular' => $solicitantePayload['celular'] ?? null,
            'direccion' => $solicitantePayload['direccion'] ?? null,
            'barrio' => $solicitantePayload['barrio'] ?? null,
            'ciudad' => $solicitantePayload['ciudad'] ?? null,
            'departamento' => $solicitantePayload['departamento'] ?? null,
            'cargo' => $solicitantePayload['cargo'] ?? null,
            'salario' => $solicitantePayload['salario'] ?? null,
            'antiguedad_meses' => $solicitantePayload['antiguedad_meses'] ?? $antiguedadMeses,
            'tipo_contrato' => $solicitantePayload['tipo_contrato'] ?? null,
            'sector_economico' => $solicitantePayload['sector_economico'] ?? null,
            'pais_residencia' => $solicitantePayload['pais_residencia'] ?? 'CO',
            'codigo_categoria' => $solicitantePayload['codigo_categoria'] ?? 'A',
        ];

        // Buscar solicitante existente
        $existingSolicitante = SolicitudSolicitante::where('solicitud_id', $numeroSolicitud)->first();

        if ($existingSolicitante) {
            $existingSolicitante->update($solicitanteData);
        } else {
            SolicitudSolicitante::create($solicitanteData);
        }
    }

    /**
     * Crear timeline inicial para nueva solicitud
     */
    private function crearTimelineInicial(string $numeroSolicitud, string $username): void
    {
        SolicitudTimeline::create([
            'solicitud_id' => $numeroSolicitud,
            'estado' => 'POSTULADO',
            'fecha' => Carbon::now(),
            'detalle' => "Solicitud {$numeroSolicitud} creada exitosamente en el sistema",
            'usuario_username' => $username,
            'automatico' => true
        ]);
    }

    private function guardarFirmante($solicitudId, $solicitante)
    {
        if (empty($solicitante)) {
            return;
        }

        try {
            // Guardar firmante 1: El postulante
            $this->guardarFirmantePostulante($solicitudId, $solicitante);

            // Guardar firmante 2: La empresa del convenio (si tiene NIT)
            $this->guardarFirmanteEmpresa($solicitudId, $solicitante);
        } catch (\Exception $e) {
            // Error silencioso - no interrumpir el flujo principal
        }
    }

    /**
     * Guardar el firmante postulante (orden 1)
     */
    private function guardarFirmantePostulante($solicitudId, $solicitante)
    {
        // Verificar si ya existe un firmante principal para esta solicitud
        $firmanteExistente = FirmanteSolicitud::where('solicitud_id', $solicitudId)
            ->where('orden', 1)
            ->first();

        if ($firmanteExistente) {
            // Actualizar el firmante existente
            $firmanteExistente->update([
                'nombre_completo' => $solicitante['nombres'] . ' ' . $solicitante['apellidos'] ?? null,
                'numero_documento' => $solicitante['numero_documento'] ?? null,
                'email' => $solicitante['email'] ?? null,
                'tipo' => $solicitante['tipo_persona'],
                'rol' => 'SOLICITANTE'
            ]);
        } else {
            // Crear nuevo firmante principal
            FirmanteSolicitud::create([
                'solicitud_id' => $solicitudId,
                'orden' => 1,
                'tipo' => $solicitante['tipo_persona'],
                'nombre_completo' => $solicitante['nombres'] . ' ' . $solicitante['apellidos'] ?? null,
                'numero_documento' => $solicitante['numero_documento'] ?? null,
                'email' => $solicitante['email'] ?? null,
                'rol' => 'SOLICITANTE'
            ]);
        }
    }

    /**
     * Guardar el firmante empresa del convenio (orden 2)
     */
    private function guardarFirmanteEmpresa($solicitudId, $solicitante)
    {
        $empresaNit = $solicitante['nit'] ?? null;

        if (empty($empresaNit)) {
            return;
        }

        // Buscar la empresa en convenios
        $empresaConvenio = EmpresaConvenio::where('nit', $empresaNit)
            ->where('estado', 'Activo')
            ->first();

        if (!$empresaConvenio) {
            return;
        }

        // Verificar si ya existe un firmante empresa para esta solicitud
        $firmanteExistente = FirmanteSolicitud::where('solicitud_id', $solicitudId)
            ->where('orden', 2)
            ->first();

        if ($firmanteExistente) {
            // Actualizar el firmante existente
            $firmanteExistente->update([
                'nombre_completo' => $empresaConvenio->razon_social,
                'numero_documento' => $empresaConvenio->nit,
                'email' => $empresaConvenio->correo,
                'tipo' => 'EMPRESA_CONVENIO',
                'rol' => 'EMPRESA_PATROCINADORA'
            ]);
        } else {
            // Crear nuevo firmante empresa
            FirmanteSolicitud::create([
                'solicitud_id' => $solicitudId,
                'orden' => 2,
                'tipo' => 'EMPRESA_CONVENIO',
                'nombre_completo' => $empresaConvenio->razon_social,
                'numero_documento' => $empresaConvenio->nit,
                'email' => $empresaConvenio->correo,
                'rol' => 'EMPRESA_PATROCINADORA'
            ]);
        }
    }

    /**
     * Create a new solicitud.
     */
    public function createSolicitudUseApi(string $solicitud_id): SolicitudCredito
    {
        $solicitud = SolicitudCredito::where('numero_solicitud', $solicitud_id)->first();
        if (!$solicitud) {
            throw new DebugException("Solicitud no encontrada {$solicitud_id}", 404);
        }

        $solicitante = $solicitud->solicitante;
        $payload = $solicitud->payload;

        // Datos principales de la solicitud
        $valorSolicitud = $solicitud->valor_solicitud ?? 0;
        $plazoMeses = $solicitud->plazo_meses ?? 0;
        $tasaInteres = $solicitud->tasa_interes ?? 0;
        $fechaRadicado = $solicitud->fecha_radicado?->format('Y-m-d') ?? date('Y-m-d');

        // Datos del solicitante
        $numeroDocumento = $solicitante->numero_documento ?? '';
        $codigoCategoria = $solicitante->codigo_categoria ?? '';
        $salario = $solicitante->salario ?? 0;
        $ciudad = $solicitante->ciudad ?? '';

        // Datos laborales y financieros del payload
        $datosLaborales = $payload->informacion_laboral ?? [];
        $ingresosDescuentos = $payload->ingresos_descuentos ?? [];
        $informacionEconomica = $payload->informacion_economica ?? [];

        // Mapear los datos de la solicitud al payload de la API
        $payloadApi = [
            'documento' => $numeroDocumento,
            'fecha' => $fechaRadicado,
            'ofiafi' => '01', // Oficina afiliada (valor por defecto)
            'usuario' => $solicitud->owner_username ?? 'SYSTEM',
            'numdoc' => $numeroDocumento,
            'codcat' => $codigoCategoria,
            'forpag' => 'M', // Forma de pago (M=Mensual)
            'pigsub' => 'N', // Pignoración subsidio (N=No)
            'sueldo' => $salario,
            'otring' => $ingresosDescuentos['otros_ingresos'] ?? 0,
            'otrcre' => $informacionEconomica['total_otros_creditos'] ?? 0,
            'cappag' => $valorSolicitud,
            'numcue' => $datosLaborales['numero_cuenta'] ?? '',
            'tipcue' => $datosLaborales['tipo_cuenta'] ?? 'A', // A=Ahorros
            'codcue' => $datosLaborales['codigo_cuenta'] ?? '',
            'mancat' => $codigoCategoria,
            'tipcre' => $solicitud->tipo_credito ?? 'CONSUMO',
            'perpag' => $plazoMeses,
            'facfin' => ($tasaInteres / 100), // Convertir a factor
            'nocts' => $plazoMeses,
            'nitseg' => $datosLaborales['nit_seguro'] ?? '',
            'facseg' => 0.01, // Factor seguro (1% por defecto)
            'valcre' => $valorSolicitud,
            'tipapr' => 'N', // Tipo aprobación (N=Normal)
            'tipinv' => 'N', // Tipo inversión (N=Normal)
            'estado' => $solicitud->estado ?? 'PENDIENTE',
            'fecrec' => $fechaRadicado,
            'usuest' => $solicitud->owner_username ?? 'SYSTEM',
            'fecest' => $fechaRadicado,
            'acta' => '',
            'modrec' => 'API',
            'valapr' => $valorSolicitud,
            'nota' => 'Solicitud generada via API',
            'migrado' => 'N',
            'operacion' => 'CREAR',
            'numcre' => '',
            'cancelado' => 'N',
            'aprseg' => 'N',
            'documentos' => json_encode($solicitud->documentos->pluck('tipo_documento')->toArray() ?? [])
        ];

        // Realizar petición a la API externa usando ExternalApiService
        $response = $this->externalApiService->post('/creditos/crear-solicitud', $payloadApi);

        // Verificar si la respuesta contiene error
        if (!$response['success'] ?? true || isset($response['error'])) {
            throw new DebugException("Error creando la solicitud mediante api", 502, null, [
                'api_error' => $response['error'] ?? 'Error desconocido',
                'api_status_code' => $response['status_code'] ?? null
            ]);
        }

        return $solicitud;
    }


    public function consultarSolicitudUseApi(string $solicitud_id): SolicitudCredito
    {
        $solicitud = SolicitudCredito::where('numero_solicitud', $solicitud_id)->first();
        if (!$solicitud) {
            throw new DebugException("Solicitud no encontrada {$solicitud_id}", 404);
        }

        // Realizar petición a la API externa usando ExternalApiService
        $response = $this->externalApiService->get('/creditos/consultar-solicitud/' . $solicitud_id);

        // Verificar si la respuesta contiene error
        if (!$response['success'] ?? true || isset($response['error'])) {
            throw new DebugException("Error consultando la solicitud mediante api", 502, null, [
                'api_error' => $response['error'] ?? 'Error desconocido',
                'api_status_code' => $response['status_code'] ?? null
            ]);
        }

        return $solicitud;
    }
}
