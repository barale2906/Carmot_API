<?php

namespace Database\Factories\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdTercero;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Empresa activa identificada con NIT.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\LibroDiario\LdTercero>
 */
class LdTerceroFactory extends Factory
{
    protected $model = LdTercero::class;

    public function definition(): array
    {
        return [
            'tipo_identificacion' => 'NIT',
            'identificacion' => fake()->unique()->numerify('9########'),
            'nombre' => fake()->company(),
            'status' => 1,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['status' => 0]);
    }
}
