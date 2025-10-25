<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Modulo;
use App\Models\Configuracion\Sede;
use App\Models\Configuracion\Area;
use App\Models\Configuracion\Horario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Grupo>
 */
class GrupoFactoryDebug extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sede_id' => Sede::inRandomOrder()->first()?->id ?? Sede::factory(),
            'modulo_id' => Modulo::inRandomOrder()->first()?->id ?? Modulo::factory(),
            'profesor_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'nombre' => 'Grupo Debug ' . $this->faker->unique()->numberBetween(1, 1000),
            'inscritos' => $this->faker->numberBetween(5, 30),
            'jornada' => $this->faker->numberBetween(0, 3),
            'status' => 1,
        ];
    }

    /**
     * Estado para grupo con horarios (versión simplificada para debug).
     */
    public function conHorarios(): static
    {
        return $this->afterCreating(function ($grupo) {
            try {
                // Obtener o crear un área
                $area = Area::first();
                if (!$area) {
                    $area = Area::factory()->create();
                }

                // Crear un horario simple
                Horario::create([
                    'sede_id' => $grupo->sede_id,
                    'area_id' => $area->id,
                    'grupo_id' => $grupo->id,
                    'grupo_nombre' => $grupo->nombre,
                    'tipo' => false,
                    'periodo' => true,
                    'dia' => 'lunes',
                    'hora' => '08:00',
                    'duracion_horas' => 2,
                    'status' => 1,
                ]);

                \Log::info("Horario creado para grupo: {$grupo->nombre}");
            } catch (\Exception $e) {
                \Log::error("Error creando horario para grupo {$grupo->nombre}: " . $e->getMessage());
                throw $e;
            }
        });
    }
}
