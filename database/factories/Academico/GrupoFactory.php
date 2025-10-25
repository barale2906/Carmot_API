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
     * Estado para grupo con horarios.
     */
    public function conHorarios(): static
    {
        return $this->afterCreating(function ($grupo) {
            // Generar 1-4 horarios aleatorios para el grupo
            $cantidadHorarios = $this->faker->numberBetween(1, 4);
            $dias = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
            $horas = ['08:00', '09:00', '10:00', '14:00', '15:00', '16:00', '18:00', '19:00'];
            $duraciones = [1, 2, 3, 4]; // Duración en horas

            // Obtener áreas disponibles o crear una si no existen
            $area = Area::inRandomOrder()->first() ?? Area::factory();

            for ($i = 0; $i < $cantidadHorarios; $i++) {
                Horario::create([
                    'sede_id' => $grupo->sede_id,
                    'area_id' => $area->id,
                    'grupo_id' => $grupo->id,
                    'grupo_nombre' => $grupo->nombre,
                    'tipo' => false, // Horario de grupo
                    'periodo' => true, // Hora de inicio
                    'dia' => $this->faker->randomElement($dias),
                    'hora' => $this->faker->randomElement($horas),
                    'duracion_horas' => $this->faker->randomElement($duraciones),
                    'status' => 1, // Activo
                ]);
            }
        });
    }

    /**
     * Estado para grupo con horarios específicos de mañana.
     */
    public function conHorariosManana(): static
    {
        return $this->afterCreating(function ($grupo) {
            $horasManana = ['08:00', '09:00', '10:00', '11:00'];
            $dias = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes'];

            $area = Area::inRandomOrder()->first() ?? Area::factory();

            // 2-3 horarios de mañana
            $cantidad = $this->faker->numberBetween(2, 3);
            for ($i = 0; $i < $cantidad; $i++) {
                Horario::create([
                    'sede_id' => $grupo->sede_id,
                    'area_id' => $area->id,
                    'grupo_id' => $grupo->id,
                    'grupo_nombre' => $grupo->nombre,
                    'tipo' => false,
                    'periodo' => true,
                    'dia' => $this->faker->randomElement($dias),
                    'hora' => $this->faker->randomElement($horasManana),
                    'duracion_horas' => $this->faker->randomElement([1, 2]),
                    'status' => 1,
                ]);
            }
        });
    }

    /**
     * Estado para grupo con horarios específicos de tarde.
     */
    public function conHorariosTarde(): static
    {
        return $this->afterCreating(function ($grupo) {
            $horasTarde = ['14:00', '15:00', '16:00', '17:00'];
            $dias = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes'];

            $area = Area::inRandomOrder()->first() ?? Area::factory();

            // 2-3 horarios de tarde
            $cantidad = $this->faker->numberBetween(2, 3);
            for ($i = 0; $i < $cantidad; $i++) {
                Horario::create([
                    'sede_id' => $grupo->sede_id,
                    'area_id' => $area->id,
                    'grupo_id' => $grupo->id,
                    'grupo_nombre' => $grupo->nombre,
                    'tipo' => false,
                    'periodo' => true,
                    'dia' => $this->faker->randomElement($dias),
                    'hora' => $this->faker->randomElement($horasTarde),
                    'duracion_horas' => $this->faker->randomElement([1, 2, 3]),
                    'status' => 1,
                ]);
            }
        });
    }

    /**
     * Estado para grupo con horarios específicos de noche.
     */
    public function conHorariosNoche(): static
    {
        return $this->afterCreating(function ($grupo) {
            $horasNoche = ['18:00', '19:00', '20:00'];
            $dias = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes'];

            $area = Area::inRandomOrder()->first() ?? Area::factory();

            // 1-2 horarios de noche
            $cantidad = $this->faker->numberBetween(1, 2);
            for ($i = 0; $i < $cantidad; $i++) {
                Horario::create([
                    'sede_id' => $grupo->sede_id,
                    'area_id' => $area->id,
                    'grupo_id' => $grupo->id,
                    'grupo_nombre' => $grupo->nombre,
                    'tipo' => false,
                    'periodo' => true,
                    'dia' => $this->faker->randomElement($dias),
                    'hora' => $this->faker->randomElement($horasNoche),
                    'duracion_horas' => $this->faker->randomElement([2, 3, 4]), // Clases más largas en la noche
                    'status' => 1,
                ]);
            }
        });
    }

    /**
     * Estado para grupo con horarios de fin de semana.
     */
    public function conHorariosFinSemana(): static
    {
        return $this->afterCreating(function ($grupo) {
            $horasFinSemana = ['08:00', '09:00', '10:00', '14:00', '15:00'];
            $dias = ['sábado', 'domingo'];

            $area = Area::inRandomOrder()->first() ?? Area::factory();

            // 1-2 horarios de fin de semana
            $cantidad = $this->faker->numberBetween(1, 2);
            for ($i = 0; $i < $cantidad; $i++) {
                Horario::create([
                    'sede_id' => $grupo->sede_id,
                    'area_id' => $area->id,
                    'grupo_id' => $grupo->id,
                    'grupo_nombre' => $grupo->nombre,
                    'tipo' => false,
                    'periodo' => true,
                    'dia' => $this->faker->randomElement($dias),
                    'hora' => $this->faker->randomElement($horasFinSemana),
                    'duracion_horas' => $this->faker->randomElement([3, 4, 5]), // Clases intensivas
                    'status' => 1,
                ]);
            }
        });
    }

    /**
     * Estado para grupo con horarios intensivos (múltiples horas).
     */
    public function conHorariosIntensivos(): static
    {
        return $this->afterCreating(function ($grupo) {
            $dias = ['lunes', 'miércoles', 'viernes'];

            $area = Area::inRandomOrder()->first() ?? Area::factory();

            // 2-3 horarios intensivos
            $cantidad = $this->faker->numberBetween(2, 3);
            for ($i = 0; $i < $cantidad; $i++) {
                Horario::create([
                    'sede_id' => $grupo->sede_id,
                    'area_id' => $area->id,
                    'grupo_id' => $grupo->id,
                    'grupo_nombre' => $grupo->nombre,
                    'tipo' => false,
                    'periodo' => true,
                    'dia' => $this->faker->randomElement($dias),
                    'hora' => $this->faker->randomElement(['08:00', '14:00', '18:00']),
                    'duracion_horas' => $this->faker->randomElement([4, 5, 6]), // Clases muy largas
                    'status' => 1,
                ]);
            }
        });
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
