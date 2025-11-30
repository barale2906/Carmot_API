<?php

namespace Database\Seeders;

use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AsistenciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar que existan los datos necesarios
        if (AsistenciaClaseProgramada::count() === 0) {
            $this->command->warn('No hay clases programadas disponibles. Por favor ejecuta AsistenciaClaseProgramadaSeeder primero.');
            return;
        }

        if (Matricula::where('status', 1)->count() === 0) {
            $this->command->warn('No hay matrículas activas disponibles. Por favor ejecuta MatriculaSeeder primero.');
            return;
        }

        // Obtener clases programadas y matrículas activas
        $clasesProgramadas = AsistenciaClaseProgramada::with(['grupo', 'ciclo'])->get();
        $matriculas = Matricula::where('status', 1)->with('estudiante')->get();

        // Crear asistencias para cada clase programada
        foreach ($clasesProgramadas->take(50) as $claseProgramada) {
            // Obtener estudiantes del grupo a través de matrículas del ciclo
            $estudiantesDelCiclo = $matriculas->where('ciclo_id', $claseProgramada->ciclo_id)
                ->pluck('estudiante_id')
                ->unique()
                ->take(rand(5, 15)); // Entre 5 y 15 estudiantes por clase

            foreach ($estudiantesDelCiclo as $estudianteId) {
                // 80% presente, 10% ausente, 5% justificado, 5% tardanza
                $random = fake()->numberBetween(1, 100);
                if ($random <= 80) {
                    $estado = 'presente';
                } elseif ($random <= 90) {
                    $estado = 'ausente';
                } elseif ($random <= 95) {
                    $estado = 'justificado';
                } else {
                    $estado = 'tardanza';
                }

                Asistencia::create([
                    'estudiante_id' => $estudianteId,
                    'clase_programada_id' => $claseProgramada->id,
                    'grupo_id' => $claseProgramada->grupo_id,
                    'ciclo_id' => $claseProgramada->ciclo_id,
                    'modulo_id' => $claseProgramada->grupo->modulo_id,
                    'curso_id' => $claseProgramada->ciclo->curso_id,
                    'estado' => $estado,
                    'hora_registro' => fake()->time('H:i:s'),
                    'observaciones' => $estado === 'justificado' ? fake()->sentence() : null,
                    'registrado_por_id' => 1, // Usuario por defecto
                    'fecha_registro' => $claseProgramada->fecha_clase,
                ]);
            }
        }

        $this->command->info('Asistencias creadas exitosamente.');
    }
}
