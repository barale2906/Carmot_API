<?php

namespace App\Services\Academico;

use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Matricula;
use App\Models\Academico\NotaEstudiante;
use App\Models\User;

/**
 * Servicio SabanaNotasService
 *
 * Construye la sábana de notas de un estudiante: por cada módulo del ciclo
 * matriculado reúne los tipos de nota del esquema activo, la nota obtenida, su
 * ponderación y la nota final, más el promedio general de los módulos completos.
 *
 * Lo consumen el endpoint de sábana y el bloque de documentos que la imprime,
 * para que ambos calculen exactamente lo mismo.
 *
 * @package App\Services\Academico
 */
class SabanaNotasService
{
    /**
     * Calcula la sábana de notas de un estudiante.
     *
     * @param int      $estudianteId
     * @param int|null $cicloId Restringe la matrícula a un ciclo.
     * @param int|null $cursoId Restringe la matrícula a un curso.
     * @return array<string, mixed>|null Null si el estudiante no tiene matrícula activa.
     */
    public function porEstudiante(int $estudianteId, ?int $cicloId = null, ?int $cursoId = null): ?array
    {
        $estudiante = User::findOrFail($estudianteId);

        $matriculaQuery = Matricula::where('estudiante_id', $estudianteId)
            ->where('status', 1);

        if ($cicloId) {
            $matriculaQuery->where('ciclo_id', $cicloId);
        }

        if ($cursoId) {
            $matriculaQuery->where('curso_id', $cursoId);
        }

        $matricula = $matriculaQuery->with(['curso', 'ciclo'])->first();

        if (!$matricula) {
            return null;
        }

        return $this->construir($estudiante, $matricula);
    }

    /**
     * Calcula la sábana de notas a partir de una matrícula.
     *
     * @param Matricula $matricula
     * @return array<string, mixed>|null Null si la matrícula no tiene estudiante o ciclo.
     */
    public function porMatricula(Matricula $matricula): ?array
    {
        $matricula->loadMissing(['curso', 'ciclo', 'estudiante']);

        if (!$matricula->estudiante || !$matricula->ciclo) {
            return null;
        }

        return $this->construir($matricula->estudiante, $matricula);
    }

    /**
     * Arma la estructura de la sábana para un estudiante y su matrícula.
     *
     * @param User      $estudiante
     * @param Matricula $matricula
     * @return array<string, mixed>
     */
    private function construir(User $estudiante, Matricula $matricula): array
    {
        $grupos           = $matricula->ciclo->grupos()->with('modulo')->get();
        $modulosData      = [];
        $sumaNotasFinales = 0;
        $modulosCompletos = 0;

        foreach ($grupos as $grupo) {
            $modulo   = $grupo->modulo;
            $moduloId = $modulo->id;

            $esquema = EsquemaCalificacion::activoParaModuloGrupo($moduloId, $grupo->id)->first();

            if (!$esquema) {
                continue;
            }

            $notas = NotaEstudiante::where('estudiante_id', $estudiante->id)
                ->where('grupo_id', $grupo->id)
                ->where('modulo_id', $moduloId)
                ->where('esquema_calificacion_id', $esquema->id)
                ->where('status', 1)
                ->with('tipoNotaEsquema')
                ->get();

            $notaFinal    = $notas->sum('nota_ponderada');
            $tiposNota    = $esquema->tiposNota;
            $tiposConNota = $notas->pluck('tipo_nota_esquema_id')->toArray();

            $tiposNotaData = $tiposNota->map(function ($tipo) use ($notas) {
                $nota = $notas->firstWhere('tipo_nota_esquema_id', $tipo->id);

                return [
                    'id'             => $tipo->id,
                    'nombre_tipo'    => $tipo->nombre_tipo,
                    'peso'           => (float) $tipo->peso,
                    'nota'           => $nota ? (float) $nota->nota : null,
                    'nota_ponderada' => $nota ? (float) $nota->nota_ponderada : null,
                    'pendiente'      => !$nota,
                ];
            });

            $completo = $tiposConNota === $tiposNota->pluck('id')->toArray();

            if ($completo) {
                $modulosCompletos++;
                $sumaNotasFinales += $notaFinal;
            }

            $modulosData[] = [
                'modulo' => [
                    'id'     => $modulo->id,
                    'nombre' => $modulo->nombre,
                ],
                'grupo' => [
                    'id'     => $grupo->id,
                    'nombre' => $grupo->nombre,
                ],
                'esquema_calificacion' => [
                    'id'             => $esquema->id,
                    'nombre_esquema' => $esquema->nombre_esquema,
                ],
                'tipos_nota' => $tiposNotaData,
                'nota_final' => round($notaFinal, 2),
                'completo'   => $completo,
            ];
        }

        return [
            'estudiante' => [
                'id'        => $estudiante->id,
                'name'      => $estudiante->name,
                'email'     => $estudiante->email,
                'documento' => $estudiante->documento,
            ],
            'curso' => [
                'id'     => $matricula->curso->id,
                'nombre' => $matricula->curso->nombre,
            ],
            'ciclo' => [
                'id'     => $matricula->ciclo->id,
                'nombre' => $matricula->ciclo->nombre,
            ],
            'modulos'           => $modulosData,
            'promedio_general'  => $modulosCompletos > 0 ? round($sumaNotasFinales / $modulosCompletos, 2) : null,
            'total_modulos'     => count($modulosData),
            'modulos_completos' => $modulosCompletos,
        ];
    }
}
