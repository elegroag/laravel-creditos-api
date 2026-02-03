<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\EmpresaConvenio;

class EmpresasConvenioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('empresas_convenio')->delete();

        $empresas = [
            [
                'nit' => 900123456,
                'razon_social' => 'COMFACA COOPERATIVA FINANCIERA',
                'fecha_convenio' => '2020-01-15',
                'fecha_vencimiento' => '2025-12-31',
                'estado' => 'Activo',
                'representante_documento' => '12345678',
                'representante_nombre' => 'Carlos Rodríguez Pérez',
                'telefono' => '3001234567',
                'correo' => 'contacto@comfaca.com',
                'direccion' => 'Calle 123 #45-67',
                'ciudad' => 'Bogotá',
                'departamento' => 'Cundinamarca',
                'sector_economico' => 'Servicios Financieros',
                'numero_empleados' => 150,
                'tipo_empresa' => 'Cooperativa',
                'descripcion' => 'Cooperativa financiera especializada en créditos para trabajadores y empresas asociadas',
                'notas_internas' => 'Empresa principal del sistema'
            ],
            [
                'nit' => 800654321,
                'razon_social' => 'INDUSTRIAS ALIMENTICIAS S.A.',
                'fecha_convenio' => '2021-03-20',
                'fecha_vencimiento' => '2024-12-31',
                'estado' => 'Activo',
                'representante_documento' => '87654321',
                'representante_nombre' => 'María González López',
                'telefono' => '3009876543',
                'correo' => 'rrhh@industriasalimenticias.com',
                'direccion' => 'Avenida Industrial #123-45',
                'ciudad' => 'Medellín',
                'departamento' => 'Antioquia',
                'sector_economico' => 'Alimentos y Bebidas',
                'numero_empleados' => 500,
                'tipo_empresa' => 'Sociedad Anónima',
                'descripcion' => 'Empresa dedicada a la producción y comercialización de alimentos procesados',
                'notas_internas' => 'Cliente corporativo importante'
            ],
            [
                'nit' => 700987654,
                'razon_social' => 'CONSTRUCCIONES Y OBRAS CIVILES LTDA',
                'fecha_convenio' => '2022-06-10',
                'fecha_vencimiento' => '2025-06-30',
                'estado' => 'Activo',
                'representante_documento' => '76543210',
                'representante_nombre' => 'Pedro Martínez Sánchez',
                'telefono' => '3005551234',
                'correo' => 'administracion@construcciones.com',
                'direccion' => 'Carrera 50 #100-20',
                'ciudad' => 'Cali',
                'departamento' => 'Valle del Cauca',
                'sector_economico' => 'Construcción',
                'numero_empleados' => 75,
                'tipo_empresa' => 'Limitada',
                'descripcion' => 'Empresa especializada en construcción civil y proyectos de infraestructura',
                'notas_internas' => 'Sector construcción con buen historial crediticio'
            ],
            [
                'nit' => 600123789,
                'razon_social' => 'SERVICIOS TECNOLÓGICOS INTEGRADOS SAS',
                'fecha_convenio' => '2023-01-15',
                'fecha_vencimiento' => '2024-12-31',
                'estado' => 'Suspendido',
                'representante_documento' => '65432109',
                'representante_nombre' => 'Ana Silva Ramírez',
                'telefono' => '3007778888',
                'correo' => 'contacto@serviciostec.com',
                'direccion' => 'Calle de la Tecnología #45-67',
                'ciudad' => 'Bucaramanga',
                'departamento' => 'Santander',
                'sector_economico' => 'Tecnología',
                'numero_empleados' => 30,
                'tipo_empresa' => 'SAS',
                'descripcion' => 'Empresa de servicios tecnológicos y consultoría TI',
                'notas_internas' => 'Temporalmente suspendido por revisión de cumplimiento'
            ],
            [
                'nit' => 500456789,
                'razon_social' => 'TRANSPORTE Y LOGÍSTICA RÁPIDA S.A.',
                'fecha_convenio' => '2021-09-01',
                'fecha_vencimiento' => '2023-09-01',
                'estado' => 'Vencido',
                'representante_documento' => '54321098',
                'representante_nombre' => 'Luis Fernando Torres',
                'telefono' => '3003334444',
                'correo' => 'logistica@transporterapida.com',
                'direccion' => 'Kilómetro 10 Vía al Mar',
                'ciudad' => 'Barranquilla',
                'departamento' => 'Atlántico',
                'sector_economico' => 'Transporte',
                'numero_empleados' => 200,
                'tipo_empresa' => 'Sociedad Anónima',
                'descripcion' => 'Empresa de transporte de carga y logística',
                'notas_internas' => 'Convenio vencido, pendiente renovación'
            ],
            [
                'nit' => 891190047,
                'razon_social' => 'CAJA DE COMPENSACION FAMILIAR DEL CAQUETA',
                'fecha_convenio' => '2021-09-01',
                'fecha_vencimiento' => '2028-09-01',
                'estado' => 'Activo',
                'representante_documento' => '17689152',
                'representante_nombre' => 'GUILLERMO ALFONSO PERDOMO ROJAS"',
                'telefono' => '3003334444',
                'correo' => 'logistica@transporterapida.com',
                'direccion' => 'Kilómetro 10 Vía al Mar',
                'ciudad' => 'Barranquilla',
                'departamento' => 'Atlántico',
                'sector_economico' => 'Transporte',
                'numero_empleados' => 200,
                'tipo_empresa' => 'Sociedad Anónima',
                'descripcion' => 'Empresa de transporte de carga y logística',
                'notas_internas' => 'Convenio vencido, pendiente renovación'
            ]
        ];

        foreach ($empresas as $empresa) {
            EmpresaConvenio::create($empresa);
        }

        $this->command->info('Empresas de convenio creadas exitosamente');
        $this->command->info('');
        $this->command->info('Resumen de empresas creadas:');
        $this->command->info('1. COMFACA (900123456) - Activo');
        $this->command->info('2. INDUSTRIAS ALIMENTICIAS (800654321) - Activo');
        $this->command->info('3. CONSTRUCCIONES (700987654) - Activo');
        $this->command->info('4. SERVICIOS TECNOLÓGICOS (600123789) - Suspendido');
        $this->command->info('5. TRANSPORTE Y LOGÍSTICA (500456789) - Vencido');
    }
}
