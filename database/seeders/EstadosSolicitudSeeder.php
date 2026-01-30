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
        // Limpiar tabla antes de insertar
        DB::table('estados_solicitud')->delete();

        $estados = [
            [
                'codigo' => 'POSTULADO',
                'nombre' => 'Postulado',
                'descripcion' => 'Solicitud recién creada y enviada para revisión inicial',
                'orden' => 1,
                'color' => '#3B82F6',
                'activo' => true
            ],
            [
                'codigo' => 'EN_REVISION',
                'nombre' => 'En Revisión',
                'descripcion' => 'Solicitud siendo revisada por el equipo de crédito',
                'orden' => 2,
                'color' => '#F59E0B',
                'activo' => true
            ],
            [
                'codigo' => 'REQUIERE_DOCUMENTOS',
                'nombre' => 'Requiere Documentos',
                'descripcion' => 'Se solicitan documentos adicionales para continuar el proceso',
                'orden' => 3,
                'color' => '#EF4444',
                'activo' => true
            ],
            [
                'codigo' => 'PRE_APROBADO',
                'nombre' => 'Pre-Aprobado',
                'descripcion' => 'Solicitud aprobada preliminarmente, pendiente de verificación final',
                'orden' => 4,
                'color' => '#8B5CF6',
                'activo' => true
            ],
            [
                'codigo' => 'EN_VERIFICACION',
                'nombre' => 'En Verificación',
                'descripcion' => 'Verificación final de documentos y requisitos',
                'orden' => 5,
                'color' => '#06B6D4',
                'activo' => true
            ],
            [
                'codigo' => 'APROBADO',
                'nombre' => 'Aprobado',
                'descripcion' => 'Solicitud aprobada y lista para desembolso',
                'orden' => 6,
                'color' => '#10B981',
                'activo' => true
            ],
            [
                'codigo' => 'RECHAZADO',
                'nombre' => 'Rechazado',
                'descripcion' => 'Solicitud rechazada por no cumplir requisitos',
                'orden' => 7,
                'color' => '#EF4444',
                'activo' => true
            ],
            [
                'codigo' => 'CANCELADO',
                'nombre' => 'Cancelado',
                'descripcion' => 'Solicitud cancelada por el solicitante',
                'orden' => 8,
                'color' => '#6B7280',
                'activo' => true
            ],
            [
                'codigo' => 'DESISTE',
                'nombre' => 'Desiste',
                'descripcion' => 'El solicitante desiste de continuar con el proceso',
                'orden' => 9,
                'color' => '#6B7280',
                'activo' => true
            ],
            [
                'codigo' => 'FINALIZADO',
                'nombre' => 'Finalizado',
                'descripcion' => 'Crédito desembolsado y proceso completado',
                'orden' => 10,
                'color' => '#059669',
                'activo' => true
            ],
            [
                'codigo' => 'EN_MORA',
                'nombre' => 'En Mora',
                'descripcion' => 'Crédito con pagos atrasados',
                'orden' => 11,
                'color' => '#DC2626',
                'activo' => true
            ],
            [
                'codigo' => 'CASTIGADO',
                'nombre' => 'Castigado',
                'descripcion' => 'Crédito castigado por incumplimiento grave',
                'orden' => 12,
                'color' => '#7C2D12',
                'activo' => true
            ]
        ];

        foreach ($estados as $estado) {
            EstadoSolicitud::create($estado);
        }

        $this->command->info('Estados de solicitud creados exitosamente');
        $this->command->info('');
        $this->command->info('Flujo de estados del sistema:');
        $this->command->info('1. POSTULADO → EN_REVISION → PRE_APROBADO → EN_VERIFICACION → APROBADO → FINALIZADO');
        $this->command->info('2. POSTULADO → REQUIERE_DOCUMENTOS → EN_REVISION → ...');
        $this->command->info('3. Cualquier estado → RECHAZADO / CANCELADO / DESISTE');
        $this->command->info('4. APROBADO → FINALIZADO → EN_MORA → CASTIGADO');
        $this->command->info('');
        $this->command->info('Total de estados configurados: ' . count($estados));
    }
}
