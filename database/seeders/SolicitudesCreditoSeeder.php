<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\SolicitudCredito;
use App\Models\User;
use App\Models\EstadoSolicitud;
use App\Models\NumeroSolicitud;

class SolicitudesCreditoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('solicitudes_credito')->delete();

        // Obtener usuarios y estados
        $adminUser = User::where('username', 'admin')->first();
        $asesorUser = User::where('username', 'asesor1')->first();
        $empresaUser = User::where('username', 'empresa1')->first();
        $trabajadorUser = User::where('username', 'trabajador1')->first();

        $estadoPostulado = EstadoSolicitud::where('codigo', 'POSTULADO')->first();
        $estadoAprobado = EstadoSolicitud::where('codigo', 'APROBADO')->first();
        $estadoRevision = EstadoSolicitud::where('codigo', 'EN_REVISION')->first();
        $estadoFinalizado = EstadoSolicitud::where('codigo', 'FINALIZADO')->first();

        $solicitudes = [
            [
                'numero_solicitud' => '000001-202501-03',
                'owner_username' => $trabajadorUser->username,
                'xml_filename' => 'solicitud_000001_202501.xml',
                'monto_solicitado' => 15000000.00,
                'monto_aprobado' => 12000000.00,
                'plazo_meses' => 36,
                'tasa_interes' => 11.50,
                'destino_credito' => 'Mejora de vivienda',
                'descripcion' => 'Crédito solicitado para reparaciones y mejoras en la vivienda principal',
                'estado' => $estadoFinalizado->codigo
            ],
            [
                'numero_solicitud' => '000002-202501-03',
                'owner_username' => $empresaUser->username,
                'xml_filename' => 'solicitud_000002_202501.xml',
                'monto_solicitado' => 50000000.00,
                'monto_aprobado' => 0.00,
                'plazo_meses' => 48,
                'tasa_interes' => 10.00,
                'destino_credito' => 'Capital de trabajo',
                'descripcion' => 'Financiamiento para expansión de operaciones y capital de trabajo',
                'estado' => $estadoRevision->codigo
            ],
            [
                'numero_solicitud' => '000003-202501-03',
                'owner_username' => $trabajadorUser->username,
                'xml_filename' => 'solicitud_000003_202501.xml',
                'monto_solicitado' => 8000000.00,
                'monto_aprobado' => 0.00,
                'plazo_meses' => 24,
                'tasa_interes' => 12.00,
                'destino_credito' => 'Educación superior',
                'descripcion' => 'Crédito para pago de matrícula y semestres universitarios',
                'estado' => $estadoPostulado->codigo
            ],
            [
                'numero_solicitud' => '000001-202401-03',
                'owner_username' => $adminUser->username,
                'xml_filename' => 'solicitud_000001_202401.xml',
                'monto_solicitado' => 25000000.00,
                'monto_aprobado' => 25000000.00,
                'plazo_meses' => 60,
                'tasa_interes' => 9.50,
                'destino_credito' => 'Vivienda nueva',
                'descripcion' => 'Financiamiento para compra de vivienda nueva',
                'estado' => $estadoFinalizado->codigo
            ],
            [
                'numero_solicitud' => '000002-202401-03',
                'owner_username' => $asesorUser->username,
                'xml_filename' => 'solicitud_000002_202401.xml',
                'monto_solicitado' => 35000000.00,
                'monto_aprobado' => 30000000.00,
                'plazo_meses' => 36,
                'tasa_interes' => 10.50,
                'destino_credito' => 'Vehículo',
                'descripcion' => 'Crédito para compra de vehículo particular',
                'estado' => $estadoAprobado->codigo
            ],
            [
                'numero_solicitud' => '000003-202401-03',
                'owner_username' => $empresaUser->username,
                'xml_filename' => 'solicitud_000003_202401.xml',
                'monto_solicitado' => 100000000.00,
                'monto_aprobado' => 0.00,
                'plazo_meses' => 60,
                'tasa_interes' => 8.00,
                'destino_credito' => 'Maquinaria y equipo',
                'descripcion' => 'Inversión en nueva maquinaria para la planta de producción',
                'estado' => $estadoRevision->codigo
            ],
            [
                'numero_solicitud' => '000001-202301-03',
                'owner_username' => $trabajadorUser->username,
                'xml_filename' => 'solicitud_000001_202301.xml',
                'monto_solicitado' => 5000000.00,
                'monto_aprobado' => 5000000.00,
                'plazo_meses' => 18,
                'tasa_interes' => 13.00,
                'destino_credito' => 'Emergencia médica',
                'descripcion' => 'Crédito para gastos médicos urgentes',
                'estado' => $estadoFinalizado->codigo
            ],
            [
                'numero_solicitud' => '000002-202301-03',
                'owner_username' => $asesorUser->username,
                'xml_filename' => 'solicitud_000002_202301.xml',
                'monto_solicitado' => 12000000.00,
                'monto_aprobado' => 0.00,
                'plazo_meses' => 30,
                'tasa_interes' => 11.00,
                'destino_credito' => 'Consolidación de deudas',
                'descripcion' => 'Crédito para consolidar varias deudas existentes',
                'estado' => $estadoPostulado->codigo
            ],
            [
                'numero_solicitud' => '000003-202301-03',
                'owner_username' => $adminUser->username,
                'xml_filename' => 'solicitud_000003_202301.xml',
                'monto_solicitado' => 75000000.00,
                'monto_aprobado' => 60000000.00,
                'plazo_meses' => 84,
                'tasa_interes' => 7.50,
                'destino_credito' => 'Proyecto educativo',
                'descripcion' => 'Financiamiento para estudios de posgrado en el exterior',
                'estado' => $estadoAprobado->codigo
            ],
            [
                'numero_solicitud' => '000001-202201-01',
                'owner_username' => $empresaUser->username,
                'xml_filename' => 'solicitud_000001_202201.xml',
                'monto_solicitado' => 200000000.00,
                'monto_aprobado' => 150000000.00,
                'plazo_meses' => 120,
                'tasa_interes' => 6.50,
                'destino_credito' => 'Expansión internacional',
                'descripcion' => 'Crédito para expansión de operaciones a mercados internacionales',
                'estado' => $estadoFinalizado->codigo
            ]
        ];

        foreach ($solicitudes as $solicitud) {
            SolicitudCredito::create($solicitud);
        }

        $this->command->info('Solicitudes de crédito creadas exitosamente');
        $this->command->info('');
        $this->command->info('Resumen de solicitudes creadas:');
        $this->command->info('Año 2025 (3 solicitudes):');
        $this->command->info('  - 000001-202501-03: $12M aprobado - FINALIZADO');
        $this->command->info('  - 000002-202501-03: $50M solicitado - EN_REVISION');
        $this->command->info('  - 000003-202501-03: $8M solicitado - POSTULADO');
        $this->command->info('Año 2024 (3 solicitudes):');
        $this->command->info('  - 000001-202401-03: $25M aprobado - FINALIZADO');
        $this->command->info('  - 000002-202401-03: $30M aprobado - APROBADO');
        $this->command->info('  - 000003-202401-03: $100M solicitado - EN_REVISION');
        $this->command->info('Año 2023 (3 solicitudes):');
        $this->command->info('  - 000001-202301-03: $5M aprobado - FINALIZADO');
        $this->command->info('  - 000002-202301-03: $12M solicitado - POSTULADO');
        $this->command->info('  - 000003-202301-03: $60M aprobado - APROBADO');
        $this->command->info('Año 2022 (1 solicitud):');
        $this->command->info('  - 000001-202201-01: $150M aprobado - FINALIZADO');
        $this->command->info('');
        $this->command->info('Total solicitado: $640M');
        $this->command->info('Total aprobado: $277M');
        $this->command->info('Tasa de aprobación: 43.3%');
    }
}
