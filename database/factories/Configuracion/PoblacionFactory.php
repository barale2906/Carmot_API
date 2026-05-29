<?php

namespace Database\Factories\Configuracion;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Configuracion\Poblacion>
 */
class PoblacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pais'      => 'Colombia',
            'provincia' => fake()->randomElement(['Antioquia', 'Cundinamarca', 'Boyacá', 'Valle del Cauca', 'Santander']),
            'nombre'    => fake()->city(),
            'latitud'   => fake()->latitude(-4.23, 12.44),
            'longitud'  => fake()->longitude(-81.73, -66.87),
            'status'    => 1,
        ];
    }

    /**
     * Estado para una población inactiva.
     */
    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 0]);
    }
}
