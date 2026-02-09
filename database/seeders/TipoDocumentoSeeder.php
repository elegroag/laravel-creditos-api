<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TipoDocumento;

class TipoDocumentoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            ['tipo' => 'pdf', 'detalle' => 'Documento PDF', 'orden' => 1],
            ['tipo' => 'identificacion', 'detalle' => 'Identificación Personal', 'orden' => 2],
            ['tipo' => 'contrato', 'detalle' => 'Contrato Laboral', 'orden' => 3],
            ['tipo' => 'nomina', 'detalle' => 'Nómina', 'orden' => 4],
            ['tipo' => 'estudio_cotizacion', 'detalle' => 'Estudio de Cotización', 'orden' => 5],
            ['tipo' => 'certificado_ingresos', 'detalle' => 'Certificado de Ingresos', 'orden' => 6],
            ['tipo' => 'desprendible', 'detalle' => 'Desprendible de Pago', 'orden' => 7],
            ['tipo' => 'carta_laboral', 'detalle' => 'Carta Laboral', 'orden' => 8],
            ['tipo' => 'rut', 'detalle' => 'RUT', 'orden' => 9],
            ['tipo' => 'camara_comercio', 'detalle' => 'Cámara de Comercio', 'orden' => 10],
            ['tipo' => 'estado_financiero', 'detalle' => 'Estado Financiero', 'orden' => 11],
            ['tipo' => 'impuesto_predial', 'detalle' => 'Impuesto Predial', 'orden' => 12],
            ['tipo' => 'factura', 'detalle' => 'Factura', 'orden' => 13],
            ['tipo' => 'selfie', 'detalle' => 'Selfie o Foto', 'orden' => 14],
            ['tipo' => 'firma', 'detalle' => 'Firma Digital', 'orden' => 15],
            ['tipo' => 'otro', 'detalle' => 'Otro Documento', 'orden' => 99],
        ];

        foreach ($tipos as $tipo) {
            TipoDocumento::updateOrCreate(
                ['tipo' => $tipo['tipo']],
                [
                    'detalle' => $tipo['detalle'],
                    'orden' => $tipo['orden'],
                    'activo' => true
                ]
            );
        }

        $this->command->info('Tipos de documento creados exitosamente');
    }
}
