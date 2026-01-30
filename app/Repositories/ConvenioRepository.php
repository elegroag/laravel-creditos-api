<?php

namespace App\Repositories;

use App\Models\EmpresaConvenio;
use App\Models\SolicitudSolicitante;
use App\Models\Postulacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConvenioRepository extends BaseRepository
{
    /**
     * Find convenio by NIT.
     */
    public function findByNit(int $nit): ?EmpresaConvenio
    {
        return $this->model->where('nit', $nit)->first();
    }

    /**
     * Find convenio by NIT or razón social.
     */
    public function findByNitOrRazonSocial(string $search): Collection
    {
        return $this->model->where('nit', 'like', "%{$search}%")
            ->orWhere('razon_social', 'like', "%{$search}%")
            ->get();
    }

    /**
     * Get active convenios.
     */
    public function getActive(): Collection
    {
        return $this->model->active()
            ->orderBy('razon_social')
            ->get();
    }

    /**
     * Get convenios by status.
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->byStatus($status)
            ->orderBy('razon_social')
            ->get();
    }

    /**
     * Get convenios expiring soon.
     */
    public function getExpiringSoon(int $days = 30): Collection
    {
        return $this->model->expiringSoon($days)
            ->orderBy('fecha_vencimiento')
            ->get();
    }

    /**
     * Get expired convenios.
     */
    public function getExpired(): Collection
    {
        return $this->model->expired()
            ->orderBy('fecha_vencimiento', 'desc')
            ->get();
    }

    /**
     * Get convenios by city.
     */
    public function getByCity(string $city): Collection
    {
        return $this->model->where('ciudad', $city)
            ->orderBy('razon_social')
            ->get();
    }

    /**
     * Get convenios by department.
     */
    public function getByDepartment(string $department): Collection
    {
        return $this->model->where('departamento', $department)
            ->orderBy('razon_social')
            ->get();
    }

    /**
     * Get convenios by sector económico.
     */
    public function getBySectorEconomico(string $sector): Collection
    {
        return $this->model->where('sector_economico', $sector)
            ->orderBy('razon_social')
            ->get();
    }

    /**
     * Get convenios by tipo de empresa.
     */
    public function getByTipoEmpresa(string $tipo): Collection
    {
        return $this->model->where('tipo_empresa', $tipo)
            ->orderBy('razon_social')
            ->get();
    }

    /**
     * Get convenios by number of employees range.
     */
    public function getByEmployeeRange(int $min, int $max): Collection
    {
        return $this->model->whereBetween('numero_empleados', [$min, $max])
            ->orderBy('numero_empleados')
            ->get();
    }

    /**
     * Search convenios by multiple criteria.
     */
    public function searchConvenios(string $term, array $filters = []): Collection
    {
        $query = $this->model->where(function ($q) use ($term) {
            $q->where('nit', 'like', "%{$term}%")
                ->orWhere('razon_social', 'like', "%{$term}%")
                ->orWhere('representante_nombre', 'like', "%{$term}%")
                ->orWhere('correo', 'like', "%{$term}%");
        });

        // Apply filters
        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['ciudad'])) {
            $query->where('ciudad', $filters['ciudad']);
        }

        if (isset($filters['departamento'])) {
            $query->where('departamento', $filters['departamento']);
        }

        if (isset($filters['sector_economico'])) {
            $query->where('sector_economico', $filters['sector_economico']);
        }

        if (isset($filters['tipo_empresa'])) {
            $query->where('tipo_empresa', $filters['tipo_empresa']);
        }

        if (isset($filters['empleados_min'])) {
            $query->where('numero_empleados', '>=', $filters['empleados_min']);
        }

        if (isset($filters['empleados_max'])) {
            $query->where('numero_empleados', '<=', $filters['empleados_max']);
        }

        return $query->orderBy('razon_social')->get();
    }

    /**
     * Create new convenio.
     */
    public function createConvenio(array $convenioData): EmpresaConvenio
    {
        // Set default values
        $convenioData = array_merge([
            'estado' => 'Activo',
            'created_at' => now(),
            'updated_at' => now()
        ], $convenioData);

        return $this->create($convenioData);
    }

    /**
     * Update convenio and get updated record.
     */
    public function updateConvenio(int $nit, array $data): ?EmpresaConvenio
    {
        $convenio = $this->findByNit($nit);
        if (!$convenio) {
            return null;
        }

        $data['updated_at'] = now();
        $convenio->update($data);

        return $convenio->fresh();
    }

    /**
     * Activate convenio.
     */
    public function activate(int $nit): bool
    {
        $convenio = $this->findByNit($nit);
        if (!$convenio) {
            return false;
        }

        return $this->update($convenio->id, ['estado' => 'Activo']);
    }

    /**
     * Deactivate convenio.
     */
    public function deactivate(int $nit): bool
    {
        $convenio = $this->findByNit($nit);
        if (!$convenio) {
            return false;
        }

        return $this->update($convenio->id, ['estado' => 'Inactivo']);
    }

    /**
     * Suspend convenio.
     */
    public function suspend(int $nit): bool
    {
        $convenio = $this->findByNit($nit);
        if (!$convenio) {
            return false;
        }

        return $this->update($convenio->id, ['estado' => 'Suspendido']);
    }

    /**
     * Check if NIT exists.
     */
    public function nitExists(int $nit, ?int $excludeId = null): bool
    {
        $query = $this->model->where('nit', $nit);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Check if email exists.
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->model->where('correo', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get convenios with pagination and filters.
     */
    public function getPaginated(int $perPage = 15, array $filters = [])
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['ciudad'])) {
            $query->where('ciudad', $filters['ciudad']);
        }

        if (isset($filters['departamento'])) {
            $query->where('departamento', $filters['departamento']);
        }

        if (isset($filters['sector_economico'])) {
            $query->where('sector_economico', $filters['sector_economico']);
        }

        if (isset($filters['tipo_empresa'])) {
            $query->where('tipo_empresa', $filters['tipo_empresa']);
        }

        if (isset($filters['empleados_min'])) {
            $query->where('numero_empleados', '>=', $filters['empleados_min']);
        }

        if (isset($filters['empleados_max'])) {
            $query->where('numero_empleados', '<=', $filters['empleados_max']);
        }

        if (isset($filters['vencimiento_desde'])) {
            $query->where('fecha_vencimiento', '>=', $filters['vencimiento_desde']);
        }

        if (isset($filters['vencimiento_hasta'])) {
            $query->where('fecha_vencimiento', '<=', $filters['vencimiento_hasta']);
        }

        return $query->orderBy('razon_social')
            ->paginate($perPage);
    }

    /**
     * Get convenios statistics.
     */
    public function getStatistics(): array
    {
        $total = $this->model->count();
        $active = $this->model->active()->count();
        $expired = $this->model->expired()->count();
        $expiringSoon = $this->model->expiringSoon(30)->count();

        $byStatus = $this->model->groupBy('estado')
            ->selectRaw('estado, COUNT(*) as count')
            ->pluck('count', 'estado')
            ->toArray();

        $byCity = $this->model->groupBy('ciudad')
            ->selectRaw('ciudad, COUNT(*) as count')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('count', 'ciudad')
            ->toArray();

        $byDepartment = $this->model->groupBy('departamento')
            ->selectRaw('departamento, COUNT(*) as count')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('count', 'departamento')
            ->toArray();

        $bySector = $this->model->groupBy('sector_economico')
            ->selectRaw('sector_economico, COUNT(*) as count')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('count', 'sector_economico')
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
            'by_status' => $byStatus,
            'by_city' => $byCity,
            'by_department' => $byDepartment,
            'by_sector' => $bySector,
            'active_percentage' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
            'expired_percentage' => $total > 0 ? round(($expired / $total) * 100, 2) : 0
        ];
    }

    /**
     * Get convenios with related solicitudes.
     */
    public function getWithSolicitudes(int $nit): ?EmpresaConvenio
    {
        return $this->model->with(['solicitudes', 'postulaciones'])
            ->where('nit', $nit)
            ->first();
    }

    /**
     * Get solicitudes count for convenio.
     */
    public function getSolicitudesCount(int $nit): int
    {
        return SolicitudSolicitante::where('empresa_nit', $nit)->count();
    }

    /**
     * Get postulaciones count for convenio.
     */
    public function getPostulacionesCount(int $nit): int
    {
        return Postulacion::where('empresa_nit', $nit)->count();
    }

    /**
     * Get dashboard data for convenios.
     */
    public function getDashboardData(): array
    {
        $hoy = now()->startOfDay();
        $ayer = now()->subDay()->startOfDay();
        $proximos30Dias = now()->addDays(30);

        return [
            'total' => $this->model->count(),
            'activos' => $this->model->active()->count(),
            'vencidos' => $this->model->expired()->count(),
            'por_vencer' => $this->model->expiringSoon()->count(),
            'creados_hoy' => $this->model->where('created_at', '>=', $hoy)->count(),
            'creados_ayer' => $this->model->whereBetween('created_at', [$ayer, $hoy])->count(),
            'vencen_proximos_30_dias' => $this->model->whereBetween('fecha_vencimiento', [now(), $proximos30Dias])->count(),
            'porcentaje_activos' => $this->model->count() > 0 ? round(($this->model->active()->count() / $this->model->count()) * 100, 2) : 0,
            'ciudades_unicas' => $this->model->distinct('ciudad')->count('ciudad'),
            'departamentos_unicos' => $this->model->distinct('departamento')->count('departamento'),
            'sectores_economicos_unicos' => $this->model->distinct('sector_economico')->count('sector_economico')
        ];
    }

    /**
     * Bulk update convenios status.
     */
    public function bulkUpdateStatus(array $nits, string $status): int
    {
        return $this->model->whereIn('nit', $nits)
            ->update([
                'estado' => $status,
                'updated_at' => now()
            ]);
    }

    /**
     * Get convenios that need renewal notification.
     */
    public function getForRenewalNotification(int $daysBefore = 30): Collection
    {
        return $this->model->where('estado', 'Activo')
            ->where('fecha_vencimiento', '<=', now()->addDays($daysBefore))
            ->where('fecha_vencimiento', '>', now())
            ->orderBy('fecha_vencimiento')
            ->get();
    }

    /**
     * Soft delete convenio.
     */
    public function softDeleteConvenio(int $nit): bool
    {
        $convenio = $this->findByNit($nit);
        if (!$convenio) {
            return false;
        }

        return $convenio->delete();
    }

    /**
     * Restore soft deleted convenio.
     */
    public function restoreConvenio(int $nit): bool
    {
        $convenio = $this->model->withTrashed()->where('nit', $nit)->first();
        if (!$convenio) {
            return false;
        }

        return $convenio->restore();
    }

    /**
     * Get unique cities.
     */
    public function getUniqueCities(): Collection
    {
        return $this->model->whereNotNull('ciudad')
            ->distinct()
            ->orderBy('ciudad')
            ->pluck('ciudad');
    }

    /**
     * Get unique departments.
     */
    public function getUniqueDepartments(): Collection
    {
        return $this->model->whereNotNull('departamento')
            ->distinct()
            ->orderBy('departamento')
            ->pluck('departamento');
    }

    /**
     * Get unique economic sectors.
     */
    public function getUniqueSectoresEconomicos(): Collection
    {
        return $this->model->whereNotNull('sector_economico')
            ->distinct()
            ->orderBy('sector_economico')
            ->pluck('sector_economico');
    }

    /**
     * Get unique company types.
     */
    public function getUniqueTiposEmpresa(): Collection
    {
        return $this->model->whereNotNull('tipo_empresa')
            ->distinct()
            ->orderBy('tipo_empresa')
            ->pluck('tipo_empresa');
    }

    /**
     * Export convenios to array format.
     */
    public function exportToArray(array $filters = []): array
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['ciudad'])) {
            $query->where('ciudad', $filters['ciudad']);
        }

        if (isset($filters['departamento'])) {
            $query->where('departamento', $filters['departamento']);
        }

        return $query->orderBy('razon_social')
            ->get()
            ->map(function ($convenio) {
                return [
                    'nit' => $convenio->nit,
                    'razon_social' => $convenio->razon_social,
                    'representante_nombre' => $convenio->representante_nombre,
                    'representante_documento' => $convenio->representante_documento,
                    'telefono' => $convenio->telefono,
                    'correo' => $convenio->correo,
                    'direccion' => $convenio->direccion,
                    'ciudad' => $convenio->ciudad,
                    'departamento' => $convenio->departamento,
                    'sector_economico' => $convenio->sector_economico,
                    'numero_empleados' => $convenio->numero_empleados,
                    'tipo_empresa' => $convenio->tipo_empresa,
                    'fecha_convenio' => $convenio->fecha_convenio->format('Y-m-d'),
                    'fecha_vencimiento' => $convenio->fecha_vencimiento->format('Y-m-d'),
                    'estado' => $convenio->estado,
                    'full_address' => $convenio->full_address,
                    'is_active' => $convenio->isActive(),
                    'is_expired' => $convenio->isExpired(),
                    'days_until_expiration' => $convenio->getDaysUntilExpiration(),
                    'created_at' => $convenio->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $convenio->updated_at->format('Y-m-d H:i:s')
                ];
            })
            ->toArray();
    }
}
