<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Modulo;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Grupo>
 */
class GrupoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Nombres de grupos académicos realistas
        $nombresGrupos = [
            'Grupo A', 'Grupo B', 'Grupo C', 'Grupo D',
            'Matemáticas I', 'Matemáticas II', 'Matemáticas III',
            'Física I', 'Física II', 'Química I', 'Química II',
            'Programación I', 'Programación II', 'Programación III',
            'Base de Datos I', 'Base de Datos II',
            'Inglés Básico', 'Inglés Intermedio', 'Inglés Avanzado',
            'Grupo Mañana', 'Grupo Tarde', 'Grupo Noche',
            'Grupo Fin de Semana', 'Grupo Intensivo'
        ];

        return [
            'sede_id' => Sede::inRandomOrder()->first()?->id ?? Sede::factory(),
            'modulo_id' => Modulo::inRandomOrder()->first()?->id ?? Modulo::factory(),
            'profesor_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'nombre' => $this->faker->randomElement($nombresGrupos),
            'inscritos' => $this->faker->numberBetween(5, 30),
            'jornada' => $this->faker->numberBetween(0, 3), // 0 Mañana, 1 Tarde, 2 Noche, 3 Fin de semana
            'status' => $this->faker->randomElement([0, 1]), // 0 inactivo, 1 Activo
        ];
    }

    /**
     * Estado para grupo activo.
     */
    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
        ]);
    }

    /**
     * Estado para grupo inactivo.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }

    /**
     * Estado para grupo de mañana.
     */
    public function manana(): static
    {
        return $this->state(fn (array $attributes) => [
            'jornada' => 0,
        ]);
    }

    /**
     * Estado para grupo de tarde.
     */
    public function tarde(): static
    {
        return $this->state(fn (array $attributes) => [
            'jornada' => 1,
        ]);
    }

    /**
     * Estado para grupo de noche.
     */
    public function noche(): static
    {
        return $this->state(fn (array $attributes) => [
            'jornada' => 2,
        ]);
    }

    /**
     * Estado para grupo de fin de semana.
     */
    public function finDeSemana(): static
    {
        return $this->state(fn (array $attributes) => [
            'jornada' => 3,
        ]);
    }

    /**
     * Estado para grupo con pocos inscritos.
     */
    public function pocosInscritos(): static
    {
        return $this->state(fn (array $attributes) => [
            'inscritos' => $this->faker->numberBetween(5, 10),
        ]);
    }

    /**
     * Estado para grupo con muchos inscritos.
     */
    public function muchosInscritos(): static
    {
        return $this->state(fn (array $attributes) => [
            'inscritos' => $this->faker->numberBetween(20, 30),
        ]);
    }

    /**
     * Configurar el factory para usar relaciones existentes.
     */
    public function configure()
    {
        return $this->afterMaking(function ($grupo) {
            // Solo crear nuevas relaciones si no existen datos en la BD
            if (!$grupo->sede_id && Sede::count() === 0) {
                $grupo->sede_id = Sede::factory();
            }
            if (!$grupo->modulo_id && Modulo::count() === 0) {
                $grupo->modulo_id = Modulo::factory();
            }
            if (!$grupo->profesor_id && User::count() === 0) {
                $grupo->profesor_id = User::factory();
            }
        });
    }
}
