<?php

namespace Database\Factories\Activos;

use App\Models\Activos\ActCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<ActCategoria>
 */
class ActCategoriaFactory extends Factory
{
    protected $model = ActCategoria::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'      => fake()->unique()->randomElement(['Herramienta', 'Equipo de cómputo', 'Mobiliario', 'Maquinaria', 'Vehículo', 'Equipo de taller']) . ' ' . fake()->unique()->numberBetween(1, 9999),
            'descripcion' => fake()->optional()->sentence(),
            'status'      => 1,
        ];
    }
}
