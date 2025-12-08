<?php

namespace Database\Factories\Financiero\Lp;

use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear precios de productos en listas de precios.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\Lp\LpPrecioProducto>
 */
class LpPrecioProductoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LpPrecioProducto::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $listaPrecio = LpListaPrecio::inRandomOrder()->first()
            ?? LpListaPrecio::factory()->enProceso()->create();

        $producto = LpProducto::inRandomOrder()->first()
            ?? LpProducto::factory()->activo()->create();

        // Cargar relación para verificar si es financiable
        $producto->load('tipoProducto');
        $esFinanciable = $producto->esFinanciable();

        // Precio de contado (siempre presente)
        $precioContado = fake()->randomFloat(2, 50000, 5000000);

        // Si es financiable, generar datos de financiación
        $precioTotal = null;
        $matricula = 0;
        $numeroCuotas = null;

        if ($esFinanciable) {
            // Precio total suele ser mayor que el precio de contado (con descuento)
            $precioTotal = fake()->randomFloat(2, $precioContado * 1.1, $precioContado * 1.3);
            // Matrícula entre 10% y 30% del precio total
            $matricula = fake()->randomFloat(2, $precioTotal * 0.1, $precioTotal * 0.3);
            // Número de cuotas entre 6 y 24
            $numeroCuotas = fake()->numberBetween(6, 24);
        }

        return [
            'lista_precio_id' => $listaPrecio->id,
            'producto_id' => $producto->id,
            'precio_contado' => $precioContado,
            'precio_total' => $precioTotal,
            'matricula' => $matricula,
            'numero_cuotas' => $numeroCuotas,
            // valor_cuota se calcula automáticamente en el modelo
            'observaciones' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Estado para crear un precio de producto financiable.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function financiable(): static
    {
        return $this->state(function (array $attributes) {
            $producto = LpProducto::find($attributes['producto_id'] ?? null);

            // Si no hay producto o no es financiable, crear uno financiable
            if (!$producto || !$producto->esFinanciable()) {
                $producto = LpProducto::factory()->activo()->curso()->create();
            }

            $precioContado = fake()->randomFloat(2, 500000, 5000000);
            $precioTotal = fake()->randomFloat(2, $precioContado * 1.1, $precioContado * 1.3);
            $matricula = fake()->randomFloat(2, $precioTotal * 0.1, $precioTotal * 0.3);
            $numeroCuotas = fake()->numberBetween(6, 24);

            return [
                'producto_id' => $producto->id,
                'precio_contado' => $precioContado,
                'precio_total' => $precioTotal,
                'matricula' => $matricula,
                'numero_cuotas' => $numeroCuotas,
            ];
        });
    }

    /**
     * Estado para crear un precio de producto no financiable (solo contado).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function noFinanciable(): static
    {
        return $this->state(function (array $attributes) {
            $producto = LpProducto::find($attributes['producto_id'] ?? null);

            // Si no hay producto o es financiable, crear uno complementario
            if (!$producto || $producto->esFinanciable()) {
                $producto = LpProducto::factory()->activo()->complementario()->create();
            }

            $precioContado = fake()->randomFloat(2, 10000, 200000);

            return [
                'producto_id' => $producto->id,
                'precio_contado' => $precioContado,
                'precio_total' => null,
                'matricula' => 0,
                'numero_cuotas' => null,
            ];
        });
    }

    /**
     * Estado para crear un precio con valores específicos.
     *
     * @param float $precioContado Precio de contado
     * @param float|null $precioTotal Precio total (si es financiable)
     * @param float $matricula Matrícula (si es financiable)
     * @param int|null $numeroCuotas Número de cuotas (si es financiable)
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conValores(
        float $precioContado,
        ?float $precioTotal = null,
        float $matricula = 0,
        ?int $numeroCuotas = null
    ): static {
        return $this->state(function (array $attributes) use ($precioContado, $precioTotal, $matricula, $numeroCuotas) {
            return [
                'precio_contado' => $precioContado,
                'precio_total' => $precioTotal,
                'matricula' => $matricula,
                'numero_cuotas' => $numeroCuotas,
            ];
        });
    }

    /**
     * Estado para crear un precio con matrícula cero.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function sinMatricula(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'matricula' => 0,
            ];
        });
    }

    /**
     * Estado para crear un precio con muchas cuotas.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conMuchasCuotas(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'numero_cuotas' => fake()->numberBetween(18, 36),
            ];
        });
    }

    /**
     * Estado para crear un precio con pocas cuotas.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conPocasCuotas(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'numero_cuotas' => fake()->numberBetween(3, 6),
            ];
        });
    }
}




