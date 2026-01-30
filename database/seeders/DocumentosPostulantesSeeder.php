<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\DocumentoPostulante;
use App\Models\User;

class DocumentosPostulantesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('documentos_postulantes')->delete();

        // Obtener usuarios existentes
        $users = User::all();

        foreach ($users as $user) {
            // Generar documentos según el rol del usuario
            $documentos = $this->generarDocumentosParaUsuario($user);

            foreach ($documentos as $documento) {
                DocumentoPostulante::create($documento);
            }
        }

        $this->command->info('Documentos de postulantes creados exitosamente');
        $this->command->info('');
        $this->command->info('Resumen de documentos creados:');
        $this->command->info('Total documentos: ' . DocumentoPostulante::count());
        $this->command->info('Documentos activos: ' . DocumentoPostulante::where('activo', true)->count());
        $this->command->info('Promedio por usuario: ' . round(DocumentoPostulante::count() / $users->count(), 1));
        $this->command->info('');
        $this->command->info('Tipos de documentos generados:');
        $this->command->info('- Identificación: Cédula, pasaporte, documento extranjero');
        $this->command->info('- Laborales: Contrato, certificado laboral, carta trabajo');
        $this->command->info('- Financieros: Extractos bancarios, comprobantes ingresos');
        $this->command->info('- Personales: Referencias, declaraciones juramentadas');
        $this->command->info('- Empresariales: RUT, cámara comercio, estados financieros');
    }

    /**
     * Generar documentos según el rol del usuario
     */
    private function generarDocumentosParaUsuario(User $user): array
    {
        $username = $user->username;
        $roles = json_decode($user->roles ?? '[]', true);

        $documentos = [];

        // Documentos base para todos los usuarios
        $documentosBase = [
            [
                'username' => $username,
                'tipo_documento' => 'cedula_ciudadania',
                'nombre_original' => 'cedula_ciudadania_' . $username . '.jpg',
                'saved_filename' => 'cedula_' . $username . '.jpg',
                'tipo_mime' => 'image/jpeg',
                'tamano_bytes' => rand(500000, 2000000),
                'ruta_archivo' => "documentos/postulantes/{$username}/cedula.jpg",
                'activo' => true
            ]
        ];

        if (in_array('administrator', $roles)) {
            $documentosAdmin = [
                [
                    'username' => $username,
                    'tipo_documento' => 'pasaporte',
                    'nombre_original' => 'pasaporte_' . $username . '.jpg',
                    'saved_filename' => 'pasaporte_' . $username . '.jpg',
                    'tipo_mime' => 'image/jpeg',
                    'tamano_bytes' => rand(400000, 1500000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/pasaporte.jpg",
                    'activo' => true
                ],
                [
                    'username' => $username,
                    'tipo_documento' => 'certificado_estudio',
                    'nombre_original' => 'certificado_estudio_' . $username . '.pdf',
                    'saved_filename' => 'certificado_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(800000, 3000000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/certificado.pdf",
                    'activo' => true
                ],
                [
                    'username' => $username,
                    'tipo_documento' => 'declaracion_renta',
                    'nombre_original' => 'declaracion_renta_' . $username . '.pdf',
                    'saved_filename' => 'declaracion_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(1000000, 5000000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/declaracion.pdf",
                    'activo' => true
                ]
            ];
            $documentos = array_merge($documentos, $documentosBase, $documentosAdmin);
        } elseif (in_array('adviser', $roles)) {
            $documentosAsesor = [
                [
                    'username' => $username,
                    'tipo_documento' => 'certificado_laboral',
                    'nombre_original' => 'certificado_laboral_' . $username . '.pdf',
                    'saved_filename' => 'cert_laboral_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(600000, 2500000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/cert_laboral.pdf",
                    'activo' => true
                ],
                [
                    'username' => $username,
                    'tipo_documento' => 'carta_trabajo',
                    'nombre_original' => 'carta_trabajo_' . $username . '.pdf',
                    'saved_filename' => 'carta_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(400000, 1200000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/carta.pdf",
                    'activo' => true
                ],
                [
                    'username' => $username,
                    'tipo_documento' => 'referencias_personales',
                    'nombre_original' => 'referencias_' . $username . '.pdf',
                    'saved_filename' => 'referencias_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(300000, 800000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/referencias.pdf",
                    'activo' => true
                ]
            ];
            $documentos = array_merge($documentos, $documentosBase, $documentosAsesor);
        } elseif (in_array('user_empresa', $roles)) {
            $documentosEmpresa = [
                [
                    'username' => $username,
                    'tipo_documento' => 'rut_empresa',
                    'nombre_original' => 'rut_empresa_' . $username . '.pdf',
                    'saved_filename' => 'rut_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(700000, 2800000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/rut.pdf",
                    'activo' => true
                ],
                [
                    'username' => $username,
                    'tipo_documento' => 'camara_comercio',
                    'nombre_original' => 'camara_comercio_' . $username . '.jpg',
                    'saved_filename' => 'camara_' . $username . '.jpg',
                    'tipo_mime' => 'image/jpeg',
                    'tamano_bytes' => rand(800000, 3500000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/camara.jpg",
                    'activo' => true
                ],
                [
                    'username' => $username,
                    'tipo_documento' => 'estados_financieros',
                    'nombre_original' => 'estados_financieros_' . $username . '.pdf',
                    'saved_filename' => 'estados_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(1500000, 6000000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/estados.pdf",
                    'activo' => true
                ],
                [
                    'username' => $username,
                    'tipo_documento' => 'representante_legal',
                    'nombre_original' => 'representante_legal_' . $username . '.pdf',
                    'saved_filename' => 'rep_legal_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(500000, 2000000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/rep_legal.pdf",
                    'activo' => true
                ]
            ];
            $documentos = array_merge($documentos, $documentosBase, $documentosEmpresa);
        } elseif (in_array('user_trabajador', $roles)) {
            $documentosTrabajador = [
                [
                    'username' => $username,
                    'tipo_documento' => 'contrato_trabajo',
                    'nombre_original' => 'contrato_trabajo_' . $username . '.pdf',
                    'saved_filename' => 'contrato_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(600000, 2400000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/contrato.pdf",
                    'activo' => true
                ],
                [
                    'username' => $username,
                    'tipo_documento' => 'certificado_afiliacion',
                    'nombre_original' => 'certificado_afiliacion_' . $username . '.pdf',
                    'saved_filename' => 'cert_afiliacion_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(400000, 1800000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/cert_afiliacion.pdf",
                    'activo' => true
                ],
                [
                    'username' => $username,
                    'tipo_documento' => 'extractos_bancarios',
                    'nombre_original' => 'extractos_bancarios_' . $username . '.pdf',
                    'saved_filename' => 'extractos_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(800000, 3200000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/extractos.pdf",
                    'activo' => true
                ],
                [
                    'username' => $username,
                    'tipo_documento' => 'comprobante_ingresos',
                    'nombre_original' => 'comprobante_ingresos_' . $username . '.pdf',
                    'saved_filename' => 'comprobante_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(500000, 1500000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/comprobante.pdf",
                    'activo' => true
                ]
            ];
            $documentos = array_merge($documentos, $documentosBase, $documentosTrabajador);
        } else {
            // Documentos básicos para usuarios sin rol específico
            $documentosBasicos = [
                [
                    'username' => $username,
                    'tipo_documento' => 'referencia_personal',
                    'nombre_original' => 'referencia_personal_' . $username . '.pdf',
                    'saved_filename' => 'ref_personal_' . $username . '.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => rand(300000, 900000),
                    'ruta_archivo' => "documentos/postulantes/{$username}/ref_personal.pdf",
                    'activo' => true
                ]
            ];
            $documentos = array_merge($documentos, $documentosBase, $documentosBasicos);
        }

        return $documentos;
    }
}
