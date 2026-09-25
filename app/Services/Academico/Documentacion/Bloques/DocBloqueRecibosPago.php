<?php

namespace App\Services\Academico\Documentacion\Bloques;

use App\Models\Academico\Matricula;
use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Database\Eloquent\Model;

/**
 * Bloque de listado de recibos de pago.
 *
 * Imprime una fila por recibo asociado a la matrícula, ordenado por fecha. Los
 * recibos anulados se incluyen con su estado visible, porque un paz y salvo o una
 * certificación de pagos debe reflejar lo que realmente ocurrió.
 *
 * @package App\Services\Academico\Documentacion\Bloques
 */
class DocBloqueRecibosPago implements DocBloqueContract
{
    /**
     * Columnas que el bloque puede entregar.
     *
     * @return array<string, array{label: string, type: string}>
     */
    public function columnas(): array
    {
        return [
            'numero_recibo'    => ['label' => 'Número de recibo', 'type' => 'string'],
            'fecha_recibo'     => ['label' => 'Fecha', 'type' => 'date'],
            'valor_total'      => ['label' => 'Valor', 'type' => 'money'],
            'descuento_total'  => ['label' => 'Descuento', 'type' => 'money'],
            'sobrecargo_total' => ['label' => 'Sobrecargo', 'type' => 'money'],
            'estado'           => ['label' => 'Estado', 'type' => 'string'],
        ];
    }

    /**
     * Consulta los recibos de pago de la matrícula.
     *
     * @param Model $entidad
     * @return array{filas: array<int, array<string, mixed>>, resumen: array{label: string, valores: array<string, mixed>}|null}
     */
    public function datos(Model $entidad): array
    {
        if (!$entidad instanceof Matricula) {
            return ['filas' => [], 'resumen' => null];
        }

        $recibos = ReciboPago::where('matricula_id', $entidad->id)
            ->orderBy('fecha_recibo')
            ->orderBy('id')
            ->get();

        $filas = $recibos->map(fn (ReciboPago $recibo) => [
            'numero_recibo'    => $recibo->numero_recibo,
            'fecha_recibo'     => $recibo->fecha_recibo,
            'valor_total'      => $recibo->valor_total,
            'descuento_total'  => $recibo->descuento_total,
            'sobrecargo_total' => $recibo->sobrecargo_total,
            'estado'           => ReciboPago::getStatusText($recibo->status),
        ])->all();

        return [
            'filas'   => $filas,
            'resumen' => [
                'label'   => 'Total recibido',
                'valores' => ['valor_total' => $recibos->sum('valor_total')],
            ],
        ];
    }
}
