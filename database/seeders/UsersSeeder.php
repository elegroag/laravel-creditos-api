<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla antes de insertar
        DB::table('users')->delete();

        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@comfaca.com',
                'full_name' => 'Administrador del Sistema',
                'phone' => '3001234567',
                'password' => 'Admin123!',
                'tipo_documento' => '1',
                'numero_documento' => '12345678',
                'nombres' => 'Administrador',
                'apellidos' => 'Sistema',
                'roles' => ['administrator'],
                'disabled' => false
            ],
            [
                'username' => 'asesor1',
                'email' => 'asesor1@comfaca.com',
                'full_name' => 'Juan Pérez Asesor',
                'phone' => '3007654321',
                'password' => 'Asesor123!',
                'tipo_documento' => '1',
                'numero_documento' => '87654321',
                'nombres' => 'Juan',
                'apellidos' => 'Pérez',
                'roles' => ['adviser'],
                'disabled' => false
            ],
            [
                'username' => 'empresa1',
                'email' => 'contacto@empresa1.com',
                'full_name' => 'Empresa Demo S.A.',
                'phone' => '3005551234',
                'password' => 'Empresa123!',
                'tipo_documento' => '3',
                'numero_documento' => '900123456',
                'nombres' => 'Empresa',
                'apellidos' => 'Demo S.A.',
                'roles' => ['user_empresa'],
                'disabled' => false
            ],
            [
                'username' => 'trabajador1',
                'email' => 'trabajador1@email.com',
                'full_name' => 'María García Trabajadora',
                'phone' => '3008889999',
                'password' => 'Trabajador123!',
                'tipo_documento' => '1',
                'numero_documento' => '11223344',
                'nombres' => 'María',
                'apellidos' => 'García',
                'roles' => ['user_trabajador'],
                'disabled' => false
            ],
            [
                'username' => 'elegro',
                'email' => 'eelgroag@email.com',
                'full_name' => 'María García Trabajadora',
                'phone' => '3008889999',
                'password' => '8912Elegro$.',
                'tipo_documento' => '1',
                'numero_documento' => '1110491951',
                'nombres' => 'María',
                'apellidos' => 'García',
                'roles' => ['user_trabajador'],
                'disabled' => false
            ]
        ];

        foreach ($users as $userData) {
            $user = User::create([
                'username' => $userData['username'],
                'email' => $userData['email'],
                'full_name' => $userData['full_name'],
                'phone' => $userData['phone'],
                'password_hash' => Hash::make($userData['password']),
                'tipo_documento' => $userData['tipo_documento'],
                'numero_documento' => $userData['numero_documento'],
                'nombres' => $userData['nombres'],
                'apellidos' => $userData['apellidos'],
                'roles' => $userData['roles'],
                'disabled' => $userData['disabled'],
                'is_active' => $userData['is_active'] ?? true,
                'email_verified_at' => now()
            ]);
        }

        $this->command->info('Usuarios creados exitosamente');
        $this->command->info('Credenciales de prueba:');
        $this->command->info('Admin: admin / Admin123!');
        $this->command->info('Asesor: asesor1 / Asesor123!');
        $this->command->info('Empresa: empresa1 / Empresa123!');
        $this->command->info('Trabajador: trabajador1 / Trabajador123!');
    }
}
