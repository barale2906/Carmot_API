<?php

namespace Database\Seeders;

use App\Models\Academico\Ciclo;
use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Academico\Modulo;
use App\Models\Academico\NotaEstudiante;
use App\Models\Academico\TipoNotaEsquema;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotaEstudianteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener matrículas activas
        $matriculas = Matricula::where('status', 1)
            ->with(['ciclo.grupos.modulo', 'estudiante'])
            ->get();

        $notasCreadas = 0;
        $notasPorEstudiante = 0;

        foreach ($matriculas as $matricula) {
            $estudiante = $matricula->estudiante;
            $ciclo = $matricula->ciclo;

            if (!$ciclo || !$ciclo->grupos) {
                continue;
            }

            // Para cada grupo del ciclo
            foreach ($ciclo->grupos as $grupo) {
                $modulo = $grupo->modulo;

                if (!$modulo) {
                    continue;
                }

                // Buscar esquema activo para este módulo/grupo
                $esquema = EsquemaCalificacion::where('modulo_id', $modulo->id)
                    ->where(function ($q) use ($grupo) {
                        $q->where('grupo_id', $grupo->id)
                          ->orWhereNull('grupo_id');
                    })
                    ->where('status', 1)
                    ->orderByDesc('created_at')
                    ->first();

                if (!$esquema) {
                    continue;
                }

                // Obtener tipos de nota del esquema
                $tiposNota = $esquema->tiposNota;

                if ($tiposNota->isEmpty()) {
                    continue;
                }

                // Crear notas para algunos tipos (70% de probabilidad por tipo)
                foreach ($tiposNota as $tipoNota) {
                    // 70% de probabilidad de crear la nota
                    if (rand(1, 100) <= 70) {
                        // Usar firstOrCreate para evitar duplicados
                        $nota = rand(
                            (int)($tipoNota->nota_minima * 100),
                            (int)($tipoNota->nota_maxima * 100)
                        ) / 100;

                        $notaPonderada = NotaEstudiante::calcularNotaPonderada($nota, $tipoNota->peso);

                        $notaCreada = NotaEstudiante::firstOrCreate(
                            [
                                'estudiante_id' => $estudiante->id,
                                'grupo_id' => $grupo->id,
                                'modulo_id' => $modulo->id,
                                'tipo_nota_esquema_id' => $tipoNota->id,
                            ],
                            [
                                'esquema_calificacion_id' => $esquema->id,
                                'nota' => $nota,
                                'nota_ponderada' => $notaPonderada,
                                'fecha_registro' => now()->subDays(rand(0, 90)),
                                'registrado_por_id' => $grupo->profesor_id ?? 1,
                                'status' => 1, // Registrada
                            ]
                        );

                        // Solo contar si fue creada (no existía antes)
                        if ($notaCreada->wasRecentlyCreated) {
                            $notasCreadas++;
                        }
                    }
                }

                $notasPorEstudiante++;
            }
        }

        // Crear algunas notas adicionales aleatorias usando el factory
        // Usar firstOrCreate en el factory para evitar duplicados
        for ($i = 0; $i < 50; $i++) {
            try {
                NotaEstudiante::factory()
                    ->registrada()
                    ->create();
            } catch (\Illuminate\Database\QueryException $e) {
                // Si hay duplicado, simplemente continuar
                if ($e->getCode() == 23000) {
                    continue;
                }
                throw $e;
            }
        }

        $this->command->info("Notas de estudiantes creadas: {$notasCreadas} desde matrículas + 50 aleatorias.");
        $this->command->info("Estudiantes procesados: {$notasPorEstudiante}.");
    }
}
