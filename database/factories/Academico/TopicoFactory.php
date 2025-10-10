<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Modulo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Topico>
 */
class TopicoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->sentence(3),
            'descripcion' => fake()->paragraph(3),
            'duracion' => fake()->randomFloat(1, 0.5, 8.0), // Duración entre 0.5 y 8 horas
            'status' => fake()->randomElement([0, 1]),
        ];
    }

    /**
     * Configura el estado después de crear el tópico para asociar módulos.
     */
    public function configure()
    {
        return $this->afterCreating(function ($topico) {
            // Asociar entre 1 y 4 módulos aleatorios al tópico
            $modulos = Modulo::inRandomOrder()->take(fake()->numberBetween(1, 4))->get();
            $topico->modulos()->attach($modulos);
        });
    }

    /**
     * Estado para crear un tópico con módulos específicos.
     */
    public function withModulos(array $moduloIds = [])
    {
        return $this->afterCreating(function ($topico) use ($moduloIds) {
            if (!empty($moduloIds)) {
                $topico->modulos()->attach($moduloIds);
            }
        });
    }

    /**
     * Estado para crear un tópico sin módulos.
     */
    public function withoutModulos()
    {
        return $this->afterCreating(function ($topico) {
            // No hacer nada, el tópico se crea sin módulos
        });
    }
}
