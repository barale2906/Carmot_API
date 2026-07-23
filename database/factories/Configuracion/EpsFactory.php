<?php

namespace Database\Factories\Configuracion;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Configuracion\Eps>
 */
class EpsFactory extends Factory
{
    protected $model = \App\Models\Configuracion\Eps::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombres = [
            'Sura', 'Sanitas', 'Compensar', 'Nueva EPS', 'Famisanar',
            'Salud Total', 'Coomeva', 'Cafesalud', 'Medimás', 'Coosalud',
        ];

        return [
            'nombre' => $this->faker->unique()->randomElement($nombres) . ' ' . $this->faker->companySuffix(),
            'direccion' => $this->faker->address(),
            'status' => $this->faker->randomElement([0, 1]),
        ];
    }

    /**
     * Estado activo.
     *
     * @return static
     */
    public function activa(): static
    {
        return $this->state(['status' => 1]);
    }

    /**
     * Estado inactivo.
     *
     * @return static
     */
    public function inactiva(): static
    {
        return $this->state(['status' => 0]);
    }
}
