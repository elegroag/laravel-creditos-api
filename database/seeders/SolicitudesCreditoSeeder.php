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

        $trabajadorUser = User::where('username', 'trabajador1')->first();
        $estadoFinalizado = EstadoSolicitud::where('id', 'APROBADA')->first();
        $solicitudes = [
            [
                'numero_solicitud' => '000001-202501-03',
                'owner_username' => $trabajadorUser->username,
                'valor_solicitud' => 15000000.00,
                'plazo_meses' => 36,
                'tasa_interes' => 11.50,
                'estado' => $estadoFinalizado->id,
                'fecha_radicado' => '2025-01-15',
                'producto_tipo' => '01',
                'ha_tenido_credito' => true,
                'detalle_modalidad' => 'Crédito personal',
                'tipo_credito' => '01',
                'moneda' => 'COP',
                'cuota_mensual' => 495000.00,
                'rol_en_solicitud' => 'T',
            ]
        ];

        foreach ($solicitudes as $solicitud) {
            SolicitudCredito::create($solicitud);
        }
    }
}
