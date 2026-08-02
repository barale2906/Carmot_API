<?php

namespace Database\Factories\Inventarios;

use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvProducto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventarios\InvStock>
 */
class InvStockFactory extends Factory
{
    protected $model = \App\Models\Inventarios\InvStock::class;

    public function definition(): array
    {
        $total     = $this->faker->numberBetween(1, 100);
        $reservada = $this->faker->numberBetween(0, (int) ($total / 2));

        return [
            'almacen_id'          => InvAlmacen::factory(),
            'producto_id'         => InvProducto::factory()->activo(),
            'cantidad_total'      => $total,
            'cantidad_reservada'  => $reservada,
            'cantidad_disponible' => $total - $reservada,
            'ultimo_movimiento_at' => now(),
        ];
    }
}
