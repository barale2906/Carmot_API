<?php

namespace Database\Factories\Inventarios;

use App\Models\Configuracion\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventarios\InvAlmacen>
 */
class InvAlmacenFactory extends Factory
{
    protected $model = \App\Models\Inventarios\InvAlmacen::class;

    public function definition(): array
    {
        $nombres = [
            'Bodega Principal', 'Almacén Central', 'Bodega Secundaria',
            'Depósito Norte', 'Almacén Uniformes', 'Bodega Útiles',
        ];

        return [
            'nombre'      => $this->faker->unique()->randomElement($nombres) . ' ' . $this->faker->word(),
            'descripcion' => $this->faker->optional()->sentence(),
            'sede_id'     => Sede::factory(),
            'status'      => 1,
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
}
