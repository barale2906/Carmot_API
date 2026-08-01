<?php

namespace Database\Factories\Inventarios;

use App\Models\Inventarios\InvCategoria;
use App\Models\Inventarios\InvUnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventarios\InvProducto>
 */
class InvProductoFactory extends Factory
{
    protected $model = \App\Models\Inventarios\InvProducto::class;

    public function definition(): array
    {
        return [
            'codigo'            => strtoupper($this->faker->unique()->bothify('PROD-####')),
            'nombre'            => $this->faker->words(3, true),
            'descripcion'       => $this->faker->optional()->sentence(),
            'tipo'              => 'simple',
            'imagen'            => null,
            'categoria_id'      => InvCategoria::factory(),
            'unidad_medida_id'  => InvUnidadMedida::factory(),
            'producto_padre_id' => null,
            'status'            => 1,
        ];
    }

    public function activo(): static
    {
        return $this->state(['status' => 1]);
    }

    public function inactivo(): static
    {
        return $this->state(['status' => 0]);
    }

    public function kit(): static
    {
        return $this->state(['tipo' => 'kit']);
    }

    public function grupo(): static
    {
        return $this->state(['tipo' => 'grupo', 'categoria_id' => null, 'unidad_medida_id' => null]);
    }
}
