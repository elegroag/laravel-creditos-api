<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Iniciando seeders para la base de datos MySQL...');

        // Ejecutar seeders en orden correcto
        $this->call([
            RolesSeeder::class,
            UsersSeeder::class,
            EmpresasConvenioSeeder::class,
            EstadosSolicitudSeeder::class,
            LineasInversionSeeder::class,
            NumeroSolicitudSeeder::class,
            SolicitudesCreditoSeeder::class,
            SolicitudDocumentosSeeder::class,
            SolicitudPayloadSeeder::class,
            SolicitudTimelineSeeder::class,
            EntidadDigitalSeeder::class,
            TipoDocumentoSeeder::class,
            DocumentosPostulantesSeeder::class,
            PostulacionesSeeder::class,
        ]);

        $this->command->info('Seeders completados exitosamente!');
        $this->command->info('');
        $this->command->info('Resumen de datos creados:');
        $this->command->info('');
        $this->command->info('👥 Usuarios (4):');
        $this->command->info('1. Administrador: admin / Admin123!');
        $this->command->info('2. Asesor: asesor1 / Asesor123!');
        $this->command->info('3. Empresa: empresa1 / Empresa123!');
        $this->command->info('4. Trabajador: trabajador1 / Trabajador123!');
        $this->command->info('');
        $this->command->info('🏢 Empresas (5):');
        $this->command->info('1. COMFACA (900123456) - Activo');
        $this->command->info('2. INDUSTRIAS ALIMENTICIAS (800654321) - Activo');
        $this->command->info('3. CONSTRUCCIONES (700987654) - Activo');
        $this->command->info('4. SERVICIOS TECNOLÓGICOS (600123789) - Suspendido');
        $this->command->info('5. TRANSPORTE Y LOGÍSTICA (500456789) - Vencido');
        $this->command->info('');
        $this->command->info('📋 Estados de Solicitud (12):');
        $this->command->info('POSTULADO → EN_REVISION → PRE_APROBADO → APROBADO → FINALIZADO');
        $this->command->info('Con estados especiales: RECHAZADO, CANCELADO, DESISTE, EN_MORA, CASTIGADO');
        $this->command->info('');
        $this->command->info('💰 Líneas de Inversión (6):');
        $this->command->info('1. Trabajadores - $50M - Activo');
        $this->command->info('2. Empresas - $200M - Activo');
        $this->command->info('3. Consumo - $25M - Activo');
        $this->command->info('4. Educativo - $30M - Activo');
        $this->command->info('5. Vivienda - $150M - Activo');
        $this->command->info('6. Microempresas - $35M - Inactivo');
        $this->command->info('Total disponible: $455M (líneas activas)');
        $this->command->info('');
        $this->command->info('🔢 Números de Solicitud (12):');
        $this->command->info('Año 2025: 3 secuencias (línea 03)');
        $this->command->info('Año 2024: 3 secuencias (línea 03)');
        $this->command->info('Año 2023: 3 secuencias (línea 03)');
        $this->command->info('Año 2022: 3 secuencias (línea 01)');
        $this->command->info('Formato: XXXXXX-YYYYMM-LC');
        $this->command->info('');
        $this->command->info('📋 Solicitudes de Crédito (10):');
        $this->command->info('Año 2025: 3 solicitudes ($70M total)');
        $this->command->info('Año 2024: 3 solicitudes ($155M total)');
        $this->command->info('Año 2023: 3 solicitudes ($77M total)');
        $this->command->info('Año 2022: 1 solicitud ($150M total)');
        $this->command->info('Total: $452M solicitado, $277M aprobado (43.3% tasa)');
        $this->command->info('');
        $this->command->info('📄 Documentos de Solicitud (25+):');
        $this->command->info('Promedio: 2.5 documentos por solicitud');
        $this->command->info('Tipos: Identificación, ingresos, certificados, referencias');
        $this->command->info('Específicos: Vivienda, educación, empresa, vehículo');
        $this->command->info('');
        $this->command->info('📦 Payload de Solicitud (10):');
        $this->command->info('Estructura JSON completa para cada solicitud');
        $this->command->info('Datos: Solicitante, laboral, financiero, referencias');
        $this->command->info('Versión: 1.0 con save_xml activado');
        $this->command->info('');
        $this->command->info('📅 Timeline de Solicitudes (35+):');
        $this->command->info('Promedio: 3.5 entradas por solicitud');
        $this->command->info('Tipos: Cambios de estado, observaciones, notificaciones');
        $this->command->info('Automáticos: Creación, revisiones, aprobaciones');
        $this->command->info('');
        $this->command->info('🔐 Entidad Digital (4):');
        $this->command->info('Una entidad por usuario con documentos digitales');
        $this->command->info('Documentos: Cédula, selfie, firma, certificados');
        $this->command->info('Estados: Activas, validación biométrica completada');
        $this->command->info('');
        $this->command->info('📄 Documentos Postulantes (20+):');
        $this->command->info('Documentos personales por usuario según rol');
        $this->command->info('Admin: Pasaporte, certificados, declaración renta');
        $this->command->info('Asesor: Certificado laboral, referencias');
        $this->command->info('Empresa: RUT, cámara comercio, estados financieros');
        $this->command->info('Trabajador: Contrato, extractos, comprobantes');
        $this->command->info('');
        $this->command->info('📋 Postulaciones (4):');
        $this->command->info('Una postulación por usuario con datos completos');
        $this->command->info('Trabajadores: Datos personales, laborales, financieros');
        $this->command->info('Empresas: Información legal, comercial, financiera');
        $this->command->info('Estados: Iniciadas, completas, verificadas, aprobadas');
        $this->command->info('');
        $this->command->info('🔐 Roles del sistema:');
        $this->command->info('- administrator: Acceso completo al sistema');
        $this->command->info('- adviser: Gestión de solicitudes y reportes');
        $this->command->info('- user_empresa: Postulación de créditos para empresas');
        $this->command->info('- user_trabajador: Postulación de créditos para trabajadores');
    }
}
