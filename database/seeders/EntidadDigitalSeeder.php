<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\EntidadDigital;
use App\Models\User;

class EntidadDigitalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('entidad_digital')->delete();

        // Obtener usuarios existentes
        $users = User::all();

        foreach ($users as $user) {
            // Generar entidad digital para cada usuario según su rol
            $entidad = $this->generarEntidadParaUsuario($user);
            EntidadDigital::create($entidad);
        }

        $this->command->info('Entidades digitales creadas exitosamente');
        $this->command->info('');
        $this->command->info('Resumen de entidades creadas:');
        $this->command->info('Total entidades: ' . EntidadDigital::count());
        $this->command->info('Entidades activas: ' . EntidadDigital::where('estado', 'activa')->count());
        $this->command->info('Entidades inactivas: ' . EntidadDigital::where('estado', 'inactiva')->count());
        $this->command->info('Entidades bloqueadas: ' . EntidadDigital::where('estado', 'bloqueada')->count());
        $this->command->info('');
        $this->command->info('Documentos por entidad:');
        $this->command->info('Promedio: 2-3 documentos por entidad');
        $this->command->info('Tipos: Cédula, selfie, firma, documentos adicionales');
        $this->command->info('');
        $this->command->info('Estados de validación:');
        $this->command->info('Verificación de identidad completada para todas las entidades');
        $this->command->info('Validaciones biométricas y documentales procesadas');
    }

    /**
     * Generar entidad digital según el tipo de usuario
     */
    private function generarEntidadParaUsuario(User $user): array
    {
        $username = $user->username;
        $roles = $user->roles ?? [];

        // Datos base de identificación
        $baseData = [
            'username' => $username,
            'tipo_identificacion' => 'cedula',
            'numero_identificacion' => $this->generarNumeroDocumento(),
            'selfie' => "selfies/{$username}_selfie.jpg",
            'clave_firma_hash' => $this->generarHashFirma(),
            'estado' => 'activa',
            'metadata' => [
                'dispositivo_registro' => 'Web',
                'direccion_ip' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'fecha_registro' => now()->toISOString(),
                'metodo_autenticacion' => 'email_password'
            ],
            'validaciones' => [
                [
                    'tipo' => 'identificacion',
                    'resultado' => true,
                    'mensaje' => 'Identificación verificada exitosamente',
                    'detalles' => [
                        'documento_validado' => true,
                        'numero_coincide' => true,
                        'fecha_valida' => true
                    ],
                    'fecha' => now()->toISOString()
                ],
                [
                    'tipo' => 'biometrica',
                    'resultado' => true,
                    'mensaje' => 'Validación biométrica completada',
                    'detalles' => [
                        'selfie_verificada' => true,
                        'coincidencia_facial' => 0.95,
                        'metodo_verificacion' => 'facial_recognition'
                    ],
                    'fecha' => now()->toISOString()
                ]
            ]
        ];

        // Ajustar según rol del usuario
        if (in_array('administrator', $roles)) {
            $baseData['documentos'] = $this->generarDocumentosAdministrador($username);
            $baseData['metadata']['rol_usuario'] = 'administrator';
            $baseData['metadata']['nivel_acceso'] = 'completo';
        } elseif (in_array('adviser', $roles)) {
            $baseData['documentos'] = $this->generarDocumentosAsesor($username);
            $baseData['metadata']['rol_usuario'] = 'adviser';
            $baseData['metadata']['nivel_acceso'] = 'medio';
        } elseif (in_array('user_empresa', $roles)) {
            $baseData['documentos'] = $this->generarDocumentosEmpresa($username);
            $baseData['metadata']['rol_usuario'] = 'user_empresa';
            $baseData['metadata']['nivel_acceso'] = 'basico';
        } elseif (in_array('user_trabajador', $roles)) {
            $baseData['documentos'] = $this->generarDocumentosTrabajador($username);
            $baseData['metadata']['rol_usuario'] = 'user_trabajador';
            $baseData['metadata']['nivel_acceso'] = 'basico';
        } else {
            $baseData['documentos'] = $this->generarDocumentosBasico($username);
            $baseData['metadata']['rol_usuario'] = 'usuario';
            $baseData['metadata']['nivel_acceso'] = 'basico';
        }

        return $baseData;
    }

    /**
     * Generar documentos para administrador
     */
    private function generarDocumentosAdministrador(string $username): array
    {
        return [
            'cedula_ciudadania' => [
                'ruta' => "documentos/{$username}/cedula_frente.jpg",
                'lado' => 'frente',
                'ruta_reverso' => "documentos/{$username}/cedula_reverso.jpg",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ],
            'pasaporte' => [
                'ruta' => "documentos/{$username}/pasaporte_frente.jpg",
                'lado' => 'frente',
                'ruta_reverso' => "documentos/{$username}/pasaporte_reverso.jpg",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ],
            'firma_digital' => [
                'ruta' => "documentos/{$username}/firma_digital.png",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ],
            'certificado_estudio' => [
                'ruta' => "documentos/{$username}/certificado_estudio.pdf",
                'fecha_subida' => now()->subYears(rand(5, 15))->toISOString(),
                'activo' => true
            ]
        ];
    }

    /**
     * Generar documentos para asesor
     */
    private function generarDocumentosAsesor(string $username): array
    {
        return [
            'cedula_ciudadania' => [
                'ruta' => "documentos/{$username}/cedula_frente.jpg",
                'lado' => 'frente',
                'ruta_reverso' => "documentos/{$username}/cedula_reverso.jpg",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ],
            'firma_digital' => [
                'ruta' => "documentos/{$username}/firma_digital.png",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ],
            'certificado_laboral' => [
                'ruta' => "documentos/{$username}/certificado_laboral.pdf",
                'fecha_subida' => now()->subYears(rand(1, 5))->toISOString(),
                'activo' => true
            ],
            'carta_trabajo' => [
                'ruta' => "documentos/{$username}/carta_trabajo.pdf",
                'fecha_subida' => now()->subMonths(rand(1, 12))->toISOString(),
                'activo' => true
            ]
        ];
    }

    /**
     * Generar documentos para empresa
     */
    private function generarDocumentosEmpresa(string $username): array
    {
        return [
            'cedula_representante' => [
                'ruta' => "documentos/{$username}/cedula_representante.jpg",
                'lado' => 'frente',
                'ruta_reverso' => "documentos/{$username}/cedula_representante_reverso.jpg",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ],
            'rut_empresa' => [
                'ruta' => "documentos/{$username}/rut_empresa.pdf",
                'fecha_subida' => now()->subMonths(rand(1, 3))->toISOString(),
                'activo' => true
            ],
            'camara_comercio' => [
                'ruta' => "documentos/{$username}/camara_comercio.jpg",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ],
            'firma_digital' => [
                'ruta' => "documentos/{$username}/firma_digital.png",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ]
        ];
    }

    /**
     * Generar documentos para trabajador
     */
    private function generarDocumentosTrabajador(string $username): array
    {
        return [
            'cedula_ciudadania' => [
                'ruta' => "documentos/{$username}/cedula_frente.jpg",
                'lado' => 'frente',
                'ruta_reverso' => "documentos/{$username}/cedula_reverso.jpg",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ],
            'contrato_trabajo' => [
                'ruta' => "documentos/{$username}/contrato_trabajo.pdf",
                'fecha_subida' => now()->subMonths(rand(1, 6))->toISOString(),
                'activo' => true
            ],
            'certificado_afiliacion' => [
                'ruta' => "documentos/{$username}/certificado_afiliacion.pdf",
                'fecha_subida' => now()->subMonths(rand(1, 12))->toISOString(),
                'activo' => true
            ],
            'firma_digital' => [
                'ruta' => "documentos/{$username}/firma_digital.png",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ]
        ];
    }

    /**
     * Generar documentos básicos
     */
    private function generarDocumentosBasico(string $username): array
    {
        return [
            'cedula_ciudadania' => [
                'ruta' => "documentos/{$username}/cedula_frente.jpg",
                'lado' => 'frente',
                'ruta_reverso' => "documentos/{$username}/cedula_reverso.jpg",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ],
            'firma_digital' => [
                'ruta' => "documentos/{$username}/firma_digital.png",
                'fecha_subida' => now()->toISOString(),
                'activo' => true
            ]
        ];
    }

    /**
     * Generar número de documento
     */
    private function generarNumeroDocumento(): string
    {
        return (string) rand(10000000, 99999999);
    }

    /**
     * Generar hash de firma digital
     */
    private function generarHashFirma(): string
    {
        return hash('sha256', uniqid() . time() . microtime(true));
    }
}
