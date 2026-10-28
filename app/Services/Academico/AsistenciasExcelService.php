<?php

namespace App\Services\Academico;

use App\Models\Academico\Asistencia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * AsistenciasExcelService
 *
 * Genera el Excel del listado de asistencias con los mismos filtros y el mismo orden de la
 * pantalla (clase más reciente primero): una fila por asistencia, con la justificación aparte
 * del estado y el saldo vencido del estudiante a la fecha de la descarga.
 */
class AsistenciasExcelService
{
    private const COLOR_ENCABEZADO = '1F4E79';

    private const ENCABEZADOS = [
        'Fecha clase', 'Hora', 'Sede', 'Curso', 'Módulo', 'Grupo', 'Estudiante', 'Documento',
        'Estado', 'Justificada', 'Justificación', 'Observaciones', 'En mora', 'Saldo vencido', 'Registró',
    ];

    /**
     * @param array<string, mixed> $filtros Filtros de `Asistencia::withFilters`
     * @return Spreadsheet
     */
    public function generar(array $filtros): Spreadsheet
    {
        $asistencias = Asistencia::withFilters($filtros)
            ->with(['estudiante', 'claseProgramada', 'grupo.sede', 'curso', 'modulo', 'registradoPor'])
            ->ordenadoPorClase()
            ->get();
        Asistencia::cargarMora($asistencias);

        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Asistencias');

        $ultima = Coordinate::stringFromColumnIndex(count(self::ENCABEZADOS));
        $hoja->fromArray(self::ENCABEZADOS, null, 'A1');
        $hoja->getStyle("A1:{$ultima}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_ENCABEZADO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $fila = 2;
        foreach ($asistencias as $asistencia) {
            $clase = $asistencia->claseProgramada;
            $hoja->fromArray([
                $clase?->fecha_clase?->format('Y-m-d'),
                $clase ? substr((string) $clase->hora_inicio, 0, 5) . '–' . substr((string) $clase->hora_fin, 0, 5) : null,
                $asistencia->grupo?->sede?->nombre,
                $asistencia->curso?->nombre,
                $asistencia->modulo?->nombre,
                $asistencia->grupo?->nombre,
                $asistencia->estudiante?->name,
                $asistencia->estudiante?->documento,
                ucfirst($asistencia->estado),
                $asistencia->justificada ? 'Sí' : 'No',
                $asistencia->justificacion,
                $asistencia->observaciones,
                $asistencia->saldo_vencido > 0 ? 'Sí' : 'No',
                $asistencia->saldo_vencido,
                $asistencia->registradoPor?->name,
            ], null, "A{$fila}", true);
            $fila++;
        }

        $ultimaFila = max(1, $fila - 1);
        foreach (range(1, count(self::ENCABEZADOS)) as $indice) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice))->setAutoSize(true);
        }
        $hoja->getStyle("N2:N{$ultimaFila}")->getNumberFormat()->setFormatCode('#,##0');
        $hoja->setAutoFilter("A1:{$ultima}{$ultimaFila}");
        $hoja->freezePane('A2');

        return $spreadsheet;
    }
}
