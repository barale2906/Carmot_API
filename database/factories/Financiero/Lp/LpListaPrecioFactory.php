<?php

namespace Database\Factories\Financiero\Lp;

use App\Models\Configuracion\Poblacion;
use App\Models\Financiero\Lp\LpListaPrecio;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear listas de precios.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\Lp\LpListaPrecio>
 */
class LpListaPrecioFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LpListaPrecio::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generar fechas de vigencia (rango de 1 año)
        $fechaInicio = fake()->dateTimeBetween('now', '+6 months');
        $fechaFin = fake()->dateTimeBetween($fechaInicio, '+12 months');

        $nombresListas = [
            'Lista de Precios 2024',
            'Lista de Precios 2025',
            'Lista Nacional 2024',
            'Lista Regional 2024',
            'Lista de Verano 2024',
            'Lista de Invierno 2024',
            'Lista Especial 2024',
            'Lista Promocional 2024',
        ];

        return [
            'nombre' => fake()->randomElement($nombresListas),
            'codigo' => fake()->unique()->regexify('LP-[A-Z]{3}-[0-9]{4}'),
            'fecha_inicio' => Carbon::parse($fechaInicio)->format('Y-m-d'),
            'fecha_fin' => Carbon::parse($fechaFin)->format('Y-m-d'),
            'descripcion' => fake()->optional(0.7)->paragraph(2),
            'status' => fake()->randomElement([0, 1, 2, 3]), // 0: Inactiva, 1: En Proceso, 2: Aprobada, 3: Activa
        ];
    }

    /**
     * Estado para crear una lista inactiva.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function inactiva(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => LpListaPrecio::STATUS_INACTIVA,
            ];
        });
    }

    /**
     * Estado para crear una lista en proceso.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function enProceso(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => LpListaPrecio::STATUS_EN_PROCESO,
            ];
        });
    }

    /**
     * Estado para crear una lista aprobada.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function aprobada(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => LpListaPrecio::STATUS_APROBADA,
            ];
        });
    }

    /**
     * Estado para crear una lista activa.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function activa(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => LpListaPrecio::STATUS_ACTIVA,
            ];
        });
    }

    /**
     * Estado para crear una lista vigente (activa y con fechas actuales).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function vigente(): static
    {
        return $this->state(function (array $attributes) {
            $fechaInicio = fake()->dateTimeBetween('-1 month', 'now');
            $fechaFin = fake()->dateTimeBetween('now', '+11 months');

            return [
                'fecha_inicio' => Carbon::parse($fechaInicio)->format('Y-m-d'),
                'fecha_fin' => Carbon::parse($fechaFin)->format('Y-m-d'),
                'status' => LpListaPrecio::STATUS_ACTIVA,
            ];
        });
    }

    /**
     * Estado para crear una lista futura (fechas futuras).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function futura(): static
    {
        return $this->state(function (array $attributes) {
            $fechaInicio = fake()->dateTimeBetween('+1 month', '+6 months');
            $fechaFin = fake()->dateTimeBetween($fechaInicio, '+12 months');

            return [
                'fecha_inicio' => Carbon::parse($fechaInicio)->format('Y-m-d'),
                'fecha_fin' => Carbon::parse($fechaFin)->format('Y-m-d'),
            ];
        });
    }

    /**
     * Estado para crear una lista vencida (fechas pasadas).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function vencida(): static
    {
        return $this->state(function (array $attributes) {
            $fechaFin = fake()->dateTimeBetween('-6 months', '-1 day');
            $fechaInicio = fake()->dateTimeBetween('-12 months', $fechaFin);

            return [
                'fecha_inicio' => Carbon::parse($fechaInicio)->format('Y-m-d'),
                'fecha_fin' => Carbon::parse($fechaFin)->format('Y-m-d'),
            ];
        });
    }

    /**
     * Estado para crear una lista con poblaciones específicas.
     *
     * @param array $poblacionIds IDs de las poblaciones
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conPoblaciones(array $poblacionIds): static
    {
        return $this->afterCreating(function (LpListaPrecio $lista) use ($poblacionIds) {
            $lista->poblaciones()->attach($poblacionIds);
        });
    }

    /**
     * Estado para crear una lista con poblaciones aleatorias.
     *
     * @param int $cantidad Cantidad de poblaciones a asignar
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conPoblacionesAleatorias(int $cantidad = 3): static
    {
        return $this->afterCreating(function (LpListaPrecio $lista) use ($cantidad) {
            $poblaciones = Poblacion::inRandomOrder()->limit($cantidad)->pluck('id')->toArray();
            if (!empty($poblaciones)) {
                $lista->poblaciones()->attach($poblaciones);
            }
        });
    }
}

