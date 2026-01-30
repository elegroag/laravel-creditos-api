<?php

namespace App\Services;

use App\Models\Postulacion;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PostulacionService
{
    /**
     * Crear una nueva postulación
     */
    public function crearPostulacion(array $data): Postulacion
    {
        try {
            Log::info('Creando postulación en servicio', [
                'monto_solicitado' => $data['monto_solicitado'] ?? null,
                'plazo_meses' => $data['plazo_meses'] ?? null
            ]);

            // Preparar datos con valores por defecto
            $postulacionData = array_merge([
                'estado' => 'POSTULADO',
                'timeline' => [],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ], $data);

            // Agregar evento inicial al timeline
            $postulacionData['timeline'][] = [
                'estado' => $postulacionData['estado'],
                'fecha' => Carbon::now()->toISOString(),
                'detalle' => 'Creación de postulación'
            ];

            return Postulacion::create($postulacionData);

        } catch (\Exception $e) {
            Log::error('Error en PostulacionService::crearPostulacion', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            throw $e;
        }
    }

    /**
     * Obtener postulaciones con filtros
     */
    public function obtenerPostulaciones(array $filtros = [], int $limit = 200, int $offset = 0): array
    {
        try {
            $query = Postulacion::query();

            // Aplicar filtros
            if (isset($filtros['estado'])) {
                $query->where('estado', $filtros['estado']);
            }

            if (isset($filtros['solicitante_nombre'])) {
                $query->where('solicitante->nombre', 'LIKE', '%' . $filtros['solicitante_nombre'] . '%');
            }

            if (isset($filtros['solicitante_documento'])) {
                $query->where('solicitante->numero_documento', $filtros['solicitante_documento']);
            }

            if (isset($filtros['monto_min'])) {
                $query->where('monto_solicitado', '>=', $filtros['monto_min']);
            }

            if (isset($filtros['monto_max'])) {
                $query->where('monto_solicitado', '<=', $filtros['monto_max']);
            }

            if (isset($filtros['fecha_desde'])) {
                $query->where('created_at', '>=', $filtros['fecha_desde']);
            }

            if (isset($filtros['fecha_hasta'])) {
                $query->where('created_at', '<=', $filtros['fecha_hasta']);
            }

            // Aplicar paginación
            if ($offset > 0) {
                $query->offset($offset);
            }

            if ($limit > 0) {
                $query->limit($limit);
            }

            $postulaciones = $query->orderBy('created_at', 'desc')->get();

            return $postulaciones->toArray();

        } catch (\Exception $e) {
            Log::error('Error en PostulacionService::obtenerPostulaciones', [
                'error' => $e->getMessage(),
                'filtros' => $filtros
            ]);

            throw $e;
        }
    }

    /**
     * Obtener postulación por ID
     */
    public function obtenerPostulacionPorId(string $id): ?Postulacion
    {
        try {
            return Postulacion::find($id);

        } catch (\Exception $e) {
            Log::error('Error en PostulacionService::obtenerPostulacionPorId', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Actualizar estado de postulación
     */
    public function actualizarEstado(string $id, string $estado, string $detalle = null): bool
    {
        try {
            $postulacion = Postulacion::find($id);

            if (!$postulacion) {
                return false;
            }

            $now = Carbon::now();
            $timeline = $postulacion->timeline ?? [];
            $timeline[] = [
                'estado' => $estado,
                'fecha' => $now->toISOString(),
                'detalle' => $detalle ?? 'Cambio de estado'
            ];

            $postulacion->update([
                'estado' => $estado,
                'timeline' => $timeline,
                'updated_at' => $now
            ]);

            Log::info('Estado de postulación actualizado', [
                'id' => $id,
                'nuevo_estado' => $estado,
                'detalle' => $detalle
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error en PostulacionService::actualizarEstado', [
                'id' => $id,
                'estado' => $estado,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Eliminar postulación
     */
    public function eliminarPostulacion(string $id): bool
    {
        try {
            $postulacion = Postulacion::find($id);

            if (!$postulacion) {
                return false;
            }

            $postulacion->delete();

            Log::info('Postulación eliminada', ['id' => $id]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error en PostulacionService::eliminarPostulacion', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Obtener estadísticas de postulaciones
     */
    public function obtenerEstadisticas(): array
    {
        try {
            $estadisticas = [];

            // Conteo total
            $estadisticas['total'] = Postulacion::count();

            // Conteo por estado
            $conteoEstados = Postulacion::selectRaw('estado, COUNT(*) as count')
                                   ->groupBy('estado')
                                   ->pluck('count', 'estado')
                                   ->toArray();
            $estadisticas['conteo_estados'] = $conteoEstados;

            // Estadísticas de montos
            $estadisticas['monto_total'] = Postulacion::sum('monto_solicitado') ?? 0;
            $estadisticas['monto_promedio'] = Postulacion::avg('monto_solicitado') ?? 0;
            $estadisticas['monto_minimo'] = Postulacion::min('monto_solicitado') ?? 0;
            $estadisticas['monto_maximo'] = Postulacion::max('monto_solicitado') ?? 0;

            // Estadísticas de plazos
            $estadisticas['plazo_promedio'] = Postulacion::avg('plazo_meses') ?? 0;
            $estadisticas['plazo_minimo'] = Postulacion::min('plazo_meses') ?? 0;
            $estadisticas['plazo_maximo'] = Postulacion::max('plazo_meses') ?? 0;

            // Postulaciones por mes
            $postulacionesPorMes = Postulacion::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mes, COUNT(*) as count')
                                         ->groupBy('mes')
                                         ->orderBy('mes', 'desc')
                                         ->limit(12)
                                         ->pluck('count', 'mes')
                                         ->toArray();
            $estadisticas['postulaciones_por_mes'] = $postulacionesPorMes;

            // Postulaciones recientes
            $recientes = Postulacion::orderBy('created_at', 'desc')
                                   ->limit(5)
                                   ->get(['id', 'estado', 'monto_solicitado', 'created_at'])
                                   ->toArray();
            $estadisticas['recientes'] = $recientes;

            return $estadisticas;

        } catch (\Exception $e) {
            Log::error('Error en PostulacionService::obtenerEstadisticas', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Buscar postulaciones
     */
    public function buscarPostulaciones(string $termino, array $filtros = [], int $limit = 50): array
    {
        try {
            $query = Postulacion::where(function ($q) use ($termino) {
                $q->where('solicitante->nombre', 'LIKE', '%' . $termino . '%')
                  ->orWhere('solicitante->apellido', 'LIKE', '%' . $termino . '%')
                  ->orWhere('solicitante->email', 'LIKE', '%' . $termino . '%')
                  ->orWhere('solicitante->numero_documento', 'LIKE', '%' . $termino . '%');
            });

            // Aplicar filtros adicionales
            if (isset($filtros['estado'])) {
                $query->where('estado', $filtros['estado']);
            }

            $postulaciones = $query->orderBy('created_at', 'desc')
                                   ->limit($limit)
                                   ->get();

            return $postulaciones->toArray();

        } catch (\Exception $e) {
            Log::error('Error en PostulacionService::buscarPostulaciones', [
                'termino' => $termino,
                'filtros' => $filtros,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Validar datos de postulación
     */
    public function validarDatosPostulacion(array $data): array
    {
        $errores = [];

        // Validar solicitante
        if (!isset($data['solicitante']) || !is_array($data['solicitante'])) {
            $errores[] = 'Los datos del solicitante son requeridos';
        } else {
            $solicitante = $data['solicitante'];
            
            if (empty($solicitante['nombre'])) {
                $errores[] = 'El nombre del solicitante es requerido';
            }
            
            if (empty($solicitante['apellido'])) {
                $errores[] = 'El apellido del solicitante es requerido';
            }
            
            if (empty($solicitante['tipo_documento'])) {
                $errores[] = 'El tipo de documento es requerido';
            }
            
            if (empty($solicitante['numero_documento'])) {
                $errores[] = 'El número de documento es requerido';
            }
            
            if (isset($solicitante['email']) && !filter_var($solicitante['email'], FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'El email del solicitante no es válido';
            }
        }

        // Validar monto
        if (!isset($data['monto_solicitado'])) {
            $errores[] = 'El monto solicitado es requerido';
        } elseif (!is_numeric($data['monto_solicitado']) || $data['monto_solicitado'] <= 0) {
            $errores[] = 'El monto solicitado debe ser un número mayor a 0';
        }

        // Validar plazo
        if (!isset($data['plazo_meses'])) {
            $errores[] = 'El plazo en meses es requerido';
        } elseif (!is_int($data['plazo_meses']) || $data['plazo_meses'] <= 0 || $data['plazo_meses'] > 360) {
            $errores[] = 'El plazo debe ser un número entero entre 1 y 360 meses';
        }

        return $errores;
    }

    /**
     * Calcular cuota mensual aproximada
     */
    public function calcularCuotaAproximada(float $monto, int $plazoMeses, float $tasaAnual = 0.12): array
    {
        try {
            // Cálculo simple de cuota (fórmula de amortización)
            $tasaMensual = $tasaAnual / 12;
            
            if ($tasaMensual == 0) {
                $cuota = $monto / $plazoMeses;
            } else {
                $cuota = $monto * ($tasaMensual * pow(1 + $tasaMensual, $plazoMeses)) / 
                         (pow(1 + $tasaMensual, $plazoMeses) - 1);
            }

            return [
                'monto' => $monto,
                'plazo_meses' => $plazoMeses,
                'tasa_anual' => ($tasaAnual * 100) . '%',
                'cuota_mensual' => round($cuota, 2),
                'total_intereses' => round(($cuota * $plazoMeses) - $monto, 2),
                'total_pagar' => round($cuota * $plazoMeses, 2)
            ];

        } catch (\Exception $e) {
            Log::error('Error en PostulacionService::calcularCuotaAproximada', [
                'monto' => $monto,
                'plazo_meses' => $plazoMeses,
                'tasa_anual' => $tasaAnual,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Verificar elegibilidad de postulación
     */
    public function verificarElegibilidad(array $data): array
    {
        try {
            $resultados = [
                'elegible' => true,
                'restricciones' => [],
                'recomendaciones' => []
            ];

            $monto = $data['monto_solicitado'] ?? 0;
            $plazo = $data['plazo_meses'] ?? 0;

            // Validar monto mínimo y máximo
            if ($monto < 1000000) {
                $resultados['elegible'] = false;
                $resultados['restricciones'][] = 'El monto mínimo es de $1,000,000';
                $resultados['recomendaciones'][] = 'Considere solicitar un monto mayor';
            }

            if ($monto > 50000000) {
                $resultados['elegible'] = false;
                $resultados['restricciones'][] = 'El monto máximo es de $50,000,000';
                $resultados['recomendaciones'][] = 'Considere solicitar un monto menor';
            }

            // Validar plazo mínimo y máximo
            if ($plazo < 6) {
                $resultados['elegible'] = false;
                $resultados['restricciones'][] = 'El plazo mínimo es de 6 meses';
                $resultados['recomendaciones'][] = 'Considere un plazo mayor';
            }

            if ($plazo > 360) {
                $resultados['elegible'] = false;
                $resultados['restricciones'][] = 'El plazo máximo es de 360 meses (30 años)';
                $resultados['recomendaciones'][] = 'Considere un plazo menor';
            }

            // Validar relación monto-plazo
            if ($monto / $plazo > 1000000) {
                $resultados['restricciones'][] = 'La cuota mensual estimada es muy alta';
                $resultados['recomendaciones'][] = 'Considere aumentar el plazo o reducir el monto';
            }

            return $resultados;

        } catch (\Exception $e) {
            Log::error('Error en PostulacionService::verificarElegibilidad', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
