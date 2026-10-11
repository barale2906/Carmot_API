<?php

namespace Database\Factories\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\LibroDiario\LdTipoMovimiento>
 */
class LdTipoMovimientoFactory extends Factory
{
    protected $model = LdTipoMovimiento::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(3, true),
            'clase' => LdTipoMovimiento::CLASE_EGRESO,
            'status' => 1,
        ];
    }

    public function ingreso(): static
    {
        return $this->state(fn () => ['clase' => LdTipoMovimiento::CLASE_INGRESO]);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['status' => 0]);
    }
}
