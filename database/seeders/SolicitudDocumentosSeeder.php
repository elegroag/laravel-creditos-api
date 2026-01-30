<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\SolicitudDocumento;
use App\Models\SolicitudCredito;

class SolicitudDocumentosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('solicitud_documentos')->delete();

        // Obtener solicitudes existentes
        $solicitudes = SolicitudCredito::all();

        $documentos = [];

        foreach ($solicitudes as $solicitud) {
            // Generar documentos según el tipo de solicitud
            $documentosSolicitud = $this->generarDocumentosParaSolicitud($solicitud);
            $documentos = array_merge($documentos, $documentosSolicitud);
        }

        foreach ($documentos as $documento) {
            SolicitudDocumento::create($documento);
        }

        $this->command->info('Documentos de solicitud creados exitosamente');
        $this->command->info('');
        $this->command->info('Resumen de documentos creados:');
        $this->command->info('Total documentos: ' . count($documentos));
        $this->command->info('Promedio por solicitud: ' . round(count($documentos) / $solicitudes->count(), 1));
        $this->command->info('');
        $this->command->info('Tipos de documentos generados:');
        $this->command->info('- Identificación (cédula/pasaporte)');
        $this->command->info('- Comprobantes de ingresos');
        $this->command->info('- Certificados laborales');
        $this->command->info('- Referencias personales');
        $this->command->info('- Documentos de empresa (RUT, estados financieros)');
        $this->command->info('- Documentos educativos (matrícula, historial)');
        $this->command->info('- Documentos de vivienda (escritura, avalúo)');
        $this->command->info('- Documentos de vehículo');
        $this->command->info('- Formatos y solicitudes');
    }

    /**
     * Generar documentos según el tipo de solicitud
     */
    private function generarDocumentosParaSolicitud(SolicitudCredito $solicitud): array
    {
        $documentos = [];
        $numeroSolicitud = $solicitud->numero_solicitud;
        $destino = strtolower($solicitud->destino_credito ?? '');

        // Documentos básicos para todas las solicitudes
        $documentosBasicos = [
            [
                'solicitud_id' => $solicitud->id,
                'documento_uuid' => $this->generarUUID(),
                'documento_requerido_id' => 'IDENTIFICACION',
                'nombre_original' => 'cedula_ciudadania.pdf',
                'saved_filename' => "doc_ident_{$numeroSolicitud}.pdf",
                'tipo_mime' => 'application/pdf',
                'tamano_bytes' => rand(500000, 2000000),
                'ruta_archivo' => "documentos/{$numeroSolicitud}/identificacion.pdf",
                'activo' => true
            ],
            [
                'solicitud_id' => $solicitud->id,
                'documento_uuid' => $this->generarUUID(),
                'documento_requerido_id' => 'COMPROBANTE_INGRESOS',
                'nombre_original' => 'comprobante_ingresos_3meses.pdf',
                'saved_filename' => "doc_ingresos_{$numeroSolicitud}.pdf",
                'tipo_mime' => 'application/pdf',
                'tamano_bytes' => rand(300000, 1500000),
                'ruta_archivo' => "documentos/{$numeroSolicitud}/ingresos.pdf",
                'activo' => true
            ]
        ];

        $documentos = array_merge($documentos, $documentosBasicos);

        // Documentos específicos según destino
        if (strpos($destino, 'vivienda') !== false) {
            $documentosVivienda = [
                [
                    'solicitud_id' => $solicitud->id,
                    'documento_uuid' => $this->generarUUID(),
                    'documento_requerido_id' => 'ESCRITURA_INMUEBLE',
                    'nombre_original' => 'escritura_publica.pdf',
                    'saved_filename' => "doc_escritura_{$numeroSolicitud}.pdf",
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(1000000, 5000000),
                    'ruta_archivo' => "documentos/{$numeroSolicitud}/escritura.pdf",
                    'activo' => true
                ],
                [
                    'solicitud_id' => $solicitud->id,
                    'documento_uuid' => $this->generarUUID(),
                    'documento_requerido_id' => 'AVALUO_INMUEBLE',
                    'nombre_original' => 'avaluo_comercial.pdf',
                    'saved_filename' => "doc_avaluo_{$numeroSolicitud}.pdf",
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(800000, 3000000),
                    'ruta_archivo' => "documentos/{$numeroSolicitud}/avaluo.pdf",
                    'activo' => true
                ]
            ];
            $documentos = array_merge($documentos, $documentosVivienda);
        }

        if (strpos($destino, 'educacion') !== false || strpos($destino, 'educativo') !== false) {
            $documentosEducacion = [
                [
                    'solicitud_id' => $solicitud->id,
                    'documento_uuid' => $this->generarUUID(),
                    'documento_requerido_id' => 'CERTIFICADO_MATRICULA',
                    'nombre_original' => 'matricula_universidad.pdf',
                    'saved_filename' => "doc_matricula_{$numeroSolicitud}.pdf",
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(400000, 1200000),
                    'ruta_archivo' => "documentos/{$numeroSolicitud}/matricula.pdf",
                    'activo' => true
                ],
                [
                    'solicitud_id' => $solicitud->id,
                    'documento_uuid' => $this->generarUUID(),
                    'documento_requerido_id' => 'HISTORIAL_ACADEMICO',
                    'nombre_original' => 'historial_academico.pdf',
                    'saved_filename' => "doc_historial_{$numeroSolicitud}.pdf",
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(600000, 2000000),
                    'ruta_archivo' => "documentos/{$numeroSolicitud}/historial.pdf",
                    'activo' => true
                ]
            ];
            $documentos = array_merge($documentos, $documentosEducacion);
        }

        if (strpos($destino, 'vehiculo') !== false) {
            $documentosVehiculo = [
                [
                    'solicitud_id' => $solicitud->id,
                    'documento_uuid' => $this->generarUUID(),
                    'documento_requerido_id' => 'FACTURA_VEHICULO',
                    'nombre_original' => 'factura_compra_vehiculo.pdf',
                    'saved_filename' => "doc_factura_vehiculo_{$numeroSolicitud}.pdf",
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(500000, 1800000),
                    'ruta_archivo' => "documentos/{$numeroSolicitud}/factura_vehiculo.pdf",
                    'activo' => true
                ],
                [
                    'solicitud_id' => $solicitud->id,
                    'documento_uuid' => $this->generarUUID(),
                    'documento_requerido_id' => 'SOAT_SEGURO',
                    'nombre_original' => 'soat_seguro.pdf',
                    'saved_filename' => "doc_soat_{$numeroSolicitud}.pdf",
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(300000, 900000),
                    'ruta_archivo' => "documentos/{$numeroSolicitud}/soat.pdf",
                    'activo' => true
                ]
            ];
            $documentos = array_merge($documentos, $documentosVehiculo);
        }

        if (strpos($destino, 'capital') !== false || strpos($destino, 'empresa') !== false) {
            $documentosEmpresa = [
                [
                    'solicitud_id' => $solicitud->id,
                    'documento_uuid' => $this->generarUUID(),
                    'documento_requerido_id' => 'RUT_EMPRESA',
                    'nombre_original' => 'rut_actualizado.pdf',
                    'saved_filename' => "doc_rut_{$numeroSolicitud}.pdf",
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(400000, 1200000),
                    'ruta_archivo' => "documentos/{$numeroSolicitud}/rut.pdf",
                    'activo' => true
                ],
                [
                    'solicitud_id' => $solicitud->id,
                    'documento_uuid' => $this->generarUUID(),
                    'documento_requerido_id' => 'ESTADOS_FINANCIEROS',
                    'nombre_original' => 'estados_financieros_2anos.pdf',
                    'saved_filename' => "doc_estados_{$numeroSolicitud}.pdf",
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(1500000, 4000000),
                    'ruta_archivo' => "documentos/{$numeroSolicitud}/estados.pdf",
                    'activo' => true
                ]
            ];
            $documentos = array_merge($documentos, $documentosEmpresa);
        }

        // Agregar referencias para todas las solicitudes
        $documentosReferencias = [
            [
                'solicitud_id' => $solicitud->id,
                'documento_uuid' => $this->generarUUID(),
                'documento_requerido_id' => 'REFERENCIAS_PERSONALES',
                'nombre_original' => 'formato_referencias.pdf',
                'saved_filename' => "doc_referencias_{$numeroSolicitud}.pdf",
                'tipo_mime' => 'application/pdf',
                'tamano_bytes' => rand(200000, 800000),
                'ruta_archivo' => "documentos/{$numeroSolicitud}/referencias.pdf",
                'activo' => true
            ]
        ];

        $documentos = array_merge($documentos, $documentosReferencias);

        return $documentos;
    }

    /**
     * Generar UUID único
     */
    private function generarUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
