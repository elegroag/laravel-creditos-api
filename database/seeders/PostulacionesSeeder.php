<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\EmpresaConvenio;

class PostulacionesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('postulaciones')->delete();

        // Obtener usuarios y empresas
        $users = User::all();
        $empresas = EmpresaConvenio::all();

        foreach ($users as $user) {
            // Generar postulación según el rol del usuario
            $postulacion = $this->generarPostulacionParaUsuario($user, $empresas);
            Postulacion::create($postulacion);
        }

        $this->command->info('Postulaciones creadas exitosamente');
        $this->command->info('');
        $this->command->info('Resumen de postulaciones creadas:');
        $this->command->info('Total postulaciones: ' . Postulacion::count());
        $this->command->info('Trabajadores: ' . Postulacion::where('tipo_postulante', 'trabajador')->count());
        $this->command->info('Empresas: ' . Postulacion::where('tipo_postulante', 'empresa')->count());
        $this->command->info('Iniciadas: ' . Postulacion::where('estado', 'iniciada')->count());
        $this->command->info('Completas: ' . Postulacion::where('estado', 'completa')->count());
        $this->command->info('Aprobadas: ' . Postulacion::where('estado', 'aprobada')->count());
        $this->command->info('Rechazadas: ' . Postulacion::where('estado', 'rechazada')->count());
    }

    /**
     * Generar postulación según el rol del usuario
     */
    private function generarPostulacionParaUsuario(User $user, $empresas): array
    {
        $username = $user->username;
        $roles = json_decode($user->roles ?? '[]', true);

        // Determinar tipo de postulante según rol
        if (in_array('user_empresa', $roles)) {
            return $this->generarPostulacionEmpresa($username, $empresas);
        } elseif (in_array('user_trabajador', $roles)) {
            return $this->generarPostulacionTrabajador($username);
        } elseif (in_array('administrator', $roles)) {
            return $this->generarPostulacionTrabajador($username); // Admin como trabajador
        } elseif (in_array('adviser', $roles)) {
            return $this->generarPostulacionTrabajador($username); // Asesor como trabajador
        } else {
            return $this->generarPostulacionTrabajador($username); // Por defecto trabajador
        }
    }

    /**
     * Generar postulación de empresa
     */
    private function generarPostulacionEmpresa(string $username, $empresas): array
    {
        $empresa = $empresas->random();

        return [
            'username' => $username,
            'tipo_postulante' => 'empresa',
            'empresa_nit' => $empresa->nit,
            'empresa_razon_social' => $empresa->razon_social,
            'datos_personales' => [
                'tipo_documento' => 'nit',
                'numero_documento' => $empresa->nit,
                'razon_social' => $empresa->razon_social,
                'nombre_representante' => $this->generarNombreCompleto(),
                'tipo_documento_representante' => 'cedula',
                'numero_documento_representante' => $this->generarNumeroDocumento(),
                'email_representante' => $this->generarEmail($username),
                'telefono_representante' => $this->generarTelefono(),
                'direccion_empresa' => $this->generarDireccion(),
                'ciudad_empresa' => $empresa->ciudad,
                'departamento_empresa' => $this->generarDepartamento(),
                'sector_economico' => $empresa->sector_economico ?? 'Comercio',
                'numero_empleados' => $empresa->numero_empleados ?? rand(10, 500),
                'fecha_constitucion' => now()->subYears(rand(5, 20))->format('Y-m-d')
            ],
            'datos_laborales' => [
                'actividad_principal' => $this->generarActividadEconomica(),
                'descripcion_negocio' => $this->generarDescripcionNegocio(),
                'antiguedad_empresa' => rand(2, 15) . ' años',
                'experiencia_sector' => rand(3, 10) . ' años',
                'referencias_comerciales' => [
                    [
                        'nombre_empresa' => $this->generarNombreEmpresa(),
                        'contacto' => $this->generarNombreCompleto(),
                        'telefono' => $this->generarTelefono(),
                        'tipo_relacion' => 'Proveedor'
                    ],
                    [
                        'nombre_empresa' => $this->generarNombreEmpresa(),
                        'contacto' => $this->generarNombreCompleto(),
                        'telefono' => $this->generarTelefono(),
                        'tipo_relacion' => 'Cliente'
                    ]
                ]
            ],
            'datos_financieros' => [
                'ingresos_mensuales' => rand(5000000, 50000000),
                'gastos_operativos_mensuales' => rand(2000000, 20000000),
                'utilidad_mensual' => rand(1000000, 15000000),
                'activos_totales' => rand(50000000, 500000000),
                'pasivos_totales' => rand(10000000, 200000000),
                'patrimonio_neto' => rand(40000000, 300000000),
                'cuentas_bancarias' => [
                    [
                        'banco' => 'Banco ABC',
                        'tipo_cuenta' => 'Ahorros',
                        'numero_cuenta' => $this->generarNumeroCuenta(),
                        'saldo_promedio' => rand(10000000, 100000000)
                    ],
                    [
                        'banco' => 'Banco XYZ',
                        'tipo_cuenta' => 'Corriente',
                        'numero_cuenta' => $this->generarNumeroCuenta(),
                        'saldo_promedio' => rand(5000000, 50000000)
                    ]
                ],
                'deudas_actuales' => [
                    [
                        'entidad' => 'Banco Financiero',
                        'tipo_deuda' => 'Crédito comercial',
                        'saldo' => rand(5000000, 50000000),
                        'cuota_mensual' => rand(500000, 5000000)
                    ]
                ]
            ],
            'estado' => $this->determinarEstadoAleatorio(),
            'observaciones' => $this->generarObservaciones('empresa')
        ];
    }

    /**
     * Generar postulación de trabajador
     */
    private function generarPostulacionTrabajador(string $username): array
    {
        return [
            'username' => $username,
            'tipo_postulante' => 'trabajador',
            'empresa_nit' => null,
            'empresa_razon_social' => null,
            'datos_personales' => [
                'tipo_documento' => 'cedula',
                'numero_documento' => $this->generarNumeroDocumento(),
                'nombres' => $this->generarNombres(),
                'apellidos' => $this->generarApellidos(),
                'fecha_nacimiento' => $this->generarFechaNacimiento(),
                'sexo' => $this->generarSexo(),
                'estado_civil' => $this->generarEstadoCivil(),
                'direccion' => $this->generarDireccion(),
                'ciudad' => $this->generarCiudad(),
                'departamento' => $this->generarDepartamento(),
                'telefono' => $this->generarTelefono(),
                'email' => $this->generarEmail($username),
                'nivel_educativo' => $this->generarNivelEducativo(),
                'personas_a_cargo' => rand(0, 4),
                'tipo_vivienda' => $this->generarTipoVivienda(),
                'vive_con_nucleo_familiar' => $this->generarBoolean()
            ],
            'datos_laborales' => [
                'empresa_actual' => $this->generarNombreEmpresa(),
                'cargo_actual' => $this->generarCargo(),
                'salario_mensual' => rand(1500000, 8000000),
                'fecha_ingreso' => $this->generarFechaIngreso(),
                'tipo_contrato' => $this->generarTipoContrato(),
                'telefono_empresa' => $this->generarTelefonoEmpresa(),
                'direccion_empresa' => $this->generarDireccion(),
                'ciudad_empresa' => $this->generarCiudad(),
                'sector_economico' => $this->generarSectorEconomico(),
                'experiencia_laboral' => rand(2, 20) . ' años',
                'antiguedad_empleo_actual' => rand(6, 60) . ' meses',
                'referencias_laborales' => [
                    [
                        'nombre' => $this->generarNombreCompleto(),
                        'cargo' => $this->generarCargo(),
                        'empresa' => $this->generarNombreEmpresa(),
                        'telefono' => $this->generarTelefono(),
                        'email' => $this->generarEmail($username),
                        'tiempo_conocido' => rand(1, 10) . ' años'
                    ],
                    [
                        'nombre' => $this->generarNombreCompleto(),
                        'cargo' => $this->generarCargo(),
                        'empresa' => $this->generarNombreEmpresa(),
                        'telefono' => $this->generarTelefono(),
                        'email' => $this->generarEmail($username),
                        'tiempo_conocido' => rand(1, 8) . ' años'
                    ]
                ]
            ],
            'datos_financieros' => [
                'ingreso_principal' => rand(1500000, 8000000),
                'ingresos_adicionales' => rand(0, 2000000),
                'ingreso_conyuge' => rand(0, 3000000),
                'otros_ingresos' => rand(0, 1000000),
                'total_ingresos' => 0, // Se calcula automáticamente
                'gastos_fijos' => rand(500000, 3000000),
                'gastos_variables' => rand(200000, 1500000),
                'total_gastos' => 0, // Se calcula automáticamente
                'ingreso_disponible' => 0, // Se calcula automáticamente
                'activos' => [
                    'cuenta_ahorros' => rand(1000000, 20000000),
                    'inversiones' => rand(500000, 10000000),
                    'vehiculos' => rand(0, 50000000),
                    'propiedades' => rand(0, 200000000)
                ],
                'pasivos' => [
                    'tarjetas_credito' => rand(0, 5000000),
                    'prestamos_personales' => rand(0, 10000000),
                    'hipotecas' => rand(0, 150000000),
                    'otros_pasivos' => rand(0, 5000000)
                ],
                'patrimonio_neto' => 0, // Se calcula automáticamente
                'deudas_actuales' => [
                    [
                        'entidad' => 'Banco ABC',
                        'tipo_deuda' => 'Tarjeta de crédito',
                        'saldo' => rand(0, 5000000),
                        'cuota_mensual' => rand(0, 500000)
                    ],
                    [
                        'entidad' => 'Financiera XYZ',
                        'tipo_deuda' => 'Préstamo personal',
                        'saldo' => rand(0, 10000000),
                        'cuota_mensual' => rand(0, 1000000)
                    ]
                ]
            ],
            'estado' => $this->determinarEstadoAleatorio(),
            'observaciones' => $this->generarObservaciones('trabajador')
        ];
    }

    // Métodos auxiliares para generar datos
    private function generarNombreCompleto(): string
    {
        return $this->generarNombres() . ' ' . $this->generarApellidos();
    }

    private function generarNombres(): string
    {
        $nombres = ['Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Carmen', 'Pedro', 'Laura', 'José', 'Sofía'];
        return $nombres[array_rand($nombres)];
    }

    private function generarApellidos(): string
    {
        $apellidos = ['García', 'Rodríguez', 'Martínez', 'López', 'González', 'Pérez', 'Sánchez', 'Ramírez'];
        return $apellidos[array_rand($apellidos)] . ' ' . $apellidos[array_rand($apellidos)];
    }

    private function generarNumeroDocumento(): string
    {
        return (string) rand(10000000, 99999999);
    }

    private function generarEmail(string $username): string
    {
        $dominios = ['email.com', 'gmail.com', 'outlook.com', 'yahoo.com'];
        return $username . '@' . $dominios[array_rand($dominios)];
    }

    private function generarTelefono(): string
    {
        return '300' . rand(1000000, 9999999);
    }

    private function generarTelefonoEmpresa(): string
    {
        return '1' . rand(2000000, 7999999);
    }

    private function generarDireccion(): string
    {
        $calles = ['Calle 123', 'Carrera 45', 'Avenida 67', 'Transversal 89', 'Diagonal 23'];
        return $calles[array_rand($calles)] . ' #' . rand(1, 99) . '-' . rand(10, 99);
    }

    private function generarCiudad(): string
    {
        $ciudades = ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Bucaramanga'];
        return $ciudades[array_rand($ciudades)];
    }

    private function generarDepartamento(): string
    {
        $departamentos = ['Cundinamarca', 'Antioquia', 'Valle del Cauca', 'Atlántico', 'Santander'];
        return $departamentos[array_rand($departamentos)];
    }

    private function generarFechaNacimiento(): string
    {
        $year = rand(1970, 1995);
        $month = rand(1, 12);
        $day = rand(1, 28);
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function generarSexo(): string
    {
        return rand(0, 1) ? 'M' : 'F';
    }

    private function generarEstadoCivil(): string
    {
        $estados = ['Soltero', 'Casado', 'Unión libre', 'Divorciado'];
        return $estados[array_rand($estados)];
    }

    private function generarNivelEducativo(): string
    {
        $niveles = ['Primaria', 'Secundaria', 'Técnico', 'Tecnológico', 'Profesional', 'Posgrado'];
        return $niveles[array_rand($niveles)];
    }

    private function generarTipoVivienda(): string
    {
        $tipos = ['Propia', 'Arrendada', 'Familiar', 'Otro'];
        return $tipos[array_rand($tipos)];
    }

    private function generarBoolean(): bool
    {
        return rand(0, 1) === 1;
    }

    private function generarNombreEmpresa(): string
    {
        $nombres = ['Empresa ABC S.A.', 'Comercial XYZ Ltda.', 'Industrias 123 SAS', 'Servicios Globales C.A.'];
        return $nombres[array_rand($nombres)];
    }

    private function generarCargo(): string
    {
        $cargos = ['Gerente', 'Analista', 'Coordinador', 'Supervisor', 'Especialista', 'Asistente'];
        return $cargos[array_rand($cargos)];
    }

    private function generarFechaIngreso(): string
    {
        $yearsAtraso = rand(1, 10);
        return now()->subYears($yearsAtraso)->format('Y-m-d');
    }

    private function generarTipoContrato(): string
    {
        $tipos = ['Indefinido', 'Término fijo', 'Obra labor', 'Prestación servicios'];
        return $tipos[array_rand($tipos)];
    }

    private function generarSectorEconomico(): string
    {
        $sectores = ['Comercio', 'Servicios', 'Manufactura', 'Construcción', 'Tecnología'];
        return $sectores[array_rand($sectores)];
    }

    private function generarActividadEconomica(): string
    {
        $actividades = ['Venta de productos', 'Servicios profesionales', 'Producción industrial', 'Consultoría', 'Distribución'];
        return $actividades[array_rand($actividades)];
    }

    private function generarDescripcionNegocio(): string
    {
        $descripciones = [
            'Empresa dedicada a la comercialización de productos de consumo',
            'Prestación de servicios técnicos y profesionales',
            'Fabricación y distribución de bienes industriales',
            'Consultoría y asesoría empresarial',
            'Distribución mayorista y minorista'
        ];
        return $descripciones[array_rand($descripciones)];
    }

    private function generarNumeroCuenta(): string
    {
        return str_pad((string) rand(1, 999999999), 10, '0', STR_PAD_LEFT);
    }

    private function determinarEstadoAleatorio(): string
    {
        $estados = ['iniciada', 'completa', 'verificada', 'aprobada', 'rechazada'];
        $pesos = [10, 30, 25, 25, 10]; // Probabilidades
        $random = rand(1, 100);

        $acumulado = 0;
        foreach ($estados as $index => $estado) {
            $acumulado += $pesos[$index];
            if ($random <= $acumulado) {
                return $estado;
            }
        }

        return 'iniciada';
    }

    private function generarObservaciones(string $tipo): string
    {
        $observaciones = [
            'trabajador' => [
                'Postulación inicial registrada en el sistema',
                'Documentación personal verificada',
                'Información laboral confirmada',
                'Análisis financiero completado'
            ],
            'empresa' => [
                'Postulación empresarial iniciada',
                'Documentación legal verificada',
                'Información financiera validada',
                'Referencias comerciales contactadas'
            ]
        ];

        $obs = $observaciones[$tipo] ?? [];
        return implode(' | ', array_slice($obs, 0, rand(1, 3)));
    }
}
