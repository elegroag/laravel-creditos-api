<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\NumeroSolicitud;

class NumeroSolicitudSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('numero_solicitudes')->delete();

        // Crear secuencias para los últimos 3 años y diferentes líneas de crédito
        $secuencias = [
            [
                'radicado' => '000001-202501-03',
                'numeric_secuencia' => 1,
                'linea_credito' => '03',
                'vigencia' => 202501
            ],
            [
                'radicado' => '000002-202501-03',
                'numeric_secuencia' => 2,
                'linea_credito' => '03',
                'vigencia' => 202501
            ],
            [
                'radicado' => '000003-202501-03',
                'numeric_secuencia' => 3,
                'linea_credito' => '03',
                'vigencia' => 202501
            ],
            [
                'radicado' => '000001-202401-03',
                'numeric_secuencia' => 1,
                'linea_credito' => '03',
                'vigencia' => 202401
            ],
            [
                'radicado' => '000002-202401-03',
                'numeric_secuencia' => 2,
                'linea_credito' => '03',
                'vigencia' => 202401
            ],
            [
                'radicado' => '000003-202401-03',
                'numeric_secuencia' => 3,
                'linea_credito' => '03',
                'vigencia' => 202401
            ],
            [
                'radicado' => '000001-202301-03',
                'numeric_secuencia' => 1,
                'linea_credito' => '03',
                'vigencia' => 202301
            ],
            [
                'radicado' => '000002-202301-03',
                'numeric_secuencia' => 2,
                'linea_credito' => '03',
                'vigencia' => 202301
            ],
            [
                'radicado' => '000003-202301-03',
                'numeric_secuencia' => 3,
                'linea_credito' => '03',
                'vigencia' => 202301
            ],
            [
                'radicado' => '000001-202201-01',
                'numeric_secuencia' => 1,
                'linea_credito' => '01',
                'vigencia' => 202201
            ],
            [
                'radicado' => '000002-202201-01',
                'numeric_secuencia' => 2,
                'linea_credito' => '01',
                'vigencia' => 202201
            ],
            [
                'radicado' => '000003-202201-01',
                'numeric_secuencia' => 3,
                'linea_credito' => '01',
                'vigencia' => 202201
            ]
        ];

        foreach ($secuencias as $secuencia) {
            NumeroSolicitud::create($secuencia);
        }

        $this->command->info('Secuencias de números de solicitud creadas exitosamente');
        $this->command->info('');
        $this->command->info('Resumen de secuencias creadas:');
        $this->command->info('Año 2025 (3 secuencias):');
        $this->command->info('  - 000001-202501-03 (siguiente: 000002-202501-03)');
        $this->command->info('  - 000002-202501-03 (siguiente: 000003-202501-03)');
        $this->command->info('  - 000003-202501-03 (siguiente: 000004-202501-03)');
        $this->command->info('Año 2024 (3 secuencias):');
        $this->command->info('  - 000001-202401-03 (siguiente: 000002-202401-03)');
        $this->command->info('  - 000002-202401-03 (siguiente: 000003-202401-03)');
        $this->command->info('  - 000003-202401-03 (siguiente: 000004-202401-03)');
        $this->command->info('Año 2023 (3 secuencias):');
        $this->command->info('  - 000001-202301-03 (siguiente: 000002-202301-03)');
        $this->command->info('  - 000002-202301-03 (siguiente: 000003-202301-03)');
        $this->command->info('  - 000003-202301-03 (siguiente: 000004-202301-03)');
        $this->command->info('Año 2022 (3 secuencias):');
        $this->command->info('  - 000001-202201-01 (siguiente: 000002-202201-01)');
        $this->command->info('  - 000002-202201-01 (siguiente: 000003-202201-01)');
        $this->command->info('  - 000003-202201-01 (siguiente: 000004-202201-01)');
        $this->command->info('');
        $this->command->info('Líneas de crédito configuradas:');
        $this->command->info('- Línea 03: Créditos estándar (2023-2025)');
        $this->command->info('- Línea 01: Créditos especiales (2022)');
        $this->command->info('');
        $this->command->info('Formato de radicado: XXXXXX-YYYYMM-LC');
        $this->command->info('Donde: XXXXXX = secuencia, YYYYMM = año-mes, LC = línea de crédito');
    }
}
