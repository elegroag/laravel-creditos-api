<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdvisersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $advisers = [
            [
                'username' => '332',
                'email' => 'marisaenz05@yahoo.com',
                'full_name' => 'MARITZA SAENZ REINA',
                'phone' => null,
                'password' => 'asesor40740182!',
                'tipo_documento' => '1',
                'numero_documento' => 40740182,
                'nombres' => 'MARITZA SAENZ',
                'apellidos' => 'REINA',
                'roles' => ['adviser'],
                'disabled' => false
            ],
            [
                'username' => '553',
                'email' => 'edilsarc123@gmail.com',
                'full_name' => 'EDILSA ROJAS CASTRO',
                'phone' => null,
                'password' => 'asesor40773298!',
                'tipo_documento' => '1',
                'numero_documento' => 40773298,
                'nombres' => 'EDILSA ROJAS',
                'apellidos' => 'CASTRO',
                'roles' => ['adviser'],
                'disabled' => false
            ],
            [
                'username' => '417',
                'email' => 'chaloradio@gmail.com',
                'full_name' => 'GONZALO ENRIQUE JIMENEZ ALVAREZ',
                'phone' => null,
                'password' => 'asesor17674188!',
                'tipo_documento' => '1',
                'numero_documento' => 17674188,
                'nombres' => 'GONZALO ENRIQUE',
                'apellidos' => 'JIMENEZ ALVAREZ',
                'roles' => ['adviser'],
                'disabled' => false
            ],
            [
                'username' => '488',
                'email' => 'creditos@comfaca.com',
                'full_name' => 'LILIANA LEYTON TRUJILLO',
                'phone' => null,
                'password' => 'asesor40610986!',
                'tipo_documento' => '1',
                'numero_documento' => 40610986,
                'nombres' => 'LILIANA LEYTON',
                'apellidos' => 'TRUJILLO',
                'roles' => ['adviser'],
                'disabled' => false
            ]
        ];

        foreach ($advisers as $userData) {
            // Eliminar usuario existente por email si ya existe
            $existingUser = User::where('email', $userData['email'])->first();
            if ($existingUser) {
                $existingUser->delete();
            }

            User::create([
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

        $this->command->info('Usuarios advisers creados exitosamente');
        #php artisan db:seed --class=AdvisersSeeder
    }
}
