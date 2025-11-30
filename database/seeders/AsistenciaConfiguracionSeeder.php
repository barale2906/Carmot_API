<?php

namespace Database\Seeders;

use App\Models\Academico\AsistenciaConfiguracion;
use App\Models\Academico\Curso;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AsistenciaConfiguracionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear configuración por defecto (80% mínimo) - aplica a todos los cursos
        AsistenciaConfiguracion::create([
            'curso_id' => null,
            'modulo_id' => null,
            'porcentaje_minimo' => 80.00,
            'horas_minimas' => null,
            'aplicar_justificaciones' => true,
            'perder_por_fallas' => true,
            'fecha_inicio_vigencia' => null,
            'fecha_fin_vigencia' => null,
            'observaciones' => 'Configuración por defecto del sistema',
        ]);

        // Crear configuraciones específicas por curso si existen cursos
        $cursos = Curso::all();
        if ($cursos->count() > 0) {
            foreach ($cursos->take(5) as $curso) {
                // 70% de probabilidad de crear configuración específica
                if (fake()->boolean(70)) {
                    AsistenciaConfiguracion::create([
                        'curso_id' => $curso->id,
                        'modulo_id' => null,
                        'porcentaje_minimo' => fake()->randomFloat(2, 75, 90),
                        'horas_minimas' => fake()->optional(0.3)->numberBetween(20, 100),
                        'aplicar_justificaciones' => fake()->boolean(80),
                        'perder_por_fallas' => fake()->boolean(90),
                        'fecha_inicio_vigencia' => fake()->optional(0.5)->dateTimeBetween('-1 year', 'now'),
                        'fecha_fin_vigencia' => null,
                        'observaciones' => fake()->optional(0.4)->sentence(),
                    ]);
                }
            }
        }

        $this->command->info('Configuraciones de asistencia creadas exitosamente.');
    }
}
