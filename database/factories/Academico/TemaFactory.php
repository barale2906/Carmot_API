<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Topico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para el modelo Tema
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Tema>
 */
class TemaFactory extends Factory
{
    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->sentence(3),
            'descripcion' => fake()->paragraph(3),
            'duracion' => fake()->randomFloat(1, 0.5, 4.0), // Duración entre 0.5 y 4 horas
            'status' => fake()->randomElement([0, 1]),
        ];
    }

    /**
     * Configura el estado después de crear el tema para asociar tópicos.
     */
    public function configure()
    {
        return $this->afterCreating(function ($tema) {
            // Asociar entre 1 y 3 tópicos aleatorios al tema
            $topicos = Topico::inRandomOrder()->take(fake()->numberBetween(1, 3))->get();
            if ($topicos->count() > 0) {
                $tema->topicos()->attach($topicos);
            }
        });
    }

    /**
     * Estado para crear un tema con tópicos específicos.
     *
     * @param array $topicoIds IDs de los tópicos a asociar
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withTopicos(array $topicoIds = [])
    {
        return $this->afterCreating(function ($tema) use ($topicoIds) {
            if (!empty($topicoIds)) {
                $tema->topicos()->attach($topicoIds);
            }
        });
    }

    /**
     * Estado para crear un tema sin tópicos.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withoutTopicos()
    {
        return $this->afterCreating(function ($tema) {
            // No hacer nada, el tema se crea sin tópicos
        });
    }

    /**
     * Estado para crear un tema activo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 1,
            ];
        });
    }

    /**
     * Estado para crear un tema inactivo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 0,
            ];
        });
    }
}
