<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NumeroSolicitudService
{
    /**
     * Genera un número de solicitud automático.
     *
     * Formato: {secuencia:06d}-{vigencia}-{linea_credito}
     * Ejemplo: 000001-202501-03
     */
    public function generarNumeroSolicitud(string $lineaCredito): string
    {
        try {
            $now = Carbon::now();
            $vigencia = (int) $now->format('Ym');

            // Obtener siguiente secuencia para esta línea y vigencia
            $secuencia = $this->obtenerSiguienteSecuencia($lineaCredito, $vigencia);

            // Generar número de solicitud
            $numeroSolicitud = sprintf('%06d-%d-%s', $secuencia, $vigencia, $lineaCredito);

            Log::info('Número de solicitud generado', [
                'linea_credito' => $lineaCredito,
                'vigencia' => $vigencia,
                'secuencia' => $secuencia,
                'numero_solicitud' => $numeroSolicitud
            ]);

            return $numeroSolicitud;
        } catch (\Exception $e) {
            Log::error('Error al generar número de solicitud', [
                'linea_credito' => $lineaCredito,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Obtener la siguiente secuencia para una línea y vigencia
     */
    private function obtenerSiguienteSecuencia(string $lineaCredito, int $vigencia): int
    {
        // Usar una transacción para garantizar atomicidad
        return DB::transaction(function () use ($lineaCredito, $vigencia) {
            // Buscar registro existente
            $registro = DB::table('numero_solicitudes')
                ->where('linea_credito', $lineaCredito)
                ->where('vigencia', $vigencia)
                ->lockForUpdate() // Bloquear para evitar concurrencia
                ->first();

            if ($registro) {
                // Incrementar secuencia
                $nuevaSecuencia = $registro->numeric_secuencia + 1;

                DB::table('numero_solicitudes')
                    ->where('id', $registro->id)
                    ->update([
                        'numeric_secuencia' => $nuevaSecuencia,
                        'updated_at' => Carbon::now()
                    ]);

                Log::debug('Secuencia incrementada', [
                    'linea_credito' => $lineaCredito,
                    'vigencia' => $vigencia,
                    'secuencia_anterior' => $registro->numeric_secuencia,
                    'nueva_secuencia' => $nuevaSecuencia
                ]);

                return $nuevaSecuencia;
            } else {
                // Crear nuevo registro con secuencia 1
                $nuevaSecuencia = 1;
                $radicado = sprintf('%06d-%d-%s', $nuevaSecuencia, $vigencia, $lineaCredito);

                DB::table('numero_solicitudes')->insert([
                    'radicado' => $radicado,
                    'numeric_secuencia' => $nuevaSecuencia,
                    'linea_credito' => $lineaCredito,
                    'vigencia' => $vigencia,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);

                Log::debug('Nuevo registro creado', [
                    'linea_credito' => $lineaCredito,
                    'vigencia' => $vigencia,
                    'secuencia' => $nuevaSecuencia,
                    'radicado' => $radicado
                ]);

                return $nuevaSecuencia;
            }
        });
    }

    /**
     * Validar si un número de solicitud ya existe
     */
    public function existeNumeroSolicitud(string $numeroSolicitud): bool
    {
        try {
            $parsed = $this->parseNumeroSolicitud($numeroSolicitud);

            if (!$parsed) {
                return false;
            }

            // Verificar si existe en la tabla de solicitudes
            $exists = DB::table('solicitud_creditos')
                ->where('numero_solicitud', $numeroSolicitud)
                ->exists();

            if ($exists) {
                Log::info('Número de solicitud ya existe en solicitudes', [
                    'numero_solicitud' => $numeroSolicitud
                ]);
            }

            return $exists;
        } catch (\Exception $e) {
            Log::error('Error al validar existencia de número de solicitud', [
                'numero_solicitud' => $numeroSolicitud,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Parsear un número de solicitud en sus componentes
     */
    public function parseNumeroSolicitud(string $numeroSolicitud): ?array
    {
        try {
            if (!preg_match('/^(\d{6})-(\d{6})-(\d+)$/', $numeroSolicitud, $matches)) {
                return null;
            }

            return [
                'secuencia' => (int) $matches[1],
                'vigencia' => (int) $matches[2],
                'linea_credito' => $matches[3],
                'numero_solicitud' => $numeroSolicitud
            ];
        } catch (\Exception $e) {
            Log::error('Error al parsear número de solicitud', [
                'numero_solicitud' => $numeroSolicitud,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtener estadísticas de números de solicitud
     */
    public function obtenerEstadisticas(): array
    {
        try {
            $estadisticas = [
                'total_registros' => 0,
                'por_linea_credito' => [],
                'por_vigencia' => [],
                'ultima_actualizacion' => null
            ];

            $registros = DB::table('numero_solicitudes')
                ->select('linea_credito', 'vigencia', 'secuencia', 'updated_at')
                ->get();

            $estadisticas['total_registros'] = $registros->count();

            foreach ($registros as $registro) {
                // Contar por línea de crédito
                if (!isset($estadisticas['por_linea_credito'][$registro->linea_credito])) {
                    $estadisticas['por_linea_credito'][$registro->linea_credito] = 0;
                }
                $estadisticas['por_linea_credito'][$registro->linea_credito]++;

                // Contar por vigencia
                if (!isset($estadisticas['por_vigencia'][$registro->vigencia])) {
                    $estadisticas['por_vigencia'][$registro->vigencia] = 0;
                }
                $estadisticas['por_vigencia'][$registro->vigencia]++;

                // Última actualización
                if (
                    !$estadisticas['ultima_actualizacion'] ||
                    Carbon::parse($registro->updated_at) > Carbon::parse($estadisticas['ultima_actualizacion'])
                ) {
                    $estadisticas['ultima_actualizacion'] = $registro->updated_at;
                }
            }

            Log::info('Estadísticas de números de solicitud obtenidas', $estadisticas);

            return $estadisticas;
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de números de solicitud', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Reiniciar secuencia para una línea y vigencia específica
     */
    public function reiniciarSecuencia(string $lineaCredito, int $vigencia): bool
    {
        try {
            Log::info('Reiniciando secuencia de número de solicitud', [
                'linea_credito' => $lineaCredito,
                'vigencia' => $vigencia
            ]);

            // Usar transacción para atomicidad
            DB::transaction(function () use ($lineaCredito, $vigencia) {
                // Eliminar registro existente si existe
                DB::table('numero_solicitudes')
                    ->where('linea_credito', $lineaCredito)
                    ->where('vigencia', $vigencia)
                    ->delete();

                // Crear nuevo registro con secuencia 1
                $nuevaSecuencia = 1;
                $radicado = sprintf('%06d-%d-%s', $nuevaSecuencia, $vigencia, $lineaCredito);

                DB::table('numero_solicitudes')->insert([
                    'radicado' => $radicado,
                    'numeric_secuencia' => $nuevaSecuencia,
                    'linea_credito' => $lineaCredito,
                    'vigencia' => $vigencia,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            });

            Log::info('Secuencia reiniciada exitosamente', [
                'linea_credito' => $lineaCredito,
                'vigencia' => $vigencia
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al reiniciar secuencia de número de solicitud', [
                'linea_credito' => $lineaCredito,
                'vigencia' => $vigencia,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Validar formato de número de solicitud
     */
    public function validarFormatoNumeroSolicitud(string $numeroSolicitud): bool
    {
        try {
            // Formato esperado: {secuencia:06d}-{vigencia}-{linea_credito}
            // Ejemplo: 000001-202501-03

            $pattern = '/^\d{6}-\d{6}-\d+$/';

            if (!preg_match($pattern, $numeroSolicitud)) {
                return false;
            }

            $parts = explode('-', $numeroSolicitud);

            if (count($parts) !== 3) {
                return false;
            }

            $secuencia = (int) $parts[0];
            $vigencia = (int) $parts[1];
            $lineaCredito = $parts[2];

            // Validar rangos
            if ($secuencia < 1 || $secuencia > 999999) {
                return false;
            }

            if ($vigencia < 202000 || $vigencia > 209912) {
                return false;
            }

            if (empty($lineaCredito)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error al validar formato de número de solicitud', [
                'numero_solicitud' => $numeroSolicitud,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
