<?php

namespace App\Services;

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

            return $numeroSolicitud;
        } catch (\Exception $e) {
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

            return $exists;
        } catch (\Exception $e) {
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

            return $estadisticas;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Reiniciar secuencia para una línea y vigencia específica
     */
    public function reiniciarSecuencia(string $lineaCredito, int $vigencia): bool
    {
        try {
            return DB::transaction(function () use ($lineaCredito, $vigencia) {
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

                return true;
            });
        } catch (\Exception $e) {
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
            return false;
        }
    }
}
