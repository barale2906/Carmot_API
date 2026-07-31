<?php

namespace Database\Factories\Configuracion;

use App\Models\Configuracion\Banco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Configuracion\Banco>
 */
class BancoFactory extends Factory
{
    protected $model = Banco::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company() . ' Bank',
            'codigo' => fake()->optional()->numerify('###-#####'),
            'status' => 1,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 0]);
    }
}
