<?php

namespace App\Services\Crm;

use App\Models\Crm\Seguimiento;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * SeguimientosExcelService
 *
 * Genera el Excel de la bitácora del CRM en un lapso de fechas: una fila por seguimiento,
 * con los datos del lead y el estado en que quedó, en el mismo orden de la pantalla
 * (fecha más reciente primero y, en la misma fecha, por nombre del lead).
 */
class SeguimientosExcelService
{
    private const COLOR_ENCABEZADO = '1F4E79';

    private const ENCABEZADOS = ['Fecha', 'Lead', 'Celular', 'Ciudad', 'Curso de interés', 'Asesor', 'Registró', 'Seguimiento', 'Estado del lead'];

    /**
     * @param string $desde Fecha inicial (Y-m-d)
     * @param string $hasta Fecha final (Y-m-d)
     * @param array $filtrosLead Filtros del lead: search, status
     * @return Spreadsheet
     */
    public function generar(string $desde, string $hasta, array $filtrosLead = []): Spreadsheet
    {
        $seguimientos = Seguimiento::byDateRange($desde, $hasta)
            ->deLeads($filtrosLead)
            ->with(['referido.curso', 'referido.gestor', 'seguidor'])
            ->ordenadoPorFechaYLead()
            ->get();

        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Seguimientos');

        $ultima = Coordinate::stringFromColumnIndex(count(self::ENCABEZADOS));
        $hoja->fromArray(self::ENCABEZADOS, null, 'A1');
        $hoja->getStyle("A1:{$ultima}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_ENCABEZADO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $fila = 2;
        foreach ($seguimientos as $seguimiento) {
            $lead = $seguimiento->referido;
            $hoja->fromArray([
                $seguimiento->fecha?->format('Y-m-d'),
                $lead?->nombre ?: 'Sin nombre',
                $lead?->celular,
                $lead?->ciudad,
                $lead?->curso?->nombre,
                $lead?->gestor?->name,
                $seguimiento->seguidor?->name,
                $seguimiento->seguimiento,
                $seguimiento->estado_referido_text ?? '',
            ], null, "A{$fila}");
            $fila++;
        }

        foreach (range('A', 'G') as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }
        // El texto del seguimiento puede ser largo: ancho fijo con ajuste de línea
        $hoja->getColumnDimension('H')->setWidth(70);
        $ultimaFila = max(1, $fila - 1);
        $hoja->getStyle("H1:H{$ultimaFila}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $hoja->getColumnDimension('I')->setAutoSize(true);
        $hoja->setAutoFilter("A1:{$ultima}{$ultimaFila}");
        $hoja->freezePane('A2');

        return $spreadsheet;
    }
}
