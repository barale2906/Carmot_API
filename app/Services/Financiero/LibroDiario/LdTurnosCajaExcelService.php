<?php

namespace App\Services\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * LdTurnosCajaExcelService
 *
 * Genera el Excel detallado de los cierres de caja que el usuario puede consultar, con
 * los filtros del historial y en su mismo orden (apertura más reciente primero):
 *  - Hoja "Cierres": una fila por turno con el arqueo y los totales del resumen.
 *  - Hoja "Por medio de pago": una fila por turno y medio de pago con movimiento.
 *
 * El resumen es el congelado al cierre; el de un turno abierto se calcula en vivo.
 * Conteo ciego: si el usuario no puede ver el arqueo de un turno
 * (LdTurnoCaja::arqueoVisiblePara), sus celdas de efectivo quedan vacías y del
 * desglose solo salen los medios distintos al efectivo, igual que en el PDF.
 */
class LdTurnosCajaExcelService
{
    private const COLOR_ENCABEZADO = '1F4E79';

    private const FORMATO_MONEDA = '"$" #,##0';

    private const ENCABEZADOS_CIERRES = [
        'N.° turno', 'Fecha', 'Sede', 'Cajero', 'Estado', 'Ajustado', 'Apertura', 'Cierre', 'Cerrado por',
        'Aprobado por', 'Fecha aprobación', 'Base inicial', 'Ingresos académicos', 'Ingresos inventario',
        'Otros ingresos', 'Total ingresos', 'Egresos', 'Consignaciones', 'Efectivo esperado', 'Efectivo contado',
        'Diferencia', 'Recibos', 'Movimientos', 'Cant. consignaciones', 'Transferencias por aprobar',
        'Valor transferencias por aprobar', 'Observaciones del cajero', 'Observaciones de aprobación',
    ];

    /** Columnas con valores en pesos de la hoja "Cierres" (L a U y Z). */
    private const MONEDA_CIERRES = ['L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'Z'];

    private const ENCABEZADOS_MEDIOS = [
        'N.° turno', 'Fecha', 'Sede', 'Cajero', 'Estado', 'Medio de pago', 'Académico', 'Inventario',
        'Otros ingresos', 'Total ingresos', 'Egresos',
    ];

    public function __construct(private readonly LdTurnoCajaService $turnoService) {}

    /**
     * @param  User  $usuario  Quien descarga: define el alcance y la visibilidad del arqueo
     * @param  array  $filtros  sede_id, cajero_id, status, fecha_desde, fecha_hasta
     */
    public function generar(User $usuario, array $filtros = []): Spreadsheet
    {
        $turnos = LdTurnoCaja::visiblesPara($usuario)
            ->withFilters($filtros)
            ->with(['sede', 'cajero', 'cerradoPor', 'aprobadoPor'])
            ->withSorting('abierto_at', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet;
        $cierres = $spreadsheet->getActiveSheet();
        $cierres->setTitle('Cierres');
        $medios = $spreadsheet->createSheet();
        $medios->setTitle('Por medio de pago');

        $this->encabezados($cierres, self::ENCABEZADOS_CIERRES);
        $this->encabezados($medios, self::ENCABEZADOS_MEDIOS);

        $filaCierre = 2;
        $filaMedio = 2;
        foreach ($turnos as $turno) {
            $verArqueo = $turno->arqueoVisiblePara($usuario);
            $resumen = $turno->estaAbierto() ? $this->turnoService->calcularResumen($turno) : ($turno->resumen ?? []);
            $datosTurno = [
                $turno->id,
                $turno->fecha?->format('Y-m-d'),
                $turno->sede?->nombre,
                $turno->cajero?->name,
                LdTurnoCaja::getStatusOptions()[$turno->status] ?? '',
            ];

            $cierres->fromArray($this->filaCierre($turno, $datosTurno, $resumen, $verArqueo), null, "A{$filaCierre}", true);
            $filaCierre++;

            $porMedio = $verArqueo ? ($resumen['por_medio_pago'] ?? []) : LdTurnoCaja::resumenSinEfectivo($resumen)['por_medio_pago'];
            foreach ($porMedio as $medio => $valores) {
                if (array_sum($valores) == 0) {
                    continue;
                }
                $medios->fromArray([
                    ...$datosTurno,
                    ReciboPagoMedioPago::MEDIOS[$medio] ?? $medio,
                    $valores['academico'] ?? 0,
                    $valores['inventario'] ?? 0,
                    $valores['otros'] ?? 0,
                    $valores['ingresos'] ?? 0,
                    $valores['egresos'] ?? 0,
                ], null, "A{$filaMedio}", true);
                $filaMedio++;
            }
        }

        $this->terminarHoja($cierres, count(self::ENCABEZADOS_CIERRES), $filaCierre - 1, self::MONEDA_CIERRES);
        $this->terminarHoja($medios, count(self::ENCABEZADOS_MEDIOS), $filaMedio - 1, ['G', 'H', 'I', 'J', 'K']);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Fila de la hoja "Cierres". Sin acceso al arqueo, lo que mueve efectivo queda en blanco.
     *
     * @param  array<int, mixed>  $datosTurno
     * @param  array<string, mixed>  $resumen
     * @return array<int, mixed>
     */
    private function filaCierre(LdTurnoCaja $turno, array $datosTurno, array $resumen, bool $verArqueo): array
    {
        $arqueo = fn ($valor) => $verArqueo && $valor !== null ? (float) $valor : null;
        $pendientes = $resumen['transferencias_pendientes'] ?? [];

        return [
            ...$datosTurno,
            $turno->ajustado ? 'Sí' : 'No',
            $turno->abierto_at?->format('Y-m-d H:i'),
            $turno->cerrado_at?->format('Y-m-d H:i'),
            $turno->cerradoPor?->name,
            $turno->aprobadoPor?->name,
            $turno->aprobado_at?->format('Y-m-d H:i'),
            $arqueo($turno->base_inicial),
            $arqueo($resumen['ingresos']['academico'] ?? null),
            $arqueo($resumen['ingresos']['inventario'] ?? null),
            $arqueo($resumen['ingresos']['otros'] ?? null),
            $arqueo($resumen['ingresos']['total'] ?? null),
            $arqueo($resumen['egresos'] ?? null),
            $arqueo($resumen['consignaciones'] ?? null),
            $arqueo($turno->estaAbierto() ? ($resumen['efectivo_esperado'] ?? null) : $turno->efectivo_esperado),
            $turno->efectivo_contado !== null ? (float) $turno->efectivo_contado : null,
            $arqueo($turno->diferencia),
            $verArqueo ? ($resumen['cantidades']['recibos'] ?? null) : null,
            $verArqueo ? ($resumen['cantidades']['movimientos'] ?? null) : null,
            $verArqueo ? ($resumen['cantidades']['consignaciones'] ?? null) : null,
            $pendientes['cantidad'] ?? null,
            isset($pendientes['valor']) ? (float) $pendientes['valor'] : null,
            $turno->observaciones_cierre,
            $turno->observaciones_aprobacion,
        ];
    }

    /**
     * @param  array<int, string>  $encabezados
     */
    private function encabezados(Worksheet $hoja, array $encabezados): void
    {
        $ultima = Coordinate::stringFromColumnIndex(count($encabezados));
        $hoja->fromArray($encabezados, null, 'A1');
        $hoja->getStyle("A1:{$ultima}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_ENCABEZADO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    /**
     * Formato de moneda, ancho automático, autofiltro y encabezado fijo.
     *
     * @param  array<int, string>  $columnasMoneda
     */
    private function terminarHoja(Worksheet $hoja, int $columnas, int $ultimaFila, array $columnasMoneda): void
    {
        $ultima = Coordinate::stringFromColumnIndex($columnas);
        $ultimaFila = max(1, $ultimaFila);

        if ($ultimaFila > 1) {
            foreach ($columnasMoneda as $columna) {
                $hoja->getStyle("{$columna}2:{$columna}{$ultimaFila}")->getNumberFormat()->setFormatCode(self::FORMATO_MONEDA);
            }
        }
        for ($i = 1; $i <= $columnas; $i++) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $hoja->setAutoFilter("A1:{$ultima}{$ultimaFila}");
        $hoja->freezePane('A2');
    }
}
