<?php

namespace App\Repositories;

use App\Models\EstadoSolicitud;
use Illuminate\Support\Collection;

class EstadoSolicitudRepository extends BaseRepository
{
    public function __construct(EstadoSolicitud $estado)
    {
        parent::__construct($estado);
    }

    /**
     * Get estado POSTULADO.
     */
    public function getEstadoPostulado(): ?EstadoSolicitud
    {
        return $this->model->where('codigo', 'POSTULADO')->first();
    }

    /**
     * Get estado by codigo.
     */
    public function getByCodigo(string $codigo): ?EstadoSolicitud
    {
        return $this->model->where('codigo', $codigo)->first();
    }

    /**
     * Get all active estados.
     */
    public function getActive(): Collection
    {
        return $this->model->where('activo', true)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Get estados by category.
     */
    public function getByCategory(string $categoria): Collection
    {
        return $this->model->where('categoria', $categoria)
            ->where('activo', true)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Get initial states for new solicitudes.
     */
    public function getInitialStates(): Collection
    {
        return $this->model->where('inicial', true)
            ->where('activo', true)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Get terminal states.
     */
    public function getTerminalStates(): Collection
    {
        return $this->model->where('terminal', true)
            ->where('activo', true)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Get next possible states from current state.
     */
    public function getNextStates(string $estadoCodigo): Collection
    {
        $estado = $this->getByCodigo($estado);
        if (!$estado || !$estado->transiciones) {
            return collect([]);
        }

        $nextCodigos = $estado->transiciones;
        return $this->model->whereIn('codigo', $nextCodigos)
            ->where('activo', true)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Check if transition is valid.
     */
    public function isValidTransition(string $fromEstado, string $toEstado): bool
    {
        $nextStates = $this->getNextStates($fromEstado);
        return $nextStates->contains('codigo', $toEstado);
    }

    /**
     * Get estado statistics.
     */
    public function getStatistics(): array
    {
        return $this->model->where('activo', true)
            ->orderBy('orden')
            ->get()
            ->map(function ($estado) {
                return [
                    'codigo' => $estado->codigo,
                    'nombre' => $estado->nombre,
                    'color' => $estado->color,
                    'categoria' => $estado->categoria,
                    'orden' => $estado->orden,
                    'inicial' => $estado->inicial,
                    'terminal' => $estado->terminal,
                    'descripcion' => $estado->descripcion
                ];
            })
            ->toArray();
    }
}
