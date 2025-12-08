<?php

namespace Database\Factories\Financiero\Descuento;

use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Descuento\DescuentoAplicado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory DescuentoAplicadoFactory
 *
 * Genera instancias de prueba del modelo DescuentoAplicado.
 * Útil para crear datos de prueba del historial de descuentos aplicados.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\Descuento\DescuentoAplicado>
 */
class DescuentoAplicadoFactory extends Factory
{
    /**
     * El nombre del modelo asociado a esta factory.
     *
     * @var string
     */
    protected $model = DescuentoAplicado::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $valorOriginal = $this->faker->randomFloat(2, 10000, 500000);
        $valorDescuento = $this->faker->randomFloat(2, 100, min(10000, $valorOriginal * 0.3));
        $valorFinal = max(0, $valorOriginal - $valorDescuento);

        return [
            'descuento_id' => Descuento::factory(),
            'concepto_tipo' => $this->faker->randomElement(['matricula', 'cuota', 'pago_contado']),
            'concepto_id' => $this->faker->numberBetween(1, 1000),
            'valor_original' => $valorOriginal,
            'valor_descuento' => $valorDescuento,
            'valor_final' => $valorFinal,
            'producto_id' => $this->faker->optional()->numberBetween(1, 100),
            'lista_precio_id' => $this->faker->optional()->numberBetween(1, 50),
            'sede_id' => $this->faker->optional()->numberBetween(1, 10),
            'observaciones' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indica que el descuento aplicado es para una matrícula.
     *
     * @return static
     */
    public function matricula(): static
    {
        return $this->state(fn (array $attributes) => [
            'concepto_tipo' => 'matricula',
        ]);
    }

    /**
     * Indica que el descuento aplicado es para una cuota.
     *
     * @return static
     */
    public function cuota(): static
    {
        return $this->state(fn (array $attributes) => [
            'concepto_tipo' => 'cuota',
        ]);
    }

    /**
     * Indica que el descuento aplicado es para un pago de contado.
     *
     * @return static
     */
    public function pagoContado(): static
    {
        return $this->state(fn (array $attributes) => [
            'concepto_tipo' => 'pago_contado',
        ]);
    }
}

