<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use App\Models\Configuracion\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear ciclos académicos con relaciones.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Ciclo>
 */
class CicloFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Nombres de ciclos académicos realistas
        $nombresCiclos = [
            'Ciclo I 2024', 'Ciclo II 2024', 'Ciclo III 2024',
            'Ciclo I 2025', 'Ciclo II 2025', 'Ciclo III 2025',
            'Ciclo Intensivo 2024', 'Ciclo Especial 2024',
            'Ciclo de Verano 2024', 'Ciclo de Invierno 2024',
            'Ciclo Regular 2024', 'Ciclo Extraordinario 2024',
            'Ciclo Básico 2024', 'Ciclo Avanzado 2024',
            'Ciclo de Actualización 2024', 'Ciclo de Especialización 2024'
        ];

        // Descripciones de ciclos académicos
        $descripcionesCiclos = [
            'Ciclo académico regular del año',
            'Ciclo intensivo de formación',
            'Ciclo especial de actualización',
            'Ciclo de verano para estudiantes',
            'Ciclo de invierno intensivo',
            'Ciclo extraordinario de recuperación',
            'Ciclo básico de fundamentos',
            'Ciclo avanzado de especialización',
            'Ciclo de actualización profesional',
            'Ciclo de especialización técnica'
        ];

        return [
            'sede_id' => Sede::inRandomOrder()->first()?->id ?? Sede::factory(),
            'curso_id' => Curso::inRandomOrder()->first()?->id ?? Curso::factory(),
            'nombre' => $this->faker->randomElement($nombresCiclos),
            'descripcion' => $this->faker->randomElement($descripcionesCiclos),
            'status' => $this->faker->randomElement([0, 1]), // 0 inactivo, 1 Activo
        ];
    }

    /**
     * Estado para ciclo activo.
     */
    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
        ]);
    }

    /**
     * Estado para ciclo inactivo.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }

    /**
     * Estado para ciclo con descripción específica.
     */
    public function conDescripcion(string $descripcion): static
    {
        return $this->state(fn (array $attributes) => [
            'descripcion' => $descripcion,
        ]);
    }

    /**
     * Estado para ciclo sin descripción.
     */
    public function sinDescripcion(): static
    {
        return $this->state(fn (array $attributes) => [
            'descripcion' => null,
        ]);
    }

    /**
     * Estado para ciclo de un año específico.
     */
    public function delAno(int $ano): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => $this->faker->randomElement([
                "Ciclo I {$ano}",
                "Ciclo II {$ano}",
                "Ciclo III {$ano}",
                "Ciclo Intensivo {$ano}",
                "Ciclo Especial {$ano}"
            ]),
        ]);
    }

    /**
     * Estado para ciclo de verano.
     */
    public function deVerano(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Ciclo de Verano ' . now()->year,
            'descripcion' => 'Ciclo académico de verano para estudiantes',
        ]);
    }

    /**
     * Estado para ciclo de invierno.
     */
    public function deInvierno(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Ciclo de Invierno ' . now()->year,
            'descripcion' => 'Ciclo académico de invierno intensivo',
        ]);
    }

    /**
     * Estado para ciclo intensivo.
     */
    public function intensivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Ciclo Intensivo ' . now()->year,
            'descripcion' => 'Ciclo académico intensivo de formación acelerada',
        ]);
    }

    /**
     * Estado para ciclo especial.
     */
    public function especial(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Ciclo Especial ' . now()->year,
            'descripcion' => 'Ciclo académico especial de actualización',
        ]);
    }

    /**
     * Estado para ciclo con sede específica.
     */
    public function enSede(int $sedeId): static
    {
        return $this->state(fn (array $attributes) => [
            'sede_id' => $sedeId,
        ]);
    }

    /**
     * Estado para ciclo con curso específico.
     */
    public function deCurso(int $cursoId): static
    {
        return $this->state(fn (array $attributes) => [
            'curso_id' => $cursoId,
        ]);
    }

    /**
     * Estado para ciclo con grupos específicos.
     */
    public function conGrupos(array $gruposIds): static
    {
        return $this->afterCreating(function ($ciclo) use ($gruposIds) {
            $ciclo->grupos()->attach($gruposIds);
        });
    }

    /**
     * Estado para ciclo con grupos aleatorios de la sede.
     */
    public function conGruposAleatorios(int $cantidad = null): static
    {
        return $this->afterCreating(function ($ciclo) use ($cantidad) {
            // Obtener grupos de la sede del ciclo
            $gruposDisponibles = Grupo::where('sede_id', $ciclo->sede_id)->pluck('id');

            if ($gruposDisponibles->isNotEmpty()) {
                $cantidadGrupos = $cantidad ?? $this->faker->numberBetween(1, min(3, $gruposDisponibles->count()));
                $gruposSeleccionados = $gruposDisponibles->random($cantidadGrupos);
                $ciclo->grupos()->attach($gruposSeleccionados);
            }
        });
    }

    /**
     * Estado para ciclo con muchos grupos.
     */
    public function conMuchosGrupos(): static
    {
        return $this->afterCreating(function ($ciclo) {
            $gruposDisponibles = Grupo::where('sede_id', $ciclo->sede_id)->pluck('id');

            if ($gruposDisponibles->isNotEmpty()) {
                $cantidad = min(5, $gruposDisponibles->count());
                $gruposSeleccionados = $gruposDisponibles->random($cantidad);
                $ciclo->grupos()->attach($gruposSeleccionados);
            }
        });
    }

    /**
     * Estado para ciclo con pocos grupos.
     */
    public function conPocosGrupos(): static
    {
        return $this->afterCreating(function ($ciclo) {
            $gruposDisponibles = Grupo::where('sede_id', $ciclo->sede_id)->pluck('id');

            if ($gruposDisponibles->isNotEmpty()) {
                $cantidad = min(2, $gruposDisponibles->count());
                $gruposSeleccionados = $gruposDisponibles->random($cantidad);
                $ciclo->grupos()->attach($gruposSeleccionados);
            }
        });
    }

    /**
     * Estado para ciclo sin grupos.
     */
    public function sinGrupos(): static
    {
        return $this->afterCreating(function ($ciclo) {
            // No asignar ningún grupo
        });
    }

    /**
     * Estado para ciclo con grupos activos de la sede.
     */
    public function conGruposActivos(): static
    {
        return $this->afterCreating(function ($ciclo) {
            $gruposActivos = Grupo::where('sede_id', $ciclo->sede_id)
                ->where('status', 1)
                ->pluck('id');

            if ($gruposActivos->isNotEmpty()) {
                $cantidad = min(3, $gruposActivos->count());
                $gruposSeleccionados = $gruposActivos->random($cantidad);
                $ciclo->grupos()->attach($gruposSeleccionados);
            }
        });
    }

    /**
     * Estado para ciclo con grupos de jornada específica.
     */
    public function conGruposJornada(int $jornada): static
    {
        return $this->afterCreating(function ($ciclo) use ($jornada) {
            $gruposJornada = Grupo::where('sede_id', $ciclo->sede_id)
                ->where('jornada', $jornada)
                ->pluck('id');

            if ($gruposJornada->isNotEmpty()) {
                $cantidad = min(3, $gruposJornada->count());
                $gruposSeleccionados = $gruposJornada->random($cantidad);
                $ciclo->grupos()->attach($gruposSeleccionados);
            }
        });
    }

    /**
     * Estado para ciclo con grupos de mañana.
     */
    public function conGruposManana(): static
    {
        return $this->conGruposJornada(0);
    }

    /**
     * Estado para ciclo con grupos de tarde.
     */
    public function conGruposTarde(): static
    {
        return $this->conGruposJornada(1);
    }

    /**
     * Estado para ciclo con grupos de noche.
     */
    public function conGruposNoche(): static
    {
        return $this->conGruposJornada(2);
    }

    /**
     * Estado para ciclo con grupos de fin de semana.
     */
    public function conGruposFinDeSemana(): static
    {
        return $this->conGruposJornada(3);
    }

    /**
     * Configurar el factory para usar relaciones existentes.
     */
    public function configure()
    {
        return $this->afterMaking(function ($ciclo) {
            // Solo crear nuevas relaciones si no existen datos en la BD
            if (!$ciclo->sede_id && Sede::count() === 0) {
                $ciclo->sede_id = Sede::factory();
            }
            if (!$ciclo->curso_id && Curso::count() === 0) {
                $ciclo->curso_id = Curso::factory();
            }
        });
    }
}
