<?php

namespace Database\Factories\Financiero\LibroDiario;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Turno de caja abierto hoy por defecto.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\LibroDiario\LdTurnoCaja>
 */
class LdTurnoCajaFactory extends Factory
{
    protected $model = LdTurnoCaja::class;

    public function definition(): array
    {
        return [
            'sede_id' => Sede::factory(),
            'cajero_id' => User::factory(),
            'fecha' => today(),
            'base_inicial' => 100000,
            'abierto_at' => now(),
            'status' => LdTurnoCaja::STATUS_ABIERTO,
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn () => [
            'status' => LdTurnoCaja::STATUS_CERRADO,
            'cerrado_at' => now(),
            'efectivo_esperado' => 100000,
            'efectivo_contado' => 100000,
            'diferencia' => 0,
            'resumen' => [],
        ]);
    }
}
