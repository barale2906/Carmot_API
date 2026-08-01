<?php

namespace Database\Factories\Inventarios;

use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvOrdenCompra;
use App\Models\Inventarios\InvProveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para InvOrdenCompra.
 *
 * @extends Factory<InvOrdenCompra>
 */
class InvOrdenCompraFactory extends Factory
{
    protected $model = InvOrdenCompra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proveedor_id'   => InvProveedor::factory(),
            'almacen_id'     => InvAlmacen::factory(),
            'responsable_id' => User::factory(),
            'status'         => InvOrdenCompra::STATUS_BORRADOR,
            'subtotal'       => 0,
            'total'          => 0,
            'observaciones'  => null,
            'fecha_esperada' => $this->faker->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
        ];
    }

    /**
     * Orden enviada al proveedor.
     *
     * @return static
     */
    public function enviada(): static
    {
        return $this->state(['status' => InvOrdenCompra::STATUS_ENVIADA]);
    }

    /**
     * Orden parcialmente recibida.
     *
     * @return static
     */
    public function recibidaParcial(): static
    {
        return $this->state(['status' => InvOrdenCompra::STATUS_RECIBIDA_PARCIAL]);
    }

    /**
     * Orden completamente recibida.
     *
     * @return static
     */
    public function recibida(): static
    {
        return $this->state(['status' => InvOrdenCompra::STATUS_RECIBIDA]);
    }

    /**
     * Orden cancelada.
     *
     * @return static
     */
    public function cancelada(): static
    {
        return $this->state(['status' => InvOrdenCompra::STATUS_CANCELADA]);
    }
}
