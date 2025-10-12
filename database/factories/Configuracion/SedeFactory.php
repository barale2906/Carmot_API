<?php

namespace Database\Factories\Configuracion;

use App\Models\Configuracion\Area;
use App\Models\Configuracion\Horario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Configuracion\Sede>
 */
class SedeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Ciudades específicas de Colombia para las sedes
        $ciudadesColombia = ['Tunja', 'Duitama', 'Ipiales'];

        return [
            'nombre' => $this->faker->company() . ' - Sede',
            'direccion' => $this->faker->streetAddress(),
            'telefono' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'hora_inicio' => $this->faker->time('H:i:s', '08:00:00'),
            'hora_fin' => $this->faker->time('H:i:s', '18:00:00'),
            'poblacion_id' => \App\Models\Configuracion\Poblacion::whereIn('nombre', $ciudadesColombia)
                ->where('pais', 'Colombia')
                ->inRandomOrder()
                ->first()
                ?->id ?? \App\Models\Configuracion\Poblacion::factory(),
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure()
    {
        return $this->afterCreating(function ($sede) {
            $this->createHorariosForSede($sede);
        });
    }

    /**
     * Crear horarios de funcionamiento para la sede
     *
     * @param \App\Models\Configuracion\Sede $sede
     * @return void
     */
    private function createHorariosForSede($sede)
    {
        // Días de la semana
        $dias = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

        // Determinar si la sede funciona de lunes a sábado o domingo a domingo
        $tipoFuncionamiento = $this->faker->boolean(70); // 70% probabilidad de lunes a sábado

        if ($tipoFuncionamiento) {
            // Lunes a sábado (6 días)
            $diasFuncionamiento = array_slice($dias, 0, 6);
        } else {
            // Domingo a domingo (7 días)
            $diasFuncionamiento = $dias;
        }

        // Seleccionar al menos 3 días aleatorios de los días de funcionamiento
        $diasSeleccionados = $this->faker->randomElements($diasFuncionamiento, $this->faker->numberBetween(3, count($diasFuncionamiento)));

        // Obtener un área aleatoria para los horarios
        $area = Area::inRandomOrder()->first() ?? Area::create(['nombre' => 'Sala de estudio']);

        // Horarios de apertura y cierre típicos
        $horariosApertura = ['07:00:00', '08:00:00', '09:00:00'];
        $horariosCierre = ['17:00:00', '18:00:00', '19:00:00', '20:00:00'];

        foreach ($diasSeleccionados as $dia) {
            $horaApertura = $this->faker->randomElement($horariosApertura);
            $horaCierre = $this->faker->randomElement($horariosCierre);

            // Crear horario de apertura
            Horario::create([
                'sede_id' => $sede->id,
                'area_id' => $area->id,
                'grupo_id' => null,
                'grupo_nombre' => null,
                'tipo' => true, // Siempre true para horarios de sede
                'periodo' => true, // true = inicio de jornada
                'dia' => $dia,
                'hora' => $horaApertura,
                'status' => 1,
            ]);

            // Crear horario de cierre
            Horario::create([
                'sede_id' => $sede->id,
                'area_id' => $area->id,
                'grupo_id' => null,
                'grupo_nombre' => null,
                'tipo' => true, // Siempre true para horarios de sede
                'periodo' => false, // false = fin de jornada
                'dia' => $dia,
                'hora' => $horaCierre,
                'status' => 1,
            ]);
        }
    }
}
