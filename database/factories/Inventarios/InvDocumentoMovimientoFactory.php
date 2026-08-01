<?php

namespace Database\Factories\Inventarios;

use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvDocumentoMovimiento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventarios\InvDocumentoMovimiento>
 */
class InvDocumentoMovimientoFactory extends Factory
{
    protected $model = InvDocumentoMovimiento::class;

    public function definition(): array
    {
        $tipo = $this->faker->randomElement(['entrada', 'ajuste']);

        return [
            'numero_documento'   => InvDocumentoMovimiento::generarNumero($tipo),
            'tipo_documento'     => $tipo,
            'almacen_id'         => InvAlmacen::factory(),
            'almacen_destino_id' => null,
            'proveedor_id'       => null,
            'pedido_id'          => null,
            'motivo'             => $this->faker->optional()->sentence(),
            'status'             => 'confirmado',
            'motivo_anulacion'   => null,
            'user_id'            => User::factory(),
        ];
    }

    public function entrada(): static
    {
        return $this->state([
            'tipo_documento'   => 'entrada',
            'numero_documento' => InvDocumentoMovimiento::generarNumero('entrada'),
        ]);
    }

    public function anulado(): static
    {
        return $this->state([
            'status'           => 'anulado',
            'motivo_anulacion' => 'Documento anulado para pruebas.',
        ]);
    }
}
