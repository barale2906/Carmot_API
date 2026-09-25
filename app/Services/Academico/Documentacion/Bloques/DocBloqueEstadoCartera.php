<?php

namespace App\Services\Academico\Documentacion\Bloques;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use Illuminate\Database\Eloquent\Model;

/**
 * Bloque de estado de cartera.
 *
 * Imprime una fila por cuota de la matrícula, con su valor, lo abonado, el saldo
 * pendiente y su vencimiento. El resumen totaliza las columnas de dinero.
 *
 * @package App\Services\Academico\Documentacion\Bloques
 */
class DocBloqueEstadoCartera implements DocBloqueContract
{
    /**
     * Columnas que el bloque puede entregar.
     *
     * @return array<string, array{label: string, type: string}>
     */
    public function columnas(): array
    {
        return [
            'numero_cuota'      => ['label' => 'Cuota', 'type' => 'integer'],
            'valor'             => ['label' => 'Valor', 'type' => 'money'],
            'descuento'         => ['label' => 'Descuento', 'type' => 'money'],
            'abono'             => ['label' => 'Abonado', 'type' => 'money'],
            'saldo'             => ['label' => 'Saldo', 'type' => 'money'],
            'mora_acumulada'    => ['label' => 'Mora', 'type' => 'money'],
            'fecha_vencimiento' => ['label' => 'Vencimiento', 'type' => 'date'],
            'estado'            => ['label' => 'Estado', 'type' => 'string'],
        ];
    }

    /**
     * Consulta las cuotas de cartera de la matrícula.
     *
     * @param Model $entidad
     * @return array{filas: array<int, array<string, mixed>>, resumen: array{label: string, valores: array<string, mixed>}|null}
     */
    public function datos(Model $entidad): array
    {
        if (!$entidad instanceof Matricula) {
            return ['filas' => [], 'resumen' => null];
        }

        $cuotas = Cartera::where('matricula_id', $entidad->id)
            ->orderBy('numero_cuota')
            ->get();

        $filas = $cuotas->map(fn (Cartera $cuota) => [
            'numero_cuota'      => $cuota->numero_cuota,
            'valor'             => $cuota->valor,
            'descuento'         => $cuota->descuento,
            'abono'             => $cuota->abono,
            'saldo'             => $cuota->saldo,
            'mora_acumulada'    => $cuota->mora_acumulada,
            'fecha_vencimiento' => $cuota->fecha_vencimiento,
            'estado'            => Cartera::getStatusText($cuota->status),
        ])->all();

        return [
            'filas'   => $filas,
            'resumen' => [
                'label'   => 'Totales',
                'valores' => [
                    'valor'     => $cuotas->sum('valor'),
                    'descuento' => $cuotas->sum('descuento'),
                    'abono'     => $cuotas->sum('abono'),
                    'saldo'     => $cuotas->sum('saldo'),
                ],
            ],
        ];
    }
}
