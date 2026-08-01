<?php

namespace Database\Factories\Inventarios;

use App\Models\Configuracion\Sede;
use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvPedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para InvPedido (cabecera de pedido de inventario).
 *
 * @extends Factory<InvPedido>
 */
class InvPedidoFactory extends Factory
{
    protected $model = InvPedido::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $valorTotal = $this->faker->randomFloat(2, 10000, 500000);

        return [
            'estudiante_id'   => User::factory(),
            'sede_id'         => Sede::factory(),
            'almacen_id'      => InvAlmacen::factory(),
            'cajero_id'       => User::factory(),
            'valor_total'     => $valorTotal,
            'abono_acumulado' => 0,
            'saldo'           => $valorTotal,
            'status'          => InvPedido::STATUS_ACTIVO,
            'observaciones'   => null,
        ];
    }

    /**
     * Pedido completamente pagado.
     *
     * @return static
     */
    public function pagado(): static
    {
        return $this->state(fn (array $attrs) => [
            'abono_acumulado' => $attrs['valor_total'],
            'saldo'           => 0,
            'status'          => InvPedido::STATUS_PAGADO,
        ]);
    }

    /**
     * Pedido en proceso de entrega.
     *
     * @return static
     */
    public function entregando(): static
    {
        return $this->state(fn (array $attrs) => [
            'abono_acumulado' => $attrs['valor_total'],
            'saldo'           => 0,
            'status'          => InvPedido::STATUS_ENTREGANDO,
        ]);
    }

    /**
     * Pedido totalmente entregado.
     *
     * @return static
     */
    public function entregado(): static
    {
        return $this->state(fn (array $attrs) => [
            'abono_acumulado' => $attrs['valor_total'],
            'saldo'           => 0,
            'status'          => InvPedido::STATUS_ENTREGADO,
        ]);
    }

    /**
     * Pedido cancelado.
     *
     * @return static
     */
    public function cancelado(): static
    {
        return $this->state(fn () => [
            'status' => InvPedido::STATUS_CANCELADO,
        ]);
    }
}
