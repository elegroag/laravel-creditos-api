<?php

namespace App\Repositories;

use App\Models\EntidadDigital;
use Illuminate\Support\Collection;

class EntidadDigitalRepository extends BaseRepository
{
    /**
     * Find entidad digital by username.
     */
    public function findByUsername(string $username): ?EntidadDigital
    {
        return $this->model->where('username', $username)->first();
    }

    /**
     * Find entidad digital by document.
     */
    public function findByDocument(string $tipoIdentificacion, string $numeroIdentificacion): ?EntidadDigital
    {
        return $this->model->where('tipo_identificacion', $tipoIdentificacion)
            ->where('numero_identificacion', $numeroIdentificacion)
            ->first();
    }

    /**
     * Check if entidad exists by document.
     */
    public function existsByDocument(string $tipoIdentificacion, string $numeroIdentificacion): bool
    {
        return $this->model->where('tipo_identificacion', $tipoIdentificacion)
            ->where('numero_identificacion', $numeroIdentificacion)
            ->exists();
    }

    /**
     * Get entidades by estado.
     */
    public function getByEstado(string $estado): Collection
    {
        return $this->model->where('estado', $estado)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get active entidades.
     */
    public function getActive(): Collection
    {
        return $this->model->where('estado', 'activa')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get complete entidades.
     */
    public function getComplete(): Collection
    {
        return $this->model->where(function ($query) {
            $query->whereNotNull('documentos')
                ->where('documentos', '!=', '[]')
                ->whereNotNull('selfie');
        })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get incomplete entidades.
     */
    public function getIncomplete(): Collection
    {
        return $this->model->where(function ($query) {
            $query->whereNull('documentos')
                ->orWhere('documentos', '[]')
                ->orWhereNull('selfie');
        })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Search entidades by criteria.
     */
    public function searchEntidades(string $term, array $filters = []): Collection
    {
        $query = $this->model->where(function ($q) use ($term) {
            $q->where('username', 'like', "%{$term}%")
                ->orWhere('numero_identificacion', 'like', "%{$term}%");
        });

        // Apply filters
        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['tipo_identificacion'])) {
            $query->where('tipo_identificacion', $filters['tipo_identificacion']);
        }

        if (isset($filters['has_documents'])) {
            if ($filters['has_documents']) {
                $query->whereNotNull('documentos')->where('documentos', '!=', '[]');
            } else {
                $query->whereNull('documentos')->orWhere('documentos', '[]');
            }
        }

        if (isset($filters['has_selfie'])) {
            if ($filters['has_selfie']) {
                $query->whereNotNull('selfie');
            } else {
                $query->whereNull('selfie');
            }
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get entidades with pagination.
     */
    public function getPaginated(int $perPage = 15, array $filters = [])
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['tipo_identificacion'])) {
            $query->where('tipo_identificacion', $filters['tipo_identificacion']);
        }

        if (isset($filters['username'])) {
            $query->where('username', 'like', "%{$filters['username']}%");
        }

        if (isset($filters['numero_identificacion'])) {
            $query->where('numero_identificacion', 'like', "%{$filters['numero_identificacion']}%");
        }

        if (isset($filters['has_documents'])) {
            if ($filters['has_documents']) {
                $query->whereNotNull('documentos')->where('documentos', '!=', '[]');
            } else {
                $query->whereNull('documentos')->orWhere('documentos', '[]');
            }
        }

        if (isset($filters['has_selfie'])) {
            if ($filters['has_selfie']) {
                $query->whereNotNull('selfie');
            } else {
                $query->whereNull('selfie');
            }
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get statistics.
     */
    public function getStatistics(): array
    {
        $total = $this->model->count();

        $byEstado = $this->model->selectRaw('estado, COUNT(*) as count')
            ->groupBy('estado')
            ->pluck('count', 'estado')
            ->toArray();

        $complete = $this->model->where(function ($query) {
            $query->whereNotNull('documentos')
                ->where('documentos', '!=', '[]')
                ->whereNotNull('selfie');
        })->count();

        $withDocuments = $this->model->whereNotNull('documentos')
            ->where('documentos', '!=', '[]')
            ->count();

        $withSelfie = $this->model->whereNotNull('selfie')->count();

        $byTipoIdentificacion = $this->model->selectRaw('tipo_identificacion, COUNT(*) as count')
            ->groupBy('tipo_identificacion')
            ->orderBy('count', 'desc')
            ->pluck('count', 'tipo_identificacion')
            ->toArray();

        return [
            'total' => $total,
            'complete' => $complete,
            'incomplete' => $total - $complete,
            'with_documents' => $withDocuments,
            'with_selfie' => $withSelfie,
            'without_documents' => $total - $withDocuments,
            'without_selfie' => $total - $withSelfie,
            'by_estado' => $byEstado,
            'by_tipo_identificacion' => $byTipoIdentificacion,
            'completion_rate' => $total > 0 ? round(($complete / $total) * 100, 2) : 0,
            'documents_rate' => $total > 0 ? round(($withDocuments / $total) * 100, 2) : 0,
            'selfie_rate' => $total > 0 ? round(($withSelfie / $total) * 100, 2) : 0
        ];
    }

    /**
     * Get dashboard data.
     */
    public function getDashboardData(): array
    {
        $hoy = now()->startOfDay();
        $ayer = now()->subDay()->startOfDay();

        return [
            'total' => $this->model->count(),
            'activas' => $this->model->where('estado', 'activa')->count(),
            'inactivas' => $this->model->where('estado', 'inactiva')->count(),
            'completas' => $this->model->where(function ($query) {
                $query->whereNotNull('documentos')
                    ->where('documentos', '!=', '[]')
                    ->whereNotNull('selfie');
            })->count(),
            'incompletas' => $this->model->where(function ($query) {
                $query->whereNull('documentos')
                    ->orWhere('documentos', '[]')
                    ->orWhereNull('selfie');
            })->count(),
            'hoy' => $this->model->where('created_at', '>=', $hoy)->count(),
            'ayer' => $this->model->whereBetween('created_at', [$ayer, $hoy])->count(),
            'esta_semana' => $this->model->where('created_at', '>=', now()->startOfWeek())->count(),
            'este_mes' => $this->model->where('created_at', '>=', now()->startOfMonth())->count(),
            'con_documentos' => $this->model->whereNotNull('documentos')->where('documentos', '!=', '[]')->count(),
            'con_selfie' => $this->model->whereNotNull('selfie')->count(),
            'ultima_validacion_hoy' => $this->model->where('last_validation_at', '>=', $hoy)->count(),
        ];
    }

    /**
     * Update validation timestamp.
     */
    public function updateValidationTimestamp(int $id): bool
    {
        return $this->update($id, ['last_validation_at' => now()]);
    }

    /**
     * Update estado.
     */
    public function updateEstado(int $id, string $estado): bool
    {
        return $this->update($id, ['estado' => $estado]);
    }

    /**
     * Add validation record.
     */
    public function addValidation(int $id, array $validationData): bool
    {
        $entidad = $this->findById($id);
        if (!$entidad) {
            return false;
        }

        $validaciones = $entidad->validaciones ?? [];
        $validaciones[] = array_merge($validationData, [
            'timestamp' => now()->toISOString(),
            'id' => uniqid()
        ]);

        return $this->update($id, [
            'validaciones' => $validaciones,
            'last_validation_at' => now()
        ]);
    }

    /**
     * Get unique tipos de identificacion.
     */
    public function getUniqueTiposIdentificacion(): Collection
    {
        return $this->model->distinct('tipo_identificacion')
            ->orderBy('tipo_identificacion')
            ->pluck('tipo_identificacion');
    }

    /**
     * Get entidades by tipo identificacion.
     */
    public function getByTipoIdentificacion(string $tipo): Collection
    {
        return $this->model->where('tipo_identificacion', $tipo)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get entidades created in date range.
     */
    public function getByDateRange(string $startDate, string $endDate): Collection
    {
        return $this->model->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get entidades with last validation in date range.
     */
    public function getByValidationDateRange(string $startDate, string $endDate): Collection
    {
        return $this->model->whereBetween('last_validation_at', [$startDate, $endDate])
            ->orderBy('last_validation_at', 'desc')
            ->get();
    }

    /**
     * Bulk update estado.
     */
    public function bulkUpdateEstado(array $ids, string $estado): int
    {
        return $this->model->whereIn('id', $ids)
            ->update([
                'estado' => $estado,
                'updated_at' => now()
            ]);
    }

    /**
     * Export entidades to array format.
     */
    public function exportToArray(array $filters = []): array
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['tipo_identificacion'])) {
            $query->where('tipo_identificacion', $filters['tipo_identificacion']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entidad) {
                return [
                    'id' => $entidad->id,
                    'username' => $entidad->username,
                    'tipo_identificacion' => $entidad->tipo_identificacion,
                    'numero_identificacion' => $entidad->numero_identificacion,
                    'estado' => $entidad->estado,
                    'document_count' => $entidad->document_count,
                    'has_selfie' => $entidad->hasSelfie(),
                    'is_complete' => $entidad->isComplete(),
                    'validation_count' => $entidad->validation_count,
                    'last_validation_at' => $entidad->last_validation_at?->toISOString(),
                    'created_at' => $entidad->created_at->toISOString(),
                    'updated_at' => $entidad->updated_at->toISOString()
                ];
            })
            ->toArray();
    }
}
