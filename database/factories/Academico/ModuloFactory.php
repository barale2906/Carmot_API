<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Modulo>
 */
class ModuloFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'    => fake()->words(2, true), // Mejor para nombres de módulos
            'duracion'  => fake()->randomElement([10, 15, 20, 25, 30]),
            'status'    => fake()->randomElement([0, 1]),
        ];
    }

    /**
     * Configura el estado después de crear el módulo para asociar cursos.
     */
    public function configure()
    {
        return $this->afterCreating(function ($modulo) {
            // Asociar entre 1 y 3 cursos aleatorios al módulo
            $cursos = Curso::inRandomOrder()->take(fake()->numberBetween(1, 3))->get();
            $modulo->cursos()->attach($cursos);
        });
    }

    /**
     * Estado para crear un módulo con cursos específicos.
     */
    public function withCursos(array $cursoIds = [])
    {
        return $this->afterCreating(function ($modulo) use ($cursoIds) {
            if (!empty($cursoIds)) {
                $modulo->cursos()->attach($cursoIds);
            }
        });
    }

    /**
     * Estado para crear un módulo sin cursos.
     */
    public function withoutCursos()
    {
        return $this->afterCreating(function ($modulo) {
            // No hacer nada, el módulo se crea sin cursos
        });
    }
}
