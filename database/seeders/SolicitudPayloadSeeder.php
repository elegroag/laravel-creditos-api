<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\SolicitudPayload;
use App\Models\SolicitudCredito;

class SolicitudPayloadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('solicitud_payload')->delete();

        // Obtener solicitudes existentes
        $solicitudes = SolicitudCredito::all();

        foreach ($solicitudes as $solicitud) {
            // Generar payload según el tipo de solicitud
            $payload = $this->generarPayloadParaSolicitud($solicitud);
            SolicitudPayload::create($payload);
        }

        $this->command->info('Payload de solicitudes creado exitosamente');
        $this->command->info('');
        $this->command->info('Resumen de payload creados:');
        $this->command->info('Total payload: ' . $solicitudes->count());
        $this->command->info('Versión: 1.0 para todos los registros');
        $this->command->info('');
        $this->command->info('Estructura de datos JSON:');
        $this->command->info('- encabezado: Fecha radicado, información general');
        $this->command->info('- solicitud: Número, valor, categoría');
        $this->command->info('- producto_solicitado: Tipo y historial crediticio');
        $this->command->info('- solicitante: Datos personales completos');
        $this->command->info('- informacion_laboral: Datos del empleo');
        $this->command->info('- ingresos_descuentos: Información financiera');
        $this->command->info('- informacion_economica: Activos y pasivos');
        $this->command->info('- propiedades: Bienes del solicitante');
        $this->command->info('- deudas: Deudas existentes');
        $this->command->info('- referencias: Contactos personales y familiares');
        $this->command->info('- linea_credito: Detalles del crédito solicitado');
    }

    /**
     * Generar payload según el tipo de solicitud
     */
    private function generarPayloadParaSolicitud(SolicitudCredito $solicitud): array
    {
        $numeroSolicitud = $solicitud->numero_solicitud;
        $destino = strtolower($solicitud->destino_credito ?? '');
        $monto = $solicitud->monto_solicitado;

        // Datos base del solicitante
        $solicitanteBase = [
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
            'email' => $this->generarEmail(),
            'nivel_educativo' => $this->generarNivelEducativo()
        ];

        // Información laboral base
        $infoLaboralBase = [
            'empresa' => $this->generarEmpresa(),
            'cargo' => $this->generarCargo(),
            'salario' => $this->generarSalario($monto),
            'fecha_ingreso' => $this->generarFechaIngreso(),
            'tipo_contrato' => $this->generarTipoContrato(),
            'telefono_empresa' => $this->generarTelefonoEmpresa(),
            'direccion_empresa' => $this->generarDireccionEmpresa(),
            'ciudad_empresa' => $this->generarCiudadEmpresa()
        ];

        // Ajustar según destino del crédito
        if (strpos($destino, 'vivienda') !== false) {
            $solicitanteBase['tipo_vivienda'] = 'propia';
            $solicitanteBase['tiempo_vivienda'] = '5 años';
            $infoLaboralBase['sector_economico'] = 'Construcción';
        } elseif (strpos($destino, 'educacion') !== false || strpos($destino, 'educativo') !== false) {
            $solicitanteBase['nivel_educativo_actual'] = 'Profesional';
            $solicitanteBase['institucion_educativa'] = 'Universidad Nacional';
            $infoLaboralBase['sector_economico'] = 'Educación';
        } elseif (strpos($destino, 'empresa') !== false || strpos($destino, 'capital') !== false) {
            $solicitanteBase['tipo_persona'] = 'juridica';
            $solicitanteBase['representante_legal'] = $solicitanteBase['nombres'] . ' ' . $solicitanteBase['apellidos'];
            $infoLaboralBase['sector_economico'] = 'Comercio';
        } elseif (strpos($destino, 'vehiculo') !== false) {
            $infoLaboralBase['sector_economico'] = 'Transporte';
        }

        return [
            'solicitud_id' => $solicitud->id,
            'version' => '1.0',
            'encabezado' => [
                'fecha_radicado' => $solicitud->created_at->format('Y-m-d H:i:s'),
                'usuario_radica' => $solicitud->owner_username,
                'sistema_origen' => 'COMFACA_CREDITOS',
                'version_formato' => '1.0',
                'ip_origen' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            'solicitud' => [
                'numero_solicitud' => $numeroSolicitud,
                'valor_solicitud' => $monto,
                'categoria' => $this->determinarCategoria($monto),
                'plazo_meses' => $solicitud->plazo_meses,
                'tasa_interes' => $solicitud->tasa_interes,
                'destino_credito' => $solicitud->destino_credito,
                'descripcion' => $solicitud->descripcion
            ],
            'producto_solicitado' => [
                'tipo' => $this->determinarTipoProducto($destino),
                'ha_tenido_credito_comfaca' => $this->generarBoolean(),
                'ultimo_credito_fecha' => $this->generarBoolean() ? $this->generarFechaAnterior() : null,
                'ultimo_credito_monto' => $this->generarBoolean() ? $this->generarMontoAnterior() : null,
                'comportamiento_pago' => $this->generarBoolean() ? 'Excelente' : null
            ],
            'solicitante' => $solicitanteBase,
            'informacion_laboral' => $infoLaboralBase,
            'ingresos_descuentos' => [
                'ingreso_mensual' => $infoLaboralBase['salario'],
                'ingreso_adicional' => $this->generarIngresoAdicional(),
                'ingreso_conyuge' => $this->generarIngresoConyuge(),
                'ingresos_otros' => $this->generarIngresosOtros(),
                'total_ingresos' => $this->calcularTotalIngresos($infoLaboralBase['salario']),
                'descuento_pension' => $this->generarDescuento($infoLaboralBase['salario'], 0.04),
                'descuento_salud' => $this->generarDescuento($infoLaboralBase['salario'], 0.04),
                'descuento_parafiscales' => $this->generarDescuento($infoLaboralBase['salario'], 0.02),
                'total_descuentos' => $this->calcularTotalDescuentos($infoLaboralBase['salario']),
                'ingreso_disponible' => $this->calcularIngresoDisponible($infoLaboralBase['salario'])
            ],
            'informacion_economica' => [
                'activos' => [
                    'cuenta_ahorros' => $this->generarMontoAleatorio(1000000, 10000000),
                    'inversiones' => $this->generarMontoAleatorio(500000, 5000000),
                    'vehiculos' => $this->generarMontoAleatorio(0, 50000000),
                    'propiedades' => $this->generarMontoAleatorio(0, 200000000)
                ],
                'pasivos' => [
                    'tarjetas_credito' => $this->generarMontoAleatorio(0, 5000000),
                    'prestamos_personales' => $this->generarMontoAleatorio(0, 10000000),
                    'hipotecas' => $this->generarMontoAleatorio(0, 150000000),
                    'otros_pasivos' => $this->generarMontoAleatorio(0, 5000000)
                ],
                'patrimonio_neto' => $this->generarMontoAleatorio(10000000, 300000000)
            ],
            'propiedades' => [
                'tipo_propiedad' => strpos($destino, 'vivienda') !== false ? 'Apartamento' : null,
                'direccion_propiedad' => strpos($destino, 'vivienda') !== false ? $this->generarDireccion() : null,
                'valor_propiedad' => strpos($destino, 'vivienda') !== false ? $this->generarMontoAleatorio(100000000, 300000000) : null,
                'hipoteca_actual' => strpos($destino, 'vivienda') !== false ? $this->generarBoolean() : null
            ],
            'deudas' => [
                'deudas_actuales' => $this->generarDeudasActuales(),
                'cuota_mensual_total' => $this->generarMontoAleatorio(0, 5000000),
                'entidades_financieras' => $this->generarEntidadesFinancieras()
            ],
            'referencias' => [
                'referencias_personales' => $this->generarReferenciasPersonales(),
                'referencias_familiares' => $this->generarReferenciasFamiliares(),
                'referencias_laborales' => $this->generarReferenciasLaborales()
            ],
            'linea_credito' => [
                'linea_seleccionada' => $this->determinarLineaCredito($monto),
                'monto_maximo_linea' => $this->determinarMontoMaximoLinea($monto),
                'tasa_aplicable' => $solicitud->tasa_interes,
                'cuota_estimada' => $this->calcularCuotaEstimada($monto, $solicitud->tasa_interes, $solicitud->plazo_meses),
                'seguro_desempleo' => $this->generarBoolean(),
                'seguro_vida' => $this->generarBoolean()
            ],
            'save_xml' => true
        ];
    }

    // Métodos auxiliares para generar datos
    private function generarNumeroDocumento(): string
    {
        return (string) rand(10000000, 99999999);
    }

    private function generarNombres(): string
    {
        $nombres = ['Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Carmen', 'Pedro', 'Laura', 'José', 'Sofía'];
        return $nombres[array_rand($nombres)];
    }

    private function generarApellidos(): string
    {
        $apellidos = ['García', 'Rodríguez', 'Martínez', 'López', 'González', 'Pérez', 'Sánchez', 'Ramírez', 'Torres', 'Díaz'];
        return $apellidos[array_rand($apellidos)] . ' ' . $apellidos[array_rand($apellidos)];
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

    private function generarDireccion(): string
    {
        $calles = ['Calle 123', 'Carrera 45', 'Avenida 67', 'Transversal 89', 'Diagonal 23'];
        $numeros = rand(1, 99);
        return $calles[array_rand($calles)] . ' #' . $numeros . '-' . rand(10, 99);
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

    private function generarTelefono(): string
    {
        return '300' . rand(1000000, 9999999);
    }

    private function generarEmail(): string
    {
        $nombres = strtolower($this->generarNombres());
        $apellidos = strtolower(str_replace(' ', '', $this->generarApellidos()));
        $numeros = rand(10, 99);
        return "{$nombres}.{$apellidos}{$numeros}@email.com";
    }

    private function generarNivelEducativo(): string
    {
        $niveles = ['Primaria', 'Secundaria', 'Técnico', 'Tecnológico', 'Profesional', 'Posgrado'];
        return $niveles[array_rand($niveles)];
    }

    private function generarEmpresa(): string
    {
        $empresas = ['Empresa ABC S.A.', 'Comercial XYZ Ltda.', 'Industrias 123 SAS', 'Servicios Globales C.A.'];
        return $empresas[array_rand($empresas)];
    }

    private function generarCargo(): string
    {
        $cargos = ['Gerente', 'Analista', 'Coordinador', 'Supervisor', 'Especialista', 'Asistente'];
        return $cargos[array_rand($cargos)];
    }

    private function generarSalario(float $montoCredito): float
    {
        // El salario debe ser razonable en relación al crédito solicitado
        $minimo = $montoCredito * 0.05; // Mínimo 5% del crédito
        $maximo = $montoCredito * 0.5;  // Máximo 50% del crédito
        return rand($minimo, $maximo);
    }

    private function generarFechaIngreso(): string
    {
        $yearsAtraso = rand(1, 10);
        $fecha = now()->subYears($yearsAtraso);
        return $fecha->format('Y-m-d');
    }

    private function generarTipoContrato(): string
    {
        $tipos = ['Indefinido', 'Término fijo', 'Obra labor', 'Prestación servicios'];
        return $tipos[array_rand($tipos)];
    }

    private function generarTelefonoEmpresa(): string
    {
        return '1' . rand(2000000, 7999999);
    }

    private function generarDireccionEmpresa(): string
    {
        return $this->generarDireccion();
    }

    private function generarCiudadEmpresa(): string
    {
        return $this->generarCiudad();
    }

    private function determinarCategoria(float $monto): string
    {
        if ($monto <= 25000000) return 'C';
        if ($monto <= 100000000) return 'B';
        return 'A';
    }

    private function determinarTipoProducto(string $destino): string
    {
        if (strpos($destino, 'vivienda') !== false) return 'Vivienda';
        if (strpos($destino, 'educacion') !== false || strpos($destino, 'educativo') !== false) return 'Educativo';
        if (strpos($destino, 'vehiculo') !== false) return 'Vehículo';
        if (strpos($destino, 'empresa') !== false || strpos($destino, 'capital') !== false) return 'Empresarial';
        return 'Consumo';
    }

    private function generarBoolean(): bool
    {
        return rand(0, 1) === 1;
    }

    private function generarFechaAnterior(): string
    {
        $yearsAtraso = rand(2, 5);
        return now()->subYears($yearsAtraso)->format('Y-m-d');
    }

    private function generarMontoAnterior(): float
    {
        return rand(5000000, 50000000);
    }

    private function generarIngresoAdicional(): float
    {
        return rand(0, 2000000);
    }

    private function generarIngresoConyuge(): float
    {
        return rand(0, 3000000);
    }

    private function generarIngresosOtros(): float
    {
        return rand(0, 1000000);
    }

    private function calcularTotalIngresos(float $salario): float
    {
        return $salario + $this->generarIngresoAdicional() + $this->generarIngresoConyuge() + $this->generarIngresosOtros();
    }

    private function generarDescuento(float $base, float $porcentaje): float
    {
        return $base * $porcentaje;
    }

    private function calcularTotalDescuentos(float $salario): float
    {
        return $this->generarDescuento($salario, 0.04) + $this->generarDescuento($salario, 0.04) + $this->generarDescuento($salario, 0.02);
    }

    private function calcularIngresoDisponible(float $salario): float
    {
        return $this->calcularTotalIngresos($salario) - $this->calcularTotalDescuentos($salario);
    }

    private function generarMontoAleatorio(int $min, int $max): float
    {
        return rand($min, $max);
    }

    private function generarDeudasActuales(): int
    {
        return rand(0, 5);
    }

    private function generarEntidadesFinancieras(): array
    {
        $entidades = ['Banco ABC', 'Financiera XYZ', 'Cooperativa 123'];
        return array_slice($entidades, 0, rand(1, 3));
    }

    private function generarReferenciasPersonales(): array
    {
        return [
            [
                'nombre' => $this->generarNombres() . ' ' . $this->generarApellidos(),
                'telefono' => $this->generarTelefono(),
                'parentesco' => 'Amigo',
                'tiempo_conocido' => rand(1, 10) . ' años'
            ]
        ];
    }

    private function generarReferenciasFamiliares(): array
    {
        return [
            [
                'nombre' => $this->generarNombres() . ' ' . $this->generarApellidos(),
                'telefono' => $this->generarTelefono(),
                'parentesco' => 'Hermano',
                'tiempo_conocido' => rand(20, 50) . ' años'
            ]
        ];
    }

    private function generarReferenciasLaborales(): array
    {
        return [
            [
                'nombre' => $this->generarNombres() . ' ' . $this->generarApellidos(),
                'cargo' => $this->generarCargo(),
                'telefono' => $this->generarTelefonoEmpresa(),
                'empresa' => $this->generarEmpresa(),
                'tiempo_laborando' => rand(1, 5) . ' años'
            ]
        ];
    }

    private function determinarLineaCredito(float $monto): string
    {
        if ($monto <= 25000000) return 'Línea de Crédito de Consumo';
        if ($monto <= 100000000) return 'Línea de Crédito para Empresas';
        return 'Línea de Crédito para Vivienda';
    }

    private function determinarMontoMaximoLinea(float $monto): float
    {
        return $monto * 1.5; // 50% más que el solicitado
    }

    private function calcularCuotaEstimada(float $monto, float $tasa, int $plazo): float
    {
        $tasaMensual = $tasa / 100 / 12;
        return $monto * ($tasaMensual * pow(1 + $tasaMensual, $plazo)) / (pow(1 + $tasaMensual, $plazo) - 1);
    }
}
