<?php

namespace Database\Factories;

use App\Models\Postulacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Postulacion>
 */
class PostulacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'tipo_postulante' => fake()->randomElement(['trabajador', 'empresa']),
            'empresa_nit' => null, // Will be set for company type postulations
            'empresa_razon_social' => null, // Will be set for company type postulations
            'datos_personales' => [
                'nombres' => fake()->firstName(),
                'apellidos' => fake()->lastName(),
                'tipo_documento' => fake()->randomElement(['1', '2']),
                'numero_documento' => fake()->numerify('##########'),
                'telefono' => fake()->phoneNumber(),
                'email' => fake()->unique()->safeEmail(),
                'direccion' => fake()->address(),
            ],
            'datos_laborales' => [
                'empresa' => fake()->company(),
                'cargo' => fake()->jobTitle(),
                'salario' => fake()->numberBetween(1000000, 5000000),
                'antiguedad' => fake()->numberBetween(1, 20),
            ],
            'datos_financieros' => [
                'ingresos_mensuales' => fake()->numberBetween(1000000, 5000000),
                'egresos_mensuales' => fake()->numberBetween(200000, 1500000),
                'otros_ingresos' => fake()->numberBetween(0, 1000000),
            ],
            'estado' => fake()->randomElement(['iniciada', 'completa', 'verificada', 'aprobada', 'rechazada']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Create a postulation for a worker
     */
    public function worker(): static
    {
        return static::state(function (array $attributes) {
            return [
                'tipo_postulante' => 'trabajador',
            ];
        });
    }

    /**
     * Create a postulation for a company
     */
    public function company(): static
    {
        return static::state(function (array $attributes) {
            return [
                'tipo_postulante' => 'empresa',
                'empresa_nit' => fake()->numerify('##########'),
                'empresa_razon_social' => fake()->company(),
            ];
        });
    }

    /**
     * Create a postulation with PENDING status
     */
    public function pending(): static
    {
        return static::state(function (array $attributes) {
            return [
                'estado' => 'iniciada',
            ];
        });
    }

    /**
     * Create a postulation with APPROVED status
     */
    public function approved(): static
    {
        return static::state(function (array $attributes) {
            return [
                'estado' => 'aprobada',
            ];
        });
    }

    /**
     * Create a postulation with REJECTED status
     */
    public function rejected(): static
    {
        return static::state(function (array $attributes) {
            return [
                'estado' => 'rechazada',
            ];
        });
    }
}
