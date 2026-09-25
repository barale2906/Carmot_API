<?php

namespace App\Services\Academico\Documentacion\Bloques;

use App\Models\Academico\Matricula;
use App\Services\Academico\SabanaNotasService;
use Illuminate\Database\Eloquent\Model;

/**
 * Bloque de sábana de notas.
 *
 * Imprime una fila por módulo del ciclo matriculado. El detalle de los tipos de
 * nota se entrega consolidado en una sola columna para que la tabla mantenga una
 * fila por módulo independientemente de las columnas que elija el administrador.
 *
 * @package App\Services\Academico\Documentacion\Bloques
 */
class DocBloqueSabanaNotas implements DocBloqueContract
{
    /**
     * @param SabanaNotasService $sabana Cálculo compartido con el endpoint de sábana.
     */
    public function __construct(private SabanaNotasService $sabana)
    {
    }

    /**
     * Columnas que el bloque puede entregar.
     *
     * @return array<string, array{label: string, type: string}>
     */
    public function columnas(): array
    {
        return [
            'modulo'         => ['label' => 'Módulo', 'type' => 'string'],
            'grupo'          => ['label' => 'Grupo', 'type' => 'string'],
            'esquema'        => ['label' => 'Esquema de calificación', 'type' => 'string'],
            'detalle_notas'  => ['label' => 'Detalle de notas', 'type' => 'string'],
            'nota_final'     => ['label' => 'Nota final', 'type' => 'decimal'],
            'estado'         => ['label' => 'Estado', 'type' => 'string'],
        ];
    }

    /**
     * Consulta las notas del estudiante de la matrícula.
     *
     * @param Model $entidad
     * @return array{filas: array<int, array<string, mixed>>, resumen: array{label: string, valores: array<string, mixed>}|null}
     */
    public function datos(Model $entidad): array
    {
        if (!$entidad instanceof Matricula) {
            return ['filas' => [], 'resumen' => null];
        }

        $sabana = $this->sabana->porMatricula($entidad);

        if (!$sabana) {
            return ['filas' => [], 'resumen' => null];
        }

        $filas = [];

        foreach ($sabana['modulos'] as $modulo) {
            $filas[] = [
                'modulo'        => $modulo['modulo']['nombre'],
                'grupo'         => $modulo['grupo']['nombre'],
                'esquema'       => $modulo['esquema_calificacion']['nombre_esquema'],
                'detalle_notas' => $this->detalle($modulo['tipos_nota']),
                'nota_final'    => $modulo['nota_final'],
                'estado'        => $modulo['completo'] ? 'Completo' : 'Pendiente',
            ];
        }

        return [
            'filas'   => $filas,
            'resumen' => [
                'label'   => 'Promedio general',
                'valores' => ['nota_final' => $sabana['promedio_general']],
            ],
        ];
    }

    /**
     * Consolida los tipos de nota de un módulo en una sola celda.
     *
     * @param iterable<array<string, mixed>> $tiposNota
     * @return string
     */
    private function detalle(iterable $tiposNota): string
    {
        $partes = [];

        foreach ($tiposNota as $tipo) {
            $valor     = $tipo['nota'] === null ? 'pendiente' : number_format((float) $tipo['nota'], 2, ',', '.');
            $partes[]  = $tipo['nombre_tipo'] . ': ' . $valor;
        }

        return implode(' · ', $partes);
    }
}
