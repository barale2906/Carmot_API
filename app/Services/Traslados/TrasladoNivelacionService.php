<?php

namespace App\Services\Traslados;

use App\Models\Academico\Ciclo;
use App\Services\Academico\PlaneacionCicloService;
use Carbon\Carbon;

/**
 * Cruza la planeación de temas de dos ciclos del mismo curso para saber si, al trasladar a un
 * estudiante, el tema donde quedó coincide con el que se está dictando en el ciclo de destino.
 *
 * Usa la planeación del sistema (PlaneacionCicloService), que ya define en qué fecha se dicta cada
 * tema de cada ciclo: un tema cuenta como dictado si su fecha de fin es anterior a la fecha del
 * traslado. Es solo informativo: nunca impide el traslado, sino que dice qué falta por dictar
 * al estudiante para que coordinación defina la nivelación.
 */
class TrasladoNivelacionService
{
    public function __construct(private readonly PlaneacionCicloService $planeacion) {}

    /**
     * Resultado para un traslado dentro del mismo curso.
     *
     * - `faltante_por_dictar`: temas que el ciclo de destino ya dictó y el estudiante no vio en su ciclo.
     * - `repite`: temas que el estudiante ya vio y el ciclo de destino todavía va a dictar.
     * - `coincide`: no hay faltantes ni repetidos.
     *
     * @return array<string, mixed>
     */
    public function evaluar(Ciclo $origen, Ciclo $destino, Carbon $fecha): array
    {
        $hasta = $fecha->toDateString();
        $temasOrigen = $this->temas($origen);
        $temasDestino = $this->temas($destino);

        $vistos = $this->dictados($temasOrigen, $hasta);
        $dictadosDestino = $this->dictados($temasDestino, $hasta);

        $faltantes = array_values(array_diff_key($dictadosDestino, $vistos));
        $repite = array_values(array_filter(
            array_diff_key($vistos, $dictadosDestino),
            fn (array $tema) => isset($temasDestino[$tema['clave']]) && $temasDestino[$tema['clave']]['fecha_fin'] !== null
        ));
        // Lo que "repite" se describe con las horas del ciclo de destino, que es donde se vuelve a dictar.
        $repite = array_map(fn (array $tema) => $temasDestino[$tema['clave']], $repite);

        $horasFaltantes = round(array_sum(array_column($faltantes, 'horas')), 2);
        $horasRepite = round(array_sum(array_column($repite, 'horas')), 2);

        return [
            'aplica' => true,
            'fecha' => $hasta,
            'coincide' => $faltantes === [] && $repite === [],
            'requiere_nivelacion' => $faltantes !== [],
            'tema_actual_origen' => $this->temaActual($temasOrigen, $hasta),
            'tema_actual_destino' => $this->temaActual($temasDestino, $hasta),
            'faltante_por_dictar' => $this->agruparPorModulo($faltantes),
            'horas_faltantes' => $horasFaltantes,
            'repite' => $this->agruparPorModulo($repite),
            'horas_repite' => $horasRepite,
            'mensaje' => $this->mensaje($faltantes, $repite, $horasFaltantes, $horasRepite),
        ];
    }

    /**
     * Resultado cuando el traslado cambia de curso: el estudiante empieza el curso nuevo desde el
     * principio y no hay nada que nivelar.
     *
     * @return array<string, mixed>
     */
    public function noAplica(): array
    {
        return [
            'aplica' => false,
            'requiere_nivelacion' => false,
            'mensaje' => 'El cambio de curso inicia el curso nuevo desde el principio; no requiere nivelación.',
        ];
    }

    /**
     * Temas de la planeación del ciclo, en el orden en que se dictan, indexados por módulo-tópico-tema.
     *
     * @return array<string, array<string, mixed>>
     */
    private function temas(Ciclo $ciclo): array
    {
        $temas = [];

        foreach ($this->planeacion->planeacion($ciclo)['grupos'] as $grupo) {
            foreach ($grupo['topicos'] as $topico) {
                foreach ($topico['temas'] as $tema) {
                    $clave = "{$grupo['modulo']['id']}:{$topico['topico_id']}:{$tema['tema_id']}";
                    $temas[$clave] = [
                        'clave' => $clave,
                        'modulo_id' => $grupo['modulo']['id'],
                        'modulo' => $grupo['modulo']['nombre'],
                        'topico_id' => $topico['topico_id'],
                        'topico' => $topico['nombre'],
                        'tema_id' => $tema['tema_id'],
                        'tema' => $tema['nombre'],
                        'horas' => (float) $tema['horas_ajustadas'],
                        'fecha_inicio' => $tema['fecha_inicio'],
                        'fecha_fin' => $tema['fecha_fin'],
                    ];
                }
            }
        }

        return $temas;
    }

    /**
     * Temas ya dictados antes de la fecha (su fecha de fin es anterior).
     *
     * @param  array<string, array<string, mixed>>  $temas
     * @return array<string, array<string, mixed>>
     */
    private function dictados(array $temas, string $hasta): array
    {
        return array_filter($temas, fn (array $tema) => $tema['fecha_fin'] !== null && $tema['fecha_fin'] < $hasta);
    }

    /**
     * Primer tema que aún no termina a la fecha del traslado: el que se está dictando o el próximo.
     *
     * @param  array<string, array<string, mixed>>  $temas
     * @return array<string, mixed>|null
     */
    private function temaActual(array $temas, string $hasta): ?array
    {
        foreach ($temas as $tema) {
            if ($tema['fecha_fin'] !== null && $tema['fecha_fin'] >= $hasta) {
                return $this->resumenTema($tema);
            }
        }

        return null;
    }

    /**
     * Agrupa los temas por módulo, con las horas de cada módulo.
     *
     * @param  array<int, array<string, mixed>>  $temas
     * @return array<int, array<string, mixed>>
     */
    private function agruparPorModulo(array $temas): array
    {
        $modulos = [];

        foreach ($temas as $tema) {
            $modulos[$tema['modulo_id']] ??= ['modulo_id' => $tema['modulo_id'], 'modulo' => $tema['modulo'], 'horas' => 0.0, 'temas' => []];
            $modulos[$tema['modulo_id']]['horas'] = round($modulos[$tema['modulo_id']]['horas'] + $tema['horas'], 2);
            $modulos[$tema['modulo_id']]['temas'][] = $this->resumenTema($tema);
        }

        return array_values($modulos);
    }

    /**
     * @param  array<string, mixed>  $tema
     * @return array<string, mixed>
     */
    private function resumenTema(array $tema): array
    {
        return [
            'tema_id' => $tema['tema_id'],
            'tema' => $tema['tema'],
            'topico' => $tema['topico'],
            'modulo' => $tema['modulo'],
            'horas' => $tema['horas'],
            'fecha_inicio' => $tema['fecha_inicio'],
            'fecha_fin' => $tema['fecha_fin'],
        ];
    }

    /**
     * Texto que se le muestra a quien registra el traslado.
     *
     * @param  array<int, array<string, mixed>>  $faltantes
     * @param  array<int, array<string, mixed>>  $repite
     */
    private function mensaje(array $faltantes, array $repite, float $horasFaltantes, float $horasRepite): string
    {
        if ($faltantes !== []) {
            return sprintf(
                'Al estudiante le faltan por dictar %d tema(s) (%s h) que el ciclo de destino ya dictó: defina la nivelación.',
                count($faltantes),
                $horasFaltantes
            );
        }

        if ($repite !== []) {
            return sprintf(
                'El ciclo de destino va atrasado: el estudiante repetirá %d tema(s) (%s h) que ya vio.',
                count($repite),
                $horasRepite
            );
        }

        return 'El tema donde quedó el estudiante coincide con el del ciclo de destino.';
    }
}
