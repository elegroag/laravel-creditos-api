<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuario de prueba
        DB::table('users')->updateOrInsert(
            ['username' => 'test_user'],
            [
                'email' => 'test@example.com',
                'password_hash' => bcrypt('password123'),
                'nombres' => 'Usuario',
                'apellidos' => 'de Prueba',
                'is_active' => 1,
                'roles' => json_encode(['user']),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }
}
