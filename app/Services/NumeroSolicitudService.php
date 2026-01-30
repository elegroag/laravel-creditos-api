<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NumeroSolicitudService
{
    /**
     * Genera un número de solicitud automático usando la colección numero_solicitudes.
     *
     * Formato: {secuencia:06d}-{vigencia}-{linea_credito}
     * Ejemplo: 000001-202501-03
     */
    public function generarNumeroSolicitud(string $lineaCredito): string
    {
        try {
            Log::info('Generando número de solicitud', ['linea_credito' => $lineaCredito]);

            // Obtener vigencia actual (YYYYMM)
            $now = Carbon::now();
            $vigencia = (int) $now->format('Ym');

            // Buscar y actualizar el número de secuencia para esta línea y vigencia
            $numeroSolicitud = $this->buscarOCrearNumeroSolicitud($lineaCredito, $vigencia);

            Log::info('Número de solicitud generado', [
                'linea_credito' => $lineaCredito,
                'vigencia' => $vigencia,
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
     * Busca o crea un registro de número de solicitud
     */
    private function buscarOCrearNumeroSolicitud(string $lineaCredito, int $vigencia): string
    {
        // En una implementación real con MongoDB, esto buscaría en la colección numero_solicitudes
        // Por ahora, simulamos la lógica con un contador en memoria o base de datos

        // Simulación: buscar registro existente
        $existing = $this->buscarRegistroExistente($lineaCredito, $vigencia);

        if ($existing) {
            // Incrementar la secuencia
            $newSecuencia = $existing['numeric_secuencia'] + 1;
            $this->actualizarRegistro($existing['_id'], $newSecuencia);
        } else {
            // Crear nuevo registro
            $newSecuencia = 1;
            $this->crearRegistro($lineaCredito, $vigencia, $newSecuencia);
        }

        // Generar radicado
        $radicado = sprintf('%06d-%d-%s', $newSecuencia, $vigencia, $lineaCredito);

        return $radicado;
    }

    /**
     * Buscar registro existente (simulado)
     */
    private function buscarRegistroExistente(string $lineaCredito, int $vigencia): ?array
    {
        // En una implementación real, esto sería:
        // db.numero_solicitudes.find_one({"linea_credito": linea_credito, "vigencia": vigencia})

        // Por ahora, simulamos con un archivo o base de datos simple
        $cacheKey = "numero_solicitud_{$lineaCredito}_{$vigencia}";

        // Simulación de búsqueda en cache/base de datos
        if (cache()->has($cacheKey)) {
            $data = cache()->get($cacheKey);
            return [
                '_id' => $data['id'],
                'numeric_secuencia' => $data['secuencia']
            ];
        }

        return null;
    }

    /**
     * Actualizar registro existente (simulado)
     */
    private function actualizarRegistro(string $id, int $newSecuencia): void
    {
        // En una implementación real, esto sería:
        // db.numero_solicitudes.update_one(
        //     {"_id": existing["_id"]},
        //     {
        //         "$set": {
        //             "numeric_secuencia": new_secuencia,
        //             "updatedAt": datetime.now()
        //         }
        //     }
        // )

        // Por ahora, simulamos con cache
        $cacheKey = $this->getCacheKeyFromId($id);

        cache()->put($cacheKey, [
            'id' => $id,
            'secuencia' => $newSecuencia,
            'updated_at' => Carbon::now()->toISOString()
        ]);

        Log::debug('Registro de número de solicitud actualizado', [
            'id' => $id,
            'new_secuencia' => $newSecuencia
        ]);
    }

    /**
     * Crear nuevo registro (simulado)
     */
    private function crearRegistro(string $lineaCredito, int $vigencia, int $secuencia): void
    {
        // En una implementación real, esto sería:
        // db.numero_solicitudes.insert_one({
        //     "linea_credito": linea_credito,
        //     "vigencia": vigencia,
        //     "numeric_secuencia": new_secuencia,
        //     "createdAt": datetime.now(),
        //     "updatedAt": datetime.now()
        // })

        // Por ahora, simulamos con cache
        $id = Str::uuid()->toString();
        $cacheKey = "numero_solicitud_{$lineaCredito}_{$vigencia}";

        cache()->put($cacheKey, [
            'id' => $id,
            'secuencia' => $secuencia,
            'created_at' => Carbon::now()->toISOString(),
            'updated_at' => Carbon::now()->toISOString()
        ]);

        Log::debug('Nuevo registro de número de solicitud creado', [
            'id' => $id,
            'linea_credito' => $lineaCredito,
            'vigencia' => $vigencia,
            'secuencia' => $secuencia
        ]);
    }

    /**
     * Obtener cache key desde ID (simulado)
     */
    private function getCacheKeyFromId(string $id): string
    {
        // En una implementación real, esto buscaría el ID en la base de datos
        // Por ahora, simulamos la recuperación del cache key

        // Buscar en todos los posibles cache keys
        $pattern = 'numero_solicitud_*';
        $keys = cache()->getKeys($pattern);

        foreach ($keys as $key) {
            $data = cache()->get($key);
            if (isset($data['id']) && $data['id'] === $id) {
                return $key;
            }
        }

        return '';
    }

    /**
     * Obtener estadísticas de números de solicitud
     */
    public function obtenerEstadisticas(): array
    {
        try {
            Log::info('Obteniendo estadísticas de números de solicitud');

            $estadisticas = [
                'total_registros' => 0,
                'por_linea_credito' => [],
                'por_vigencia' => [],
                'ultima_actualizacion' => null
            ];

            // En una implementación real, esto consultaría la base de datos
            // Por ahora, simulamos con los datos en cache

            $pattern = 'numero_solicitud_*';
            $keys = cache()->getKeys($pattern);

            $estadisticas['total_registros'] = count($keys);

            foreach ($keys as $key) {
                $data = cache()->get($key);
                if ($data) {
                    // Extraer información del cache key
                    $parts = explode('_', $key);
                    if (count($parts) >= 4) {
                        $lineaCredito = $parts[2];
                        $vigencia = $parts[3];

                        // Contar por línea de crédito
                        if (!isset($estadisticas['por_linea_credito'][$lineaCredito])) {
                            $estadisticas['por_linea_credito'][$lineaCredito] = 0;
                        }
                        $estadisticas['por_linea_credito'][$lineaCredito]++;

                        // Contar por vigencia
                        if (!isset($estadisticas['por_vigencia'][$vigencia])) {
                            $estadisticas['por_vigencia'][$vigencia] = 0;
                        }
                        $estadisticas['por_vigencia'][$vigencia]++;

                        // Última actualización
                        if (isset($data['updated_at'])) {
                            $updatedAt = Carbon::parse($data['updated_at']);
                            if (!$estadisticas['ultima_actualizacion'] || $updatedAt > Carbon::parse($estadisticas['ultima_actualizacion'])) {
                                $estadisticas['ultima_actualizacion'] = $data['updated_at'];
                            }
                        }
                    }
                }
            }

            Log::info('Estadísticas de números de solicitud obtenidas', $estadisticas);

            return $estadisticas;
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de números de solicitud', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
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

            $cacheKey = "numero_solicitud_{$lineaCredito}_{$vigencia}";

            // Eliminar registro existente
            cache()->forget($cacheKey);

            // Crear nuevo registro con secuencia 1
            $this->crearRegistro($lineaCredito, $vigencia, 1);

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

            if ($vigencia < 202001 || $vigencia > 209912) {
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

    /**
     * Parsear número de solicitud
     */
    public function parsearNumeroSolicitud(string $numeroSolicitud): ?array
    {
        try {
            if (!$this->validarFormatoNumeroSolicitud($numeroSolicitud)) {
                return null;
            }

            $parts = explode('-', $numeroSolicitud);

            return [
                'secuencia' => (int) $parts[0],
                'vigencia' => (int) $parts[1],
                'linea_credito' => $parts[2],
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
}
