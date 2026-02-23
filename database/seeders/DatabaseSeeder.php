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
            AdvisersSeeder::class,
            EmpresasConvenioSeeder::class,
            EstadosSolicitudSeeder::class,
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
    }
}
