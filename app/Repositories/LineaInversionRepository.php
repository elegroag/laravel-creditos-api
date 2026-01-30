<?php

namespace App\Repositories;

use App\Models\LineaInversion;
use Illuminate\Support\Collection;

class LineaInversionRepository extends BaseRepository
{
    /**
     * Get active investment lines.
     */
    public function getActive(): Collection
    {
        return $this->model->active()
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Get investment lines by category.
     */
    public function getByCategoria(string $categoria): Collection
    {
        return $this->model->where('categoria', $categoria)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Get investment lines by estado.
     */
    public function getByEstado(string $estado): Collection
    {
        return $this->model->where('estado', $estado)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Search investment lines.
     */
    public function search(string $term, array $filters = []): Collection
    {
        $query = $this->model->where(function ($q) use ($term) {
            $q->where('nombre', 'like', "%{$term}%")
                ->orWhere('descripcion', 'like', "%{$term}%")
                ->orWhere('categoria', 'like', "%{$term}%");
        });

        // Apply filters
        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['categoria'])) {
            $query->where('categoria', $filters['categoria']);
        }

        if (isset($filters['monto_min'])) {
            $query->where('monto_maximo_pesos', '>=', $filters['monto_min']);
        }

        if (isset($filters['monto_max'])) {
            $query->where('monto_maximo_pesos', '<=', $filters['monto_max']);
        }

        if (isset($filters['tasa_min'])) {
            $query->where('tasas_interes_anual.0', '>=', $filters['tasa_min']);
        }

        if (isset($filters['tasa_max'])) {
            $query->where('tasas_interes_anual.0', '<=', $filters['tasa_max']);
        }

        return $query->orderBy('id', 'asc')->get();
    }

    /**
     * Get investment lines with pagination.
     */
    public function getPaginated(int $perPage = 15, array $filters = [])
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['categoria'])) {
            $query->where('categoria', $filters['categoria']);
        }

        if (isset($filters['nombre'])) {
            $query->where('nombre', 'like', "%{$filters['nombre']}%");
        }

        if (isset($filters['monto_min'])) {
            $query->where('monto_maximo_pesos', '>=', $filters['monto_min']);
        }

        if (isset($filters['monto_max'])) {
            $query->where('monto_maximo_pesos', '<=', $filters['monto_max']);
        }

        return $query->orderBy('id', 'asc')->paginate($perPage);
    }

    /**
     * Get statistics.
     */
    public function getStatistics(): array
    {
        $total = $this->model->count();

        $byCategoria = $this->model->selectRaw('
                categoria, 
                COUNT(*) as count,
                COALESCE(SUM(monto_maximo_pesos), 0) as total_monto
            ')
            ->groupBy('categoria')
            ->orderBy('count', 'desc')
            ->get();

        $byEstado = $this->model->selectRaw('estado, COUNT(*) as count')
            ->groupBy('estado')
            ->orderBy('count', 'desc')
            ->get();

        $activeCount = $this->model->active()->count();
        $inactiveCount = $total - $activeCount;

        return [
            'total' => $total,
            'by_categoria' => $byCategoria->toArray(),
            'by_estado' => $byEstado->toArray(),
            'active_count' => $activeCount,
            'inactive_count' => $inactiveCount
        ];
    }

    /**
     * Get dashboard data.
     */
    public function getDashboardData(): array
    {
        $total = $this->model->count();
        $active = $this->model->active()->count();

        $byCategoria = $this->model->selectRaw('categoria, COUNT(*) as count')
            ->groupBy('categoria')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->pluck('count', 'categoria')
            ->toArray();

        $totalMontoMaximo = $this->model->sum('monto_maximo_pesos');
        $avgMontoMaximo = $this->model->avg('monto_maximo_pesos');

        $tasas = $this->model->get()
            ->flatMap(function ($linea) {
                return $linea->tasas_interes_anual ?? [];
            })
            ->filter();

        $avgTasa = $tasas->count() > 0 ? $tasas->avg() : 0;

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'by_categoria' => $byCategoria,
            'total_monto_maximo' => $totalMontoMaximo,
            'avg_monto_maximo' => $avgMontoMaximo,
            'avg_tasa_interes' => $avgTasa,
            'active_percentage' => $total > 0 ? round(($active / $total) * 100, 2) : 0
        ];
    }

    /**
     * Check if amount is within line limits.
     */
    public function isMontoWithinLimit(int $lineaId, float $monto): bool
    {
        $linea = $this->findById($lineaId);
        
        if (!$linea) {
            return false;
        }

        return $monto >= $linea->monto_minimo_pesos && 
               $monto <= $linea->monto_maximo_pesos;
    }

    /**
     * Get applicable interest rate for amount.
     */
    public function getTasaForAmount(int $lineaId, float $monto): ?float
    {
        $linea = $this->findById($lineaId);
        
        if (!$linea || !$linea->tasas_interes_anual) {
            return null;
        }

        $tasas = $linea->tasas_interes_anual;
        
        // Find the highest rate that applies to this amount
        $applicableRate = null;
        foreach ($tasas as $tasa) {
            if ($monto >= ($tasa['monto_min'] ?? 0) && 
                $monto <= ($tasa['monto_max'] ?? PHP_FLOAT_MAX)) {
                $applicableRate = $tasa['tasa'] ?? $applicableRate;
            }
        }

        return $applicableRate;
    }

    /**
     * Get unique categories.
     */
    public function getUniqueCategories(): Collection
    {
        return $this->model->distinct('categoria')
            ->orderBy('categoria')
            ->pluck('categoria');
    }

    /**
     * Get unique estados.
     */
    public function getUniqueEstados(): Collection
    {
        return $this->model->distinct('estado')
            ->orderBy('estado')
            ->pluck('estado');
    }

    /**
     * Get lines by monto range.
     */
    public function getByMontoRange(float $min, float $max): Collection
    {
        return $this->model->where('monto_minimo_pesos', '<=', $max)
            ->where('monto_maximo_pesos', '>=', $min)
            ->orderBy('monto_maximo_pesos', 'desc')
            ->get();
    }

    /**
     * Get lines by tasa range.
     */
    public function getByTasaRange(float $min, float $max): Collection
    {
        $lineas = $this->model->get();
        $filtered = collect();

        foreach ($lineas as $linea) {
            if (!$linea->tasas_interes_anual) continue;
            
            foreach ($linea->tasas_interes_anual as $tasa) {
                $tasaValue = $tasa['tasa'] ?? 0;
                if ($tasaValue >= $min && $tasaValue <= $max) {
                    $filtered->push($linea);
                    break;
                }
            }
        }

        return $filtered->unique('id');
    }

    /**
     * Update line estado.
     */
    public function updateEstado(int $id, string $estado): bool
    {
        return $this->update($id, ['estado' => $estado]);
    }

    /**
     * Activate line.
     */
    public function activate(int $id): bool
    {
        return $this->update($id, ['estado' => 'activa']);
    }

    /**
     * Deactivate line.
     */
    public function deactivate(int $id): bool
    {
        return $this->update($id, ['estado' => 'inactiva']);
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
     * Get lines with highest monto.
     */
    public function getHighestMonto(int $limit = 5): Collection
    {
        return $this->model->orderBy('monto_maximo_pesos', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get lines with lowest monto.
     */
    public function getLowestMonto(int $limit = 5): Collection
    {
        return $this->model->orderBy('monto_maximo_pesos', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get lines by plazo.
     */
    public function getByPlazo(int $plazoMeses): Collection
    {
        return $this->model->where('plazo_maximo_meses', '>=', $plazoMeses)
            ->orderBy('plazo_maximo_meses', 'asc')
            ->get();
    }

    /**
     * Export lines to array format.
     */
    public function exportToArray(array $filters = []): array
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['categoria'])) {
            $query->where('categoria', $filters['categoria']);
        }

        return $query->orderBy('id', 'asc')
            ->get()
            ->map(function ($linea) {
                return [
                    'id' => $linea->id,
                    'nombre' => $linea->nombre,
                    'descripcion' => $linea->descripcion,
                    'categoria' => $linea->categoria,
                    'monto_minimo_pesos' => $linea->monto_minimo_pesos,
                    'monto_maximo_pesos' => $linea->monto_maximo_pesos,
                    'plazo_maximo_meses' => $linea->plazo_maximo_meses,
                    'tasas_interes_anual' => $linea->tasas_interes_anual,
                    'requisitos' => $linea->requisitos,
                    'estado' => $linea->estado,
                    'is_active' => $linea->isActive(),
                    'created_at' => $linea->created_at->toISOString(),
                    'updated_at' => $linea->updated_at->toISOString()
                ];
            })
            ->toArray();
    }

    /**
     * Calculate monthly payment for line.
     */
    public function calculateMonthlyPayment(int $lineaId, float $monto, int $plazoMeses): array
    {
        $linea = $this->findById($lineaId);
        
        if (!$linea) {
            return [
                'error' => 'Línea no encontrada',
                'monthly_payment' => 0,
                'total_interest' => 0,
                'total_payment' => 0
            ];
        }

        $tasa = $this->getTasaForAmount($lineaId, $monto);
        
        if (!$tasa) {
            return [
                'error' => 'No hay tasa aplicable para este monto',
                'monthly_payment' => 0,
                'total_interest' => 0,
                'total_payment' => 0
            ];
        }

        $tasaMensual = $tasa / 100 / 12;
        
        if ($tasaMensual == 0) {
            $monthlyPayment = $monto / $plazoMeses;
            $totalInterest = 0;
        } else {
            $monthlyPayment = $monto * ($tasaMensual * pow(1 + $tasaMensual, $plazoMeses)) / (pow(1 + $tasaMensual, $plazoMeses) - 1);
            $totalInterest = ($monthlyPayment * $plazoMeses) - $monto;
        }

        return [
            'monthly_payment' => round($monthlyPayment, 2),
            'total_interest' => round($totalInterest, 2),
            'total_payment' => round($monthlyPayment * $plazoMeses, 2),
            'tasa_anual' => $tasa,
            'tasa_mensual' => $tasaMensual * 100
        ];
    }
}
