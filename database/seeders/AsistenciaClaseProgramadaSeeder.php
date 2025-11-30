<?php

namespace Database\Seeders;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AsistenciaClaseProgramadaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Este seeder es opcional y crea clases programadas de prueba.
     * En producción, las clases se generan automáticamente usando el servicio.
     */
    public function run(): void
    {
        // Verificar que existan los datos necesarios
        if (Grupo::count() === 0) {
            $this->command->warn('No hay grupos disponibles. Por favor ejecuta GrupoSeeder primero.');
            return;
        }

        if (Ciclo::count() === 0) {
            $this->command->warn('No hay ciclos disponibles. Por favor ejecuta CicloSeeder primero.');
            return;
        }

        // Obtener grupos y ciclos
        $grupos = Grupo::all();
        $ciclos = Ciclo::where('status', 1)->get();

        if ($ciclos->isEmpty()) {
            $this->command->warn('No hay ciclos activos disponibles.');
            return;
        }

        // Crear algunas clases programadas de prueba
        foreach ($grupos->take(10) as $grupo) {
            // Obtener ciclos que contienen este grupo
            $ciclosDelGrupo = $ciclos->filter(function ($ciclo) use ($grupo) {
                return $ciclo->grupos->contains($grupo->id);
            });

            if ($ciclosDelGrupo->isEmpty()) {
                continue;
            }

            $ciclo = $ciclosDelGrupo->random();

            // Crear 5-10 clases programadas para este grupo y ciclo
            $cantidadClases = fake()->numberBetween(5, 10);
            $clasesCreadas = 0;
            $intentos = 0;
            $maxIntentos = $cantidadClases * 3; // Permitir hasta 3 intentos por clase

            while ($clasesCreadas < $cantidadClases && $intentos < $maxIntentos) {
                $intentos++;

                $fechaClase = fake()->dateTimeBetween('-1 month', '+2 months');
                $horaInicio = fake()->time('08:00', '18:00');
                $horaFin = (clone \Carbon\Carbon::parse($horaInicio))->addHours(2)->format('H:i:s');

                // Verificar si ya existe una clase con la misma combinación única
                $existe = AsistenciaClaseProgramada::where('grupo_id', $grupo->id)
                    ->where('ciclo_id', $ciclo->id)
                    ->whereDate('fecha_clase', $fechaClase->format('Y-m-d'))
                    ->whereTime('hora_inicio', $horaInicio)
                    ->exists();

                if (!$existe) {
                    AsistenciaClaseProgramada::create([
                        'grupo_id' => $grupo->id,
                        'ciclo_id' => $ciclo->id,
                        'fecha_clase' => $fechaClase,
                        'hora_inicio' => $horaInicio,
                        'hora_fin' => $horaFin,
                        'duracion_horas' => 2.00,
                        'estado' => fake()->randomElement(['programada', 'dictada', 'cancelada']),
                        'observaciones' => fake()->optional(0.2)->sentence(),
                        'creado_por_id' => 1,
                        'fecha_programacion' => now(),
                    ]);
                    $clasesCreadas++;
                }
            }

            if ($clasesCreadas < $cantidadClases) {
                $this->command->warn("Solo se pudieron crear {$clasesCreadas} de {$cantidadClases} clases programadas para el grupo {$grupo->id} debido a duplicados.");
            }
        }

        $this->command->info('Clases programadas de prueba creadas exitosamente.');
    }
}
