<?php

namespace Database\Factories\Configuracion;

use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Configuracion\Horario>
 */
class HorarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dias = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

        return [
            'sede_id' => Sede::factory(),
            'area_id' => Area::factory(),
            'grupo_id' => null,
            'grupo_nombre' => null,
            'tipo' => true, // Siempre true para horarios de sede
            'periodo' => $this->faker->boolean(), // true = inicio, false = fin
            'dia' => $this->faker->randomElement($dias),
            'hora' => $this->faker->time('H:i:s'),
            'status' => 1,
        ];
    }
}
