<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use App\Models\User;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('roles')->delete();

        $roles = [
            [
                'nombre' => 'administrator',
                'descripcion' => 'Administrador del sistema con acceso completo',
                'permisos' => [
                    'users.create',
                    'users.read',
                    'users.update',
                    'users.delete',
                    'solicitudes.create',
                    'solicitudes.read',
                    'solicitudes.update',
                    'solicitudes.delete',
                    'trabajadores.read',
                    'empresas.read',
                    'reports.read',
                    'system.admin'
                ],
                'color' => '#DC2626',
                'orden' => 1,
                'activo' => true
            ],
            [
                'nombre' => 'adviser',
                'descripcion' => 'Asesor de crédito con acceso a solicitudes',
                'permisos' => [
                    'solicitudes.create',
                    'solicitudes.read',
                    'solicitudes.update',
                    'trabajadores.read',
                    'empresas.read',
                    'reports.read'
                ],
                'color' => '#2563EB',
                'orden' => 2,
                'activo' => true
            ],
            [
                'nombre' => 'user_empresa',
                'descripcion' => 'Usuario de empresa que postula créditos',
                'permisos' => [
                    'solicitudes.create',
                    'solicitudes.read',
                    'solicitudes.update',
                    'empresas.read'
                ],
                'color' => '#059669',
                'orden' => 3,
                'activo' => true
            ],
            [
                'nombre' => 'user_trabajador',
                'descripcion' => 'Usuario trabajador que postula créditos',
                'permisos' => [
                    'solicitudes.create',
                    'solicitudes.read',
                    'solicitudes.update'
                ],
                'color' => '#7C3AED',
                'orden' => 4,
                'activo' => true
            ]
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }

        $this->command->info('Roles creados exitosamente');
    }
}
