<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla
        DB::table('modules')->delete();

        // Módulos principales
        $modules = [
            // Dashboard
            [
                'parent_id' => null,
                'key' => 'dashboard',
                'title' => 'Dashboard',
                'route_name' => 'dashboard',
                'href' => '/dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'section' => 'Principal',
                'ordering' => 1,
                'active' => 'S',
                'description' => 'Panel principal',
                'permissions_required' => json_encode(['view_dashboard']),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Gestión de Usuarios
            [
                'parent_id' => null,
                'key' => 'users',
                'title' => 'Usuarios',
                'icon' => 'fas fa-users',
                'section' => 'Administración',
                'ordering' => 2,
                'active' => 'S',
                'description' => 'Gestión de usuarios',
                'permissions_required' => json_encode(['manage_users']),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Solicitudes de Crédito
            [
                'parent_id' => null,
                'key' => 'solicitudes',
                'title' => 'Solicitudes',
                'icon' => 'fas fa-file-contract',
                'section' => 'Créditos',
                'ordering' => 3,
                'active' => 'S',
                'description' => 'Gestión de solicitudes de crédito',
                'permissions_required' => json_encode(['manage_solicitudes']),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Reportes
            [
                'parent_id' => null,
                'key' => 'reports',
                'title' => 'Reportes',
                'icon' => 'fas fa-chart-bar',
                'section' => 'Reportes',
                'ordering' => 4,
                'active' => 'S',
                'description' => 'Reportes y estadísticas',
                'permissions_required' => json_encode(['view_reports']),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Configuración
            [
                'parent_id' => null,
                'key' => 'settings',
                'title' => 'Configuración',
                'icon' => 'fas fa-cog',
                'section' => 'Sistema',
                'ordering' => 5,
                'active' => 'S',
                'description' => 'Configuración del sistema',
                'permissions_required' => json_encode(['manage_settings']),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Perfil
            [
                'parent_id' => null,
                'key' => 'profile',
                'title' => 'Mi Perfil',
                'route_name' => 'profile',
                'href' => '/profile',
                'icon' => 'fas fa-user',
                'section' => 'Usuario',
                'ordering' => 6,
                'active' => 'S',
                'description' => 'Perfil de usuario',
                'permissions_required' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('modules')->insert($modules);

        $this->command->info('Módulos básicos creados exitosamente.');
    }
}
