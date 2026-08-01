<?php

namespace Database\Factories\Inventarios;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventarios\InvCategoria>
 */
class InvCategoriaFactory extends Factory
{
    protected $model = \App\Models\Inventarios\InvCategoria::class;

    public function definition(): array
    {
        $categorias = [
            'Uniformes', 'Útiles Escolares', 'Libros de Texto', 'Deportivos',
            'Arte y Manualidades', 'Tecnología', 'Laboratorio', 'Papelería',
        ];

        return [
            'nombre'      => $this->faker->unique()->randomElement($categorias) . ' ' . $this->faker->word(),
            'descripcion' => $this->faker->optional()->sentence(),
            'status'      => $this->faker->randomElement([0, 1]),
        ];
    }

    public function activa(): static
    {
        return $this->state(['status' => 1]);
    }

    public function inactiva(): static
    {
        return $this->state(['status' => 0]);
    }
}
