<?php

namespace App\Repositories;

use App\Models\SolicitudCredito;
use App\Models\EstadoSolicitud;
use App\Models\User;
use App\Models\SolicitudTimeline;
use App\Models\SolicitudDocumento;
use App\Models\SolicitudSolicitante;
use App\Models\SolicitudPayload;
use App\Exceptions\InvalidSolicitudStateError;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SolicitudRepository extends BaseRepository
{
    protected EstadoSolicitudRepository $estadoRepository;

    public function __construct(SolicitudCredito $solicitud)
    {
        parent::__construct($solicitud);
        $this->estadoRepository = new EstadoSolicitudRepository(new EstadoSolicitud());
    }

    /**
     * Create a new solicitud de crédito.
     */
    public function createSolicitud(array $solicitudData, string $ownerUsername): SolicitudCredito
    {
        // Obtener estado POSTULADO
        $estadoPostulado = $this->estadoRepository->getEstadoPostulado();
        if (!$estadoPostulado) {
            throw new \Exception("Estado POSTULADO no encontrado en la base de datos");
        }

        // Generar número de solicitud
        $numeroSolicitud = $this->generateNumeroSolicitud();

        // Añadir campos del sistema
        $solicitudData = array_merge($solicitudData, [
            'numero_solicitud' => $numeroSolicitud,
            'owner_username' => $ownerUsername,
            'estado_codigo' => $estadoPostulado->codigo,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $solicitud = $this->create($solicitudData);

        // Crear entrada en timeline
        $this->addTimelineEntry($solicitud->id, $estadoPostulado->codigo, 'Solicitud creada');

        return $solicitud;
    }

    /**
     * Find solicitudes by owner username.
     */
    public function findByOwner(string $ownerUsername, array $orderBy = []): Collection
    {
        $query = $this->model->where('owner_username', $ownerUsername);

        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        return $query->get();
    }

    /**
     * Find solicitudes by estado.
     */
    public function findByEstado(string $estadoCodigo, array $orderBy = []): Collection
    {
        $query = $this->model->where('estado_codigo', $estadoCodigo);

        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        return $query->get();
    }

    /**
     * Find solicitudes by owner and estado.
     */
    public function findByOwnerAndEstado(string $ownerUsername, string $estadoCodigo): Collection
    {
        return $this->model->where('owner_username', $ownerUsername)
            ->where('estado_codigo', $estadoCodigo)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Find solicitudes by monto range.
     */
    public function findByMontoRange(float $min, float $max): Collection
    {
        return $this->model->whereBetween('monto_solicitado', [$min, $max])
            ->orderBy('monto_solicitado', 'desc')
            ->get();
    }

    /**
     * Find solicitudes by date range.
     */
    public function findByDateRange(string $startDate, string $endDate): Collection
    {
        return $this->model->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get solicitudes with full relationships.
     */
    public function getWithRelations(string $solicitudId): ?SolicitudCredito
    {
        return $this->model->with([
            'owner',
            'estado',
            'documentos',
            'timeline',
            'solicitante',
            'payload'
        ])->find($solicitudId);
    }

    /**
     * Update solicitud estado.
     */
    public function updateEstado(string $solicitudId, string $nuevoEstado, string $descripcion = '', ?string $usuario = null): bool
    {
        $solicitud = $this->findById($solicitudId);
        if (!$solicitud) {
            return false;
        }

        // Validar transición de estado
        if (!$this->isValidTransition($solicitud->estado_codigo, $nuevoEstado)) {
            throw new InvalidSolicitudStateError(
                "Transición inválida de {$solicitud->estado_codigo} a {$nuevoEstado}"
            );
        }

        DB::beginTransaction();
        try {
            // Actualizar estado
            $solicitud->update([
                'estado_codigo' => $nuevoEstado,
                'updated_at' => now()
            ]);

            // Agregar entrada al timeline
            $this->addTimelineEntry($solicitudId, $nuevoEstado, $descripcion, $usuario);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Add timeline entry.
     */
    public function addTimelineEntry(string $solicitudId, string $estado, string $descripcion, ?string $usuario = null): SolicitudTimeline
    {
        return SolicitudTimeline::create([
            'solicitud_id' => $solicitudId,
            'estado_codigo' => $estado,
            'descripcion' => $descripcion,
            'usuario' => $usuario,
            'tipo' => 'manual',
            'created_at' => now()
        ]);
    }

    /**
     * Get solicitud timeline.
     */
    public function getTimeline(string $solicitudId): Collection
    {
        return SolicitudTimeline::where('solicitud_id', $solicitudId)
            ->with('estado')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get solicitud documents.
     */
    public function getDocumentos(string $solicitudId): Collection
    {
        return SolicitudDocumento::where('solicitud_id', $solicitudId)
            ->where('activo', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Add document to solicitud.
     */
    public function addDocumento(string $solicitudId, array $documentoData): SolicitudDocumento
    {
        $documentoData['solicitud_id'] = $solicitudId;
        $documentoData['activo'] = true;
        $documentoData['created_at'] = now();

        return SolicitudDocumento::create($documentoData);
    }

    /**
     * Update solicitud payload.
     */
    public function updatePayload(string $solicitudId, array $payloadData): SolicitudPayload
    {
        $payload = SolicitudPayload::where('solicitud_id', $solicitudId)->first();

        if (!$payload) {
            $payload = SolicitudPayload::create([
                'solicitud_id' => $solicitudId,
                'datos_json' => $payloadData,
                'created_at' => now()
            ]);
        } else {
            $payload->update([
                'datos_json' => array_merge($payload->datos_json ?? [], $payloadData),
                'updated_at' => now()
            ]);
        }

        return $payload;
    }

    /**
     * Get solicitud payload.
     */
    public function getPayload(string $solicitudId): ?SolicitudPayload
    {
        return SolicitudPayload::where('solicitud_id', $solicitudId)->first();
    }

    /**
     * Update or create solicitante.
     */
    public function updateOrCreateSolicitante(string $solicitudId, array $solicitanteData): SolicitudSolicitante
    {
        return SolicitudSolicitante::updateOrCreate(
            ['solicitud_id' => $solicitudId],
            array_merge($solicitanteData, [
                'updated_at' => now()
            ])
        );
    }

    /**
     * Get solicitud solicitante.
     */
    public function getSolicitante(string $solicitudId): ?SolicitudSolicitante
    {
        return SolicitudSolicitante::where('solicitud_id', $solicitudId)->first();
    }

    /**
     * Get solicitudes by user with pagination.
     */
    public function getByUserPaginated(string $username, int $perPage = 15, array $filters = [])
    {
        $query = $this->model->where('owner_username', $username);

        // Apply filters
        if (isset($filters['estado'])) {
            $query->where('estado_codigo', $filters['estado']);
        }

        if (isset($filters['monto_min'])) {
            $query->where('monto_solicitado', '>=', $filters['monto_min']);
        }

        if (isset($filters['monto_max'])) {
            $query->where('monto_solicitado', '<=', $filters['monto_max']);
        }

        if (isset($filters['fecha_inicio'])) {
            $query->where('created_at', '>=', $filters['fecha_inicio']);
        }

        if (isset($filters['fecha_fin'])) {
            $query->where('created_at', '<=', $filters['fecha_fin']);
        }

        if (isset($filters['skip'])) {
            $query->offset($filters['skip']);
        }

        return $query->with(['estado', 'documentos'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get solicitudes statistics.
     */
    public function getStatistics(): array
    {
        $total = $this->model->count();

        $byStatus = EstadoSolicitud::leftJoin('solicitudes_credito', 'estados_solicitud.codigo', '=', 'solicitudes_credito.estado_codigo')
            ->groupBy('estados_solicitud.codigo', 'estados_solicitud.nombre', 'estados_solicitud.color')
            ->selectRaw('
                estados_solicitud.codigo,
                estados_solicitud.nombre,
                estados_solicitud.color,
                COUNT(solicitudes_credito.id) as cantidad,
                COALESCE(SUM(solicitudes_credito.monto_solicitado), 0) as total_monto
            ')
            ->get();

        return [
            'total' => $total,
            'by_status' => $byStatus->map(function ($status) use ($total) {
                return [
                    'codigo' => $status->codigo,
                    'nombre' => $status->nombre,
                    'color' => $status->color,
                    'cantidad' => $status->cantidad,
                    'total_monto' => $status->total_monto,
                    'porcentaje' => $total > 0 ? round(($status->cantidad / $total) * 100, 2) : 0
                ];
            })
        ];
    }

    /**
     * Get solicitudes for dashboard.
     */
    public function getDashboardData(): array
    {
        $hoy = now()->startOfDay();
        $ayer = now()->subDay()->startOfDay();

        return [
            'total' => $this->model->count(),
            'hoy' => $this->model->where('created_at', '>=', $hoy)->count(),
            'ayer' => $this->model->whereBetween('created_at', [$ayer, $hoy])->count(),
            'esta_semana' => $this->model->where('created_at', '>=', now()->startOfWeek())->count(),
            'este_mes' => $this->model->where('created_at', '>=', now()->startOfMonth())->count(),
            'pendientes' => $this->model->where('estado_codigo', 'POSTULADO')->count(),
            'en_revision' => $this->model->where('estado_codigo', 'EN_REVISION')->count(),
            'aprobadas' => $this->model->where('estado_codigo', 'APROBADO')->count(),
            'rechazadas' => $this->model->where('estado_codigo', 'RECHAZADO')->count(),
            'total_monto_solicitado' => $this->model->sum('monto_solicitado'),
            'monto_promedio' => $this->model->avg('monto_solicitado'),
            'monto_maximo' => $this->model->max('monto_solicitado'),
            'monto_minimo' => $this->model->min('monto_solicitado')
        ];
    }

    /**
     * Check if state transition is valid.
     */
    private function isValidTransition(string $fromEstado, string $toEstado): bool
    {
        $validTransitions = [
            'POSTULADO' => ['EN_REVISION', 'RECHAZADO'],
            'EN_REVISION' => ['EN_ESTUDIO', 'PRE_APROBADO', 'RECHAZADO'],
            'EN_ESTUDIO' => ['PRE_APROBADO', 'APROBADO', 'RECHAZADO'],
            'PRE_APROBADO' => ['APROBADO', 'RECHAZADO'],
            'APROBADO' => [], // Terminal state
            'RECHAZADO' => [], // Terminal state
        ];

        return in_array($toEstado, $validTransitions[$fromEstado] ?? []);
    }

    /**
     * Generate unique solicitud number.
     */
    private function generateNumeroSolicitud(): string
    {
        do {
            $numero = 'SOL-' . date('Y') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while ($this->model->where('numero_solicitud', $numero)->exists());

        return $numero;
    }

    /**
     * Get solicitudes by multiple criteria.
     */
    public function findByCriteria(array $criteria): Collection
    {
        $query = $this->model->query();

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get solicitudes count by status.
     */
    public function getCountByStatus(): array
    {
        return $this->model->join('estados_solicitud', 'solicitudes_credito.estado_codigo', '=', 'estados_solicitud.codigo')
            ->groupBy('estados_solicitud.codigo', 'estados_solicitud.nombre')
            ->selectRaw('
                estados_solicitud.codigo,
                estados_solicitud.nombre,
                COUNT(solicitudes_credito.id) as count
            ')
            ->pluck('count', 'codigo')
            ->toArray();
    }

    /**
     * Soft delete solicitud.
     */
    public function softDeleteSolicitud(string $solicitudId): bool
    {
        $solicitud = $this->findById($solicitudId);
        if (!$solicitud) {
            return false;
        }

        return $solicitud->delete();
    }

    /**
     * Restore soft deleted solicitud.
     */
    public function restoreSolicitud(string $solicitudId): bool
    {
        $solicitud = $this->model->withTrashed()->find($solicitudId);
        if (!$solicitud) {
            return false;
        }

        return $solicitud->restore();
    }
}
