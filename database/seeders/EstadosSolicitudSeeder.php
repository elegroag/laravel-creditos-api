<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\EstadoSolicitud;

class EstadosSolicitudSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insertar estados sin duplicar (usar insertOrIgnore para evitar conflictos)
        $estados = [
            [
                'id' => 'POSTULADO',
                'nombre' => 'Postulado',
                'descripcion' => 'Solicitud recién creada y postulada',
                'orden' => 1,
                'color' => '#6B7280',
                'activo' => true
            ],
            [
                'id' => 'DOCUMENTOS_CARGADOS',
                'nombre' => 'Documentos cargados',
                'descripcion' => 'Todos los documentos han sido cargados',
                'orden' => 2,
                'color' => '#3B82F6',
                'activo' => true
            ],
            [
                'id' => 'ENVIADO_VALIDACION',
                'nombre' => 'Enviado para validación',
                'descripcion' => 'Enviado para validación de asesores',
                'orden' => 3,
                'color' => '#F59E0B',
                'activo' => true
            ],
            [
                'id' => 'PENDIENTE_FIRMADO',
                'nombre' => 'Pendiente de firmado',
                'descripcion' => 'Solicitud en proceso de firmado de solicitud de crédito',
                'orden' => 4,
                'color' => '#f5e20bff',
                'activo' => true
            ],
            [
                'id' => 'FIRMADO',
                'nombre' => 'Firmado',
                'descripcion' => 'Documentos de crédito firmados',
                'orden' => 5,
                'color' => '#0D9488',
                'activo' => true
            ],
            [
                'id' => 'ENVIADO_PENDIENTE_APROBACION',
                'nombre' => 'Enviado (pendiente de aprobación)',
                'descripcion' => 'Solicitud enviada y pendiente de aprobación',
                'orden' => 6,
                'color' => '#8B5CF6',
                'activo' => true
            ],
            [
                'id' => 'APROBADA',
                'nombre' => 'Aprobada',
                'descripcion' => 'Solicitud de crédito aprobada',
                'orden' => 7,
                'color' => '#10B981',
                'activo' => true
            ],
            [
                'id' => 'RECHAZADA',
                'nombre' => 'Rechazada',
                'descripcion' => 'Solicitud de crédito rechazada',
                'orden' => 8,
                'color' => '#EF4444',
                'activo' => true
            ],
            [
                'id' => 'DESESTIMADA',
                'nombre' => 'Desestimada',
                'descripcion' => 'Solicitud desestimada por falta de requisitos',
                'orden' => 9,
                'color' => '#F97316',
                'activo' => true
            ],
            [
                'id' => 'CANCELADA',
                'nombre' => 'Cancelada',
                'descripcion' => 'Solicitud cancelada por el solicitante',
                'orden' => 10,
                'color' => '#6B7280',
                'activo' => true
            ],
            [
                'id' => 'DESISTE',
                'nombre' => 'Desiste',
                'descripcion' => 'El solicitante desiste de continuar con el proceso',
                'orden' => 11,
                'color' => '#6B7280',
                'activo' => true
            ]
        ];

        // Insertar estados usando insertOrIgnore para evitar duplicados
        DB::table('estados_solicitud')->insertOrIgnore($estados);

        $this->command->info('Estados de solicitud creados exitosamente');
        $this->command->info('');
        $this->command->info('Flujo de estados del sistema:');
        $this->command->info('1. POSTULADO → DOCUMENTOS_CARGADOS → ENVIADO_VALIDACION → PENDIENTE_FIRMADO → FIRMADO → ENVIADO_PENDIENTE_APROBACION → APROBADA');
        $this->command->info('2. Cualquier estado → RECHAZADA / DESESTIMADA / CANCELADA / DESISTE');
        $this->command->info('');
        $this->command->info('Total de estados configurados: ' . count($estados));
    }
}
