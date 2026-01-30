<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\LineaInversion;

class LineasInversionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('lineas_inversion')->delete();

        $lineas = [
            [
                'linea_credito' => 'Línea de Crédito para Trabajadores',
                'monto_maximo_pesos' => 50000000.00,
                'plazo_maximo' => '60 meses',
                'tasas_interes_anual' => [
                    'categoria_a' => '10%',
                    'categoria_b' => '11%',
                    'categoria_c' => '14%'
                ],
                'requisitos' => [
                    'Carta de trabajo actualizada',
                    'Comprobante de ingresos últimos 3 meses',
                    'Certificado de antigüedad mínimo 6 meses',
                    'Referencias personales y laborales',
                    'Documento de identidad',
                    'Historial crediticio'
                ],
                'categoria' => 'B',
                'descripcion' => 'Línea de crédito especializada para trabajadores afiliados con tasas preferenciales según categoría de riesgo',
                'estado' => 'Activo'
            ],
            [
                'linea_credito' => 'Línea de Crédito para Empresas',
                'monto_maximo_pesos' => 200000000.00,
                'plazo_maximo' => '48 meses',
                'tasas_interes_anual' => [
                    'categoria_a' => '8%',
                    'categoria_b' => '10%',
                    'categoria_c' => '12%'
                ],
                'requisitos' => [
                    'Certificado de existencia y representación legal',
                    'Estados financieros últimos 2 años',
                    'RUT actualizado',
                    'Referencias bancarias y comerciales',
                    'Plan de negocios',
                    'Garantías según monto solicitado'
                ],
                'categoria' => 'B',
                'descripcion' => 'Línea de crédito para empresas asociadas con condiciones competitivas y plazos flexibles',
                'estado' => 'Activo'
            ],
            [
                'linea_credito' => 'Línea de Crédito de Consumo',
                'monto_maximo_pesos' => 25000000.00,
                'plazo_maximo' => '36 meses',
                'tasas_interes_anual' => [
                    'categoria_a' => '12%',
                    'categoria_b' => '14%',
                    'categoria_c' => '16%'
                ],
                'requisitos' => [
                    'Comprobante de ingresos',
                    'Historial crediticio',
                    'Referencias personales',
                    'Documento de identidad',
                    'Declaración de bienes',
                    'Avalista si es requerido'
                ],
                'categoria' => 'C',
                'descripcion' => 'Línea de crédito para consumo personal con requisitos simplificados y aprobación rápida',
                'estado' => 'Activo'
            ],
            [
                'linea_credito' => 'Línea de Crédito Educativo',
                'monto_maximo_pesos' => 30000000.00,
                'plazo_maximo' => '84 meses',
                'tasas_interes_anual' => [
                    'categoria_a' => '7%',
                    'categoria_b' => '9%',
                    'categoria_c' => '11%'
                ],
                'requisitos' => [
                    'Certificado de matrícula',
                    'Comprobante de ingresos del pagador',
                    'Historial académico',
                    'Documento de identidad',
                    'Referencias del establecimiento',
                    'Carta de pago si es requerida'
                ],
                'categoria' => 'A',
                'descripcion' => 'Financiamiento para estudios con tasas preferenciales y plazos extendidos',
                'estado' => 'Activo'
            ],
            [
                'linea_credito' => 'Línea de Crédito para Vivienda',
                'monto_maximo_pesos' => 150000000.00,
                'plazo_maximo' => '240 meses',
                'tasas_interes_anual' => [
                    'categoria_a' => '6%',
                    'categoria_b' => '8%',
                    'categoria_c' => '10%'
                ],
                'requisitos' => [
                    'Escritura del inmueble',
                    'Certificado de libertad y tradición',
                    'Comprobante de ingresos',
                    'Historial crediticio',
                    'Avaluo del inmueble',
                    'Seguro del inmueble'
                ],
                'categoria' => 'A',
                'descripcion' => 'Financiamiento para compra o construcción de vivienda con las mejores tasas del mercado',
                'estado' => 'Activo'
            ],
            [
                'linea_credito' => 'Línea de Crédito para Microempresas',
                'monto_maximo_pesos' => 35000000.00,
                'plazo_maximo' => '48 meses',
                'tasas_interes_anual' => [
                    'categoria_a' => '9%',
                    'categoria_b' => '11%',
                    'categoria_c' => '13%'
                ],
                'requisitos' => [
                    'Registro mercantil',
                    'Estados financieros',
                    'Facturas de ventas últimos 6 meses',
                    'Referencias comerciales',
                    'Plan de negocios',
                    'Garantías personales o comerciales'
                ],
                'categoria' => 'B',
                'descripcion' => 'Línea diseñada para microempresas y pequeños negocios con requisitos adaptados',
                'estado' => 'Inactivo'
            ]
        ];

        foreach ($lineas as $linea) {
            LineaInversion::create($linea);
        }

        $this->command->info('Líneas de inversión creadas exitosamente');
        $this->command->info('');
        $this->command->info('Resumen de líneas creadas:');
        $this->command->info('1. Trabajadores - $50M - Activo');
        $this->command->info('2. Empresas - $200M - Activo');
        $this->command->info('3. Consumo - $25M - Activo');
        $this->command->info('4. Educativo - $30M - Activo');
        $this->command->info('5. Vivienda - $150M - Activo');
        $this->command->info('6. Microempresas - $35M - Inactivo');
        $this->command->info('');
        $this->command->info('Total monto disponible: $455M (líneas activas)');
        $this->command->info('Categorías de riesgo: A (mejor tasa), B (tasa estándar), C (tasa superior)');
    }
}
