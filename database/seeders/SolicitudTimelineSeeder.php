<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\SolicitudTimeline;
use App\Models\SolicitudCredito;
use App\Models\EstadoSolicitud;
use App\Models\User;

class SolicitudTimelineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('solicitud_timeline')->delete();

        // Obtener solicitudes y usuarios
        $solicitudes = SolicitudCredito::all();
        $adminUser = User::where('username', 'admin')->first();
        $asesorUser = User::where('username', 'asesor1')->first();

        // Obtener todos los estados
        $estados = EstadoSolicitud::all()->keyBy('codigo');

        foreach ($solicitudes as $solicitud) {
            // Generar timeline según el estado actual y tipo de solicitud
            $timelineEntries = $this->generarTimelineParaSolicitud($solicitud, $estados, $adminUser, $asesorUser);

            foreach ($timelineEntries as $entry) {
                SolicitudTimeline::create($entry);
            }
        }

        $this->command->info('Timeline de solicitudes creado exitosamente');
        $this->command->info('');
        $this->command->info('Resumen de timeline creado:');
        $this->command->info('Total entradas: ' . SolicitudTimeline::count());
        $this->command->info('Promedio por solicitud: ' . round(SolicitudTimeline::count() / $solicitudes->count(), 1));
        $this->command->info('');
        $this->command->info('Tipos de entradas generadas:');
        $this->command->info('- Creación de solicitud (automático)');
        $this->command->info('- Cambios de estado (automático/manual)');
        $this->command->info('- Actualizaciones de documentos');
        $this->command->info('- Observaciones del asesor');
        $this->command->info('- Notificaciones automáticas');
    }

    /**
     * Generar timeline según el estado y tipo de solicitud
     */
    private function generarTimelineParaSolicitud(SolicitudCredito $solicitud, $estados, User $adminUser, User $asesorUser): array
    {
        $entries = [];
        $numeroSolicitud = $solicitud->numero_solicitud;
        $estadoActual = $solicitud->estado_codigo;
        $fechaCreacion = $solicitud->created_at;

        // 1. Entrada inicial de creación (automática)
        $entries[] = [
            'solicitud_id' => $solicitud->numero_solicitud,
            'estado_codigo' => 'POSTULADO',
            'fecha' => $fechaCreacion,
            'detalle' => "Solicitud {$numeroSolicitud} creada exitosamente en el sistema",
            'usuario_username' => $solicitud->owner_username,
            'automatico' => true
        ];

        // 2. Entrada de revisión inicial (1-2 días después)
        if (in_array($estadoActual, ['EN_REVISION', 'PRE_APROBADO', 'APROBADO', 'FINALIZADO'])) {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'EN_REVISION',
                'fecha' => $fechaCreacion->copy()->addDays(rand(1, 2)),
                'detalle' => "Solicitud {$numeroSolicitud} en revisión inicial por el equipo de crédito",
                'usuario_username' => $asesorUser->username,
                'automatico' => true
            ];
        }

        // 3. Entrada de requerimiento de documentos (si aplica)
        if (in_array($estadoActual, ['REQUIERE_DOCUMENTOS', 'EN_REVISION', 'PRE_APROBADO', 'APROBADO', 'FINALIZADO'])) {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'REQUIERE_DOCUMENTOS',
                'fecha' => $fechaCreacion->copy()->addDays(rand(2, 4)),
                'detalle' => "Se solicitan documentos adicionales para continuar con el proceso de evaluación",
                'usuario_username' => $asesorUser->username,
                'automatico' => true
            ];
        }

        // 4. Entrada de pre-aprobación (si aplica)
        if (in_array($estadoActual, ['PRE_APROBADO', 'APROBADO', 'FINALIZADO'])) {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'PRE_APROBADO',
                'fecha' => $fechaCreacion->copy()->addDays(rand(3, 7)),
                'detalle' => "Solicitud {$numeroSolicitud} pre-aprobada por valor de \$" . number_format($solicitud->monto_aprobado, 0, ',', '.'),
                'usuario_username' => $asesorUser->username,
                'automatico' => true
            ];
        }

        // 5. Entrada de verificación final (si aplica)
        if (in_array($estadoActual, ['EN_VERIFICACION', 'APROBADO', 'FINALIZADO'])) {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'EN_VERIFICACION',
                'fecha' => $fechaCreacion->copy()->addDays(rand(4, 8)),
                'detalle' => "Iniciando verificación final de documentos y requisitos",
                'usuario_username' => $asesorUser->username,
                'automatico' => true
            ];
        }

        // 6. Entrada de aprobación (si aplica)
        if (in_array($estadoActual, ['APROBADO', 'FINALIZADO'])) {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'APROBADO',
                'fecha' => $fechaCreacion->copy()->addDays(rand(5, 10)),
                'detalle' => "Solicitud {$numeroSolicitud} aprobada por \$" . number_format($solicitud->monto_aprobado, 0, ',', '.') . " con tasa del {$solicitud->tasa_interes}%",
                'usuario_username' => $adminUser->username,
                'automatico' => false
            ];
        }

        // 7. Entrada de finalización (si aplica)
        if ($estadoActual === 'FINALIZADO') {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'FINALIZADO',
                'fecha' => $fechaCreacion->copy()->addDays(rand(10, 15)),
                'detalle' => "Crédito desembolsado exitosamente. Solicitud {$numeroSolicitud} finalizada",
                'usuario_username' => $asesorUser->username,
                'automatico' => true
            ];
        }

        // 8. Entrada de rechazo (si aplica)
        if ($estadoActual === 'RECHAZADO') {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'RECHAZADO',
                'fecha' => $fechaCreacion->copy()->addDays(rand(3, 7)),
                'detalle' => "Solicitud {$numeroSolicitud} rechazada por no cumplir con los requisitos mínimos",
                'usuario_username' => $asesorUser->username,
                'automatico' => false
            ];
        }

        // 9. Entrada de cancelación (si aplica)
        if ($estadoActual === 'CANCELADO') {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'CANCELADO',
                'fecha' => $fechaCreacion->copy()->addDays(rand(2, 5)),
                'detalle' => "Solicitud {$numeroSolicitud} cancelada a petición del solicitante",
                'usuario_username' => $solicitud->owner_username,
                'automatico' => false
            ];
        }

        // 10. Entrada de desistimiento (si aplica)
        if ($estadoActual === 'DESISTE') {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'DESISTE',
                'fecha' => $fechaCreacion->copy()->addDays(rand(1, 4)),
                'detalle' => "El solicitante ha desistido de continuar con el proceso de solicitud {$numeroSolicitud}",
                'usuario_username' => $solicitud->owner_username,
                'automatico' => false
            ];
        }

        // 11. Entradas adicionales según tipo de crédito
        $entriesAdicionales = $this->generarEntradasEspecificas($solicitud, $adminUser, $asesorUser);
        $entries = array_merge($entries, $entriesAdicionales);

        // Ordenar por fecha
        usort($entries, function ($a, $b) {
            return strtotime($a['fecha']) - strtotime($b['fecha']);
        });

        return $entries;
    }

    /**
     * Generar entradas específicas según tipo de crédito
     */
    private function generarEntradasEspecificas(SolicitudCredito $solicitud, User $adminUser, User $asesorUser): array
    {
        $entries = [];
        $numeroSolicitud = $solicitud->numero_solicitud;
        $destino = strtolower($solicitud->destino_credito ?? '');
        $fechaCreacion = $solicitud->created_at;

        // Entradas específicas para vivienda
        if (strpos($destino, 'vivienda') !== false) {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'EN_REVISION',
                'fecha' => $fechaCreacion->copy()->addDays(1),
                'detalle' => "Verificación de documentos de propiedad y avalúo del inmueble",
                'usuario_username' => $asesorUser->username,
                'automatico' => true
            ];

            if ($solicitud->estado_codigo === 'APROBADO' || $solicitud->estado_codigo === 'FINALIZADO') {
                $entries[] = [
                    'solicitud_id' => $solicitud->numero_solicitud,
                    'estado_codigo' => 'APROBADO',
                    'fecha' => $fechaCreacion->copy()->addDays(8),
                    'detalle' => "Aprobación con garantía hipotecaria sobre el inmueble",
                    'usuario_username' => $adminUser->username,
                    'automatico' => false
                ];
            }
        }

        // Entradas específicas para educación
        if (strpos($destino, 'educacion') !== false || strpos($destino, 'educativo') !== false) {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'EN_REVISION',
                'fecha' => $fechaCreacion->copy()->addDays(2),
                'detalle' => "Verificación de matrícula y certificado académico con la institución educativa",
                'usuario_username' => $asesorUser->username,
                'automatico' => true
            ];

            if ($solicitud->estado_codigo === 'APROBADO' || $solicitud->estado_codigo === 'FINALIZADO') {
                $entries[] = [
                    'solicitud_id' => $solicitud->numero_solicitud,
                    'estado_codigo' => 'APROBADO',
                    'fecha' => $fechaCreacion->copy()->addDays(7),
                    'detalle' => "Aprobación con pago directo a la institución educativa",
                    'usuario_username' => $adminUser->username,
                    'automatico' => false
                ];
            }
        }

        // Entradas específicas para empresa
        if (strpos($destino, 'empresa') !== false || strpos($destino, 'capital') !== false) {
            $entries[] = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'estado_codigo' => 'EN_REVISION',
                'fecha' => $fechaCreacion->copy()->addDays(3),
                'detalle' => "Análisis de estados financieros y capacidad de pago de la empresa",
                'usuario_username' => $asesorUser->username,
                'automatico' => true
            ];

            if ($solicitud->estado_codigo === 'APROBADO' || $solicitud->estado_codigo === 'FINALIZADO') {
                $entries[] = [
                    'solicitud_id' => $solicitud->numero_solicitud,
                    'estado_codigo' => 'APROBADO',
                    'fecha' => $fechaCreacion->copy()->addDays(9),
                    'detalle' => "Aprobación con garantías corporativas y flujo de caja verificado",
                    'usuario_username' => $adminUser->username,
                    'automatico' => false
                ];
            }
        }

        return $entries;
    }
}
