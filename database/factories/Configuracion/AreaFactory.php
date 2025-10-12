<?php

namespace Database\Factories\Configuracion;

use App\Models\Configuracion\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Configuracion\Area>
 */
class AreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company() . ' - Area',
            'status' => $this->faker->randomElement([0, 1]), // 0 = Inactivo, 1 = Activo
        ];
    }

    /**
     * Configura el estado del modelo después de la creación.
     * Crea relaciones con sedes automáticamente.
     *
     * @return static
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($area) {
            // Obtener sedes existentes
            $sedes = Sede::all();

            // Si no hay sedes, crear algunas
            if ($sedes->isEmpty()) {
                $sedes = Sede::factory(3)->create();
            }

            // Asignar entre 1 y 3 sedes aleatorias a esta área
            $randomSedes = $sedes->random(rand(1, min(3, $sedes->count())));
            $area->sedes()->attach($randomSedes->pluck('id')->toArray());
        });
    }

    /**
     * Crea un área con sedes específicas.
     *
     * @param array $sedeIds
     * @return static
     */
    public function withSedes(array $sedeIds): static
    {
        return $this->afterCreating(function ($area) use ($sedeIds) {
            $area->sedes()->attach($sedeIds);
        });
    }

    /**
     * Crea un área sin sedes.
     *
     * @return static
     */
    public function withoutSedes(): static
    {
        return $this->afterCreating(function ($area) {
            // No asignar sedes
        });
    }

    /**
     * Crea un área activa.
     *
     * @return static
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 1,
            ];
        });
    }

    /**
     * Crea un área inactiva.
     *
     * @return static
     */
    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 0,
            ];
        });
    }
}
