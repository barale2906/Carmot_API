<?php

namespace Database\Factories\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdImpuesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\LibroDiario\LdImpuesto>
 */
class LdImpuestoFactory extends Factory
{
    protected $model = LdImpuesto::class;

    public function definition(): array
    {
        return [
            'nombre' => 'IVA '.fake()->unique()->numberBetween(1, 9999),
            'porcentaje' => 19,
            'efecto' => LdImpuesto::EFECTO_SUMA,
            'status' => 1,
        ];
    }

    public function retencion(float $porcentaje = 4): static
    {
        return $this->state(fn () => [
            'nombre' => 'Retención '.fake()->unique()->numberBetween(1, 9999),
            'porcentaje' => $porcentaje,
            'efecto' => LdImpuesto::EFECTO_RESTA,
        ]);
    }
}
