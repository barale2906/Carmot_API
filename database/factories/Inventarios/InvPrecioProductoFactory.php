<?php

namespace Database\Factories\Inventarios;

use App\Models\Inventarios\InvPrecioProducto;
use App\Models\Inventarios\InvProducto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para InvPrecioProducto (precio de producto en lista de precios de inventario).
 *
 * @extends Factory<InvPrecioProducto>
 */
class InvPrecioProductoFactory extends Factory
{
    protected $model = InvPrecioProducto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lista_precio_id' => \App\Models\Financiero\Lp\LpListaPrecio::factory(),
            'producto_id'     => InvProducto::factory()->simple(),
            'precio'          => $this->faker->randomFloat(2, 1000, 150000),
            'observaciones'   => null,
        ];
    }
}
