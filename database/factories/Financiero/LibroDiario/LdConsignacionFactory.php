<?php

namespace Database\Factories\Financiero\LibroDiario;

use App\Models\Configuracion\Banco;
use App\Models\Financiero\LibroDiario\LdConsignacion;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Consignación de efectivo; sede y usuario se toman del turno.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\LibroDiario\LdConsignacion>
 */
class LdConsignacionFactory extends Factory
{
    protected $model = LdConsignacion::class;

    public function definition(): array
    {
        return [
            'turno_caja_id' => LdTurnoCaja::factory(),
            'sede_id' => fn (array $a) => LdTurnoCaja::find($a['turno_caja_id'])->sede_id,
            'usuario_id' => fn (array $a) => LdTurnoCaja::find($a['turno_caja_id'])->cajero_id,
            'fecha' => today(),
            'banco_id' => Banco::factory(),
            'valor' => 30000,
            'titular' => fake()->name(),
            'numero_comprobante' => fake()->numerify('########'),
            'status' => LdConsignacion::STATUS_REGISTRADO,
        ];
    }
}
