<?php

namespace Database\Factories\Financiero\ConceptoPago;

use App\Models\Financiero\ConceptoPago\ConceptoPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear conceptos de pago.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\ConceptoPago\ConceptoPago>
 */
class ConceptoPagoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ConceptoPago::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tiposDisponibles = ConceptoPago::getTiposDisponibles();
        $indicesDisponibles = array_keys($tiposDisponibles);

        return [
            'nombre' => fake()->randomElement([
                'Matrícula',
                'Pago de mensualidad',
                'Recargo por pago con tarjeta',
                'Cobro adicional por material',
                'Pago por acuerdo de pago',
                'Recargo por mora',
                'Pago de certificado',
                'Cobro por reposición de clase',
                'Pago de uniforme',
                'Cobro por material didáctico',
            ]),
            'tipo' => fake()->randomElement($indicesDisponibles), // Índice numérico
            'valor' => fake()->randomFloat(2, 10000, 5000000), // Valores entre 10,000 y 5,000,000
        ];
    }

    /**
     * Estado para crear un concepto de pago con tipo "Cartera" (índice 0).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function tipoCartera(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'tipo' => 0, // Índice de Cartera
            ];
        });
    }

    /**
     * Estado para crear un concepto de pago con tipo "Financiero" (índice 1).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function tipoFinanciero(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'tipo' => 1, // Índice de Financiero
            ];
        });
    }

    /**
     * Estado para crear un concepto de pago con tipo "Inventario" (índice 2).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function tipoInventario(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'tipo' => 2, // Índice de Inventario
            ];
        });
    }

    /**
     * Estado para crear un concepto de pago con tipo "Otro" (índice 3).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function tipoOtro(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'tipo' => 3, // Índice de Otro
            ];
        });
    }

    /**
     * Estado para crear un concepto de pago con un valor específico.
     *
     * @param float $valor Valor del concepto de pago
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conValor(float $valor): static
    {
        return $this->state(function (array $attributes) use ($valor) {
            return [
                'valor' => $valor,
            ];
        });
    }
}

