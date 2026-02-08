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
                'xml_filename' => 'solicitud_000001_202501.xml',
                'monto_solicitado' => 15000000.00,
                'monto_aprobado' => 12000000.00,
                'plazo_meses' => 36,
                'tasa_interes' => 11.50,
                'destino_credito' => 'Mejora de vivienda',
                'descripcion' => 'Crédito solicitado para reparaciones y mejoras en la vivienda principal',
                'estado' => $estadoFinalizado->id
            ]
        ];

        foreach ($solicitudes as $solicitud) {
            SolicitudCredito::create($solicitud);
        }
    }
}
