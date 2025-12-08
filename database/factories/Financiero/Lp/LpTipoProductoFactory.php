<?php

namespace Database\Factories\Financiero\Lp;

use App\Models\Financiero\Lp\LpTipoProducto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear tipos de productos de listas de precios.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\Lp\LpTipoProducto>
 */
class LpTipoProductoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LpTipoProducto::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipos = [
            ['nombre' => 'Curso', 'codigo' => 'curso', 'es_financiable' => true],
            ['nombre' => 'Módulo', 'codigo' => 'modulo', 'es_financiable' => true],
            ['nombre' => 'Complementario', 'codigo' => 'complementario', 'es_financiable' => false],
        ];

        $tipo = fake()->randomElement($tipos);

        return [
            'nombre' => $tipo['nombre'],
            'codigo' => $tipo['codigo'] . '-' . fake()->unique()->numberBetween(1000, 9999),
            'es_financiable' => $tipo['es_financiable'],
            'descripcion' => fake()->optional(0.7)->sentence(),
            'status' => fake()->randomElement([0, 1]), // 0: Inactivo, 1: Activo
        ];
    }

    /**
     * Estado para crear un tipo de producto activo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function activo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 1,
            ];
        });
    }

    /**
     * Estado para crear un tipo de producto inactivo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function inactivo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 0,
            ];
        });
    }

    /**
     * Estado para crear un tipo de producto financiable.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function financiable(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'es_financiable' => true,
            ];
        });
    }

    /**
     * Estado para crear un tipo de producto no financiable.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function noFinanciable(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'es_financiable' => false,
            ];
        });
    }

    /**
     * Estado para crear un tipo de producto "Curso".
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function curso(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'nombre' => 'Curso',
                'codigo' => 'curso',
                'es_financiable' => true,
            ];
        });
    }

    /**
     * Estado para crear un tipo de producto "Módulo".
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function modulo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'nombre' => 'Módulo',
                'codigo' => 'modulo',
                'es_financiable' => true,
            ];
        });
    }

    /**
     * Estado para crear un tipo de producto "Complementario".
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function complementario(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'nombre' => 'Complementario',
                'codigo' => 'complementario',
                'es_financiable' => false,
            ];
        });
    }
}




