<?php

namespace Database\Factories\Academico;

use App\Models\User;
use App\Models\Academico\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear cursos con estudiantes asociados.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Curso>
 */
class CursoFactory extends Factory
{
    /**
     * El nombre del modelo correspondiente al factory.
     *
     * @var string
     */
    protected $model = Curso::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'    => fake()->randomElement(['Soldadura','Motos','Carros']),
            'duracion'  => fake()->randomNumber([100,150,200,250,300]),
            'tipo'      => fake()->randomElement([0,1]),
            'status'    => fake()->randomElement([0,1]),
        ];
    }

    /**
     * Configura el factory del modelo.
     * Por defecto, cada curso creado tendrá entre 3 y 8 estudiantes aleatorios.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function configure()
    {
        return $this->afterCreating(function (Curso $curso) {
            // Crear entre 3 y 8 estudiantes aleatorios para cada curso
            $cantidadEstudiantes = fake()->numberBetween(3, 8);
            $estudiantes = User::factory($cantidadEstudiantes)->create();
            $curso->estudiantes()->attach($estudiantes->pluck('id'));
        });
    }

    /**
     * Crear un curso con estudiantes específicos.
     * Si no se especifica cantidad, se crean entre 3 y 8 estudiantes aleatorios.
     *
     * @param int|null $cantidad Número de estudiantes a crear
     * @return static
     */
    public function conEstudiantes(int $cantidad = null): static
    {
        return $this->afterCreating(function (Curso $curso) use ($cantidad) {
            $cantidadEstudiantes = $cantidad ?? fake()->numberBetween(3, 8);
            $estudiantes = User::factory($cantidadEstudiantes)->create();
            $curso->estudiantes()->attach($estudiantes->pluck('id'));
        });
    }

    /**
     * Crear un curso sin estudiantes asociados.
     *
     * @return static
     */
    public function sinEstudiantes(): static
    {
        return $this->afterCreating(function (Curso $curso) {
            // No hacer nada, el curso se crea sin estudiantes
        });
    }

    /**
     * Crear un curso asociando estudiantes existentes por sus IDs.
     *
     * @param array $userIds Array de IDs de usuarios existentes
     * @return static
     */
    public function conEstudiantesExistentes(array $userIds): static
    {
        return $this->afterCreating(function (Curso $curso) use ($userIds) {
            $curso->estudiantes()->attach($userIds);
        });
    }

    /**
     * Crear un curso con un número específico de estudiantes nuevos.
     *
     * @param int $cantidad Número exacto de estudiantes a crear
     * @return static
     */
    public function conNumeroEstudiantes(int $cantidad): static
    {
        return $this->afterCreating(function (Curso $curso) use ($cantidad) {
            $estudiantes = User::factory($cantidad)->create();
            $curso->estudiantes()->attach($estudiantes->pluck('id'));
        });
    }
}
