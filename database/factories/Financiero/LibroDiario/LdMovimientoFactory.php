<?php

namespace Database\Factories\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Egreso en efectivo sin impuestos; sede y usuario se toman del turno.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\LibroDiario\LdMovimiento>
 */
class LdMovimientoFactory extends Factory
{
    protected $model = LdMovimiento::class;

    public function definition(): array
    {
        return [
            'turno_caja_id' => LdTurnoCaja::factory(),
            'sede_id' => fn (array $a) => LdTurnoCaja::find($a['turno_caja_id'])->sede_id,
            'usuario_id' => fn (array $a) => LdTurnoCaja::find($a['turno_caja_id'])->cajero_id,
            'tipo_movimiento_id' => LdTipoMovimiento::factory(),
            'clase' => fn (array $a) => LdTipoMovimiento::find($a['tipo_movimiento_id'])->clase,
            'fecha' => today(),
            'tercero_tipo_identificacion' => 'NIT',
            'tercero_identificacion' => fake()->numerify('#########'),
            'tercero_nombre' => fake()->company(),
            'documento_tipo' => 'factura',
            'documento_numero' => fake()->bothify('FE-####'),
            'subtotal' => 50000,
            'total_impuestos' => 0,
            'total_retenciones' => 0,
            'valor_total' => 50000,
            'medio_pago' => 'efectivo',
            'status' => LdMovimiento::STATUS_REGISTRADO,
        ];
    }
}
