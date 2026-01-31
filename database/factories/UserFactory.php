<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fullName = fake()->name();
        $nameParts = explode(' ', $fullName, 2);
        $nombres = $nameParts[0];
        $apellidos = $nameParts[1] ?? '';

        return [
            'username' => strtolower(str_replace(' ', '', $fullName)),
            'email' => fake()->unique()->safeEmail(),
            'full_name' => $fullName,
            'phone' => fake()->phoneNumber(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'tipo_documento' => fake()->randomElement(['cedula', 'tarjeta', 'pasaporte']),
            'numero_documento' => fake()->unique()->numerify('#########'),
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'roles' => json_encode(['user_trabajador']),
            'disabled' => false,
            'is_active' => true,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
