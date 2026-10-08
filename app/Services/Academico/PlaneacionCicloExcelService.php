<?php

namespace App\Services\Academico;

use App\Models\Academico\Ciclo;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * PlaneacionCicloExcelService
 *
 * Genera el libro Excel de la planeación de un ciclo a partir de
 * PlaneacionCicloService: una hoja tabular (grupo → tópico → tema) y una hoja
 * tipo Gantt con una columna por día, coloreada entre inicio y fin.
 */
class PlaneacionCicloExcelService
{
    private const COLOR_ENCABEZADO = '1F4E79';

    private const COLOR_GRUPO = '213360';

    private const COLOR_TEMA = '60A5FA';

    /** Primera columna de días en la hoja Gantt (A = etiqueta, B = inicio, C = fin). */
    private const COLUMNA_INICIAL_DIAS = 4;

    public function __construct(private readonly PlaneacionCicloService $planeacionCiclo)
    {
    }

    public function generar(Ciclo $ciclo): Spreadsheet
    {
        $planeacion  = $this->planeacionCiclo->planeacion($ciclo);
        $spreadsheet = new Spreadsheet;

        $this->hojaPlaneacion($spreadsheet->getActiveSheet(), $planeacion['grupos']);
        $this->hojaGantt($spreadsheet->createSheet(), $planeacion['grupos']);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function hojaPlaneacion(Worksheet $hoja, array $grupos): void
    {
        $hoja->setTitle('Planeación');
        $encabezados = ['Orden', 'Grupo', 'Módulo', 'Tópico', 'Tema', 'Horas originales', 'Horas ajustadas', 'Fecha inicio', 'Fecha fin'];
        $this->escribirEncabezado($hoja, $encabezados);

        $fila = 2;
        foreach ($grupos as $grupo) {
            foreach ($grupo['topicos'] as $topico) {
                foreach ($topico['temas'] as $tema) {
                    $hoja->fromArray([
                        $grupo['orden'],
                        $grupo['grupo_nombre'],
                        $grupo['modulo']['nombre'] ?? '',
                        $topico['nombre'],
                        $tema['nombre'],
                        $tema['horas_originales'],
                        $tema['horas_ajustadas'],
                        $tema['fecha_inicio'],
                        $tema['fecha_fin'],
                    ], null, "A{$fila}");
                    $fila++;
                }
            }
        }

        foreach (range('A', 'I') as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }
        $hoja->freezePane('A2');
    }

    private function hojaGantt(Worksheet $hoja, array $grupos): void
    {
        $hoja->setTitle('Gantt');
        $filas = $this->filasGantt($grupos);
        $rango = $this->rangoFechas($filas);

        $this->escribirEncabezado($hoja, ['Grupo / tema', 'Inicio', 'Fin']);
        $hoja->getColumnDimension('A')->setWidth(45);
        $hoja->getColumnDimension('B')->setWidth(12);
        $hoja->getColumnDimension('C')->setWidth(12);

        if ($rango === null) {
            return;
        }

        [$desde, $hasta] = $rango;
        $totalDias       = $desde->diffInDays($hasta) + 1;

        for ($i = 0; $i < $totalDias; $i++) {
            $columna = Coordinate::stringFromColumnIndex(self::COLUMNA_INICIAL_DIAS + $i);
            $dia     = $desde->copy()->addDays($i);
            $celda   = $hoja->getCell("{$columna}1");
            $celda->setValue($dia->format('d/m'));
            $hoja->getColumnDimension($columna)->setWidth(5);
            $hoja->getStyle("{$columna}1")->applyFromArray($this->estiloEncabezado());
            $hoja->getStyle("{$columna}1")->getAlignment()->setTextRotation(90);
        }

        $fila = 2;
        foreach ($filas as $item) {
            $hoja->setCellValue("A{$fila}", ($item['esGrupo'] ? '' : '    ').$item['etiqueta']);
            $hoja->setCellValue("B{$fila}", $item['inicio']);
            $hoja->setCellValue("C{$fila}", $item['fin']);

            if ($item['esGrupo']) {
                $hoja->getStyle("A{$fila}:C{$fila}")->getFont()->setBold(true);
            }

            if ($item['inicio'] && $item['fin']) {
                $primera = Coordinate::stringFromColumnIndex(self::COLUMNA_INICIAL_DIAS + $desde->diffInDays(Carbon::parse($item['inicio'])));
                $ultima  = Coordinate::stringFromColumnIndex(self::COLUMNA_INICIAL_DIAS + $desde->diffInDays(Carbon::parse($item['fin'])));
                $hoja->getStyle("{$primera}{$fila}:{$ultima}{$fila}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($item['esGrupo'] ? self::COLOR_GRUPO : self::COLOR_TEMA);
            }
            $fila++;
        }

        $hoja->freezePane('D2');
    }

    /**
     * Una fila por grupo y una por cada tema que lo compone.
     *
     * @return array<int, array{etiqueta: string, esGrupo: bool, inicio: ?string, fin: ?string}>
     */
    private function filasGantt(array $grupos): array
    {
        $filas = [];

        foreach ($grupos as $grupo) {
            $filas[] = [
                'etiqueta' => $grupo['orden'].'. '.($grupo['modulo']['nombre'] ?? $grupo['grupo_nombre']),
                'esGrupo'  => true,
                'inicio'   => $grupo['fecha_inicio_grupo'],
                'fin'      => $grupo['fecha_fin_grupo'],
            ];

            foreach ($grupo['topicos'] as $topico) {
                foreach ($topico['temas'] as $tema) {
                    $filas[] = [
                        'etiqueta' => $tema['nombre'],
                        'esGrupo'  => false,
                        'inicio'   => $tema['fecha_inicio'],
                        'fin'      => $tema['fecha_fin'] ?? $tema['fecha_inicio'],
                    ];
                }
            }
        }

        return $filas;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function rangoFechas(array $filas): ?array
    {
        $fechas = collect($filas)->flatMap(fn ($f) => [$f['inicio'], $f['fin']])->filter()->sort()->values();

        if ($fechas->isEmpty()) {
            return null;
        }

        return [Carbon::parse($fechas->first())->startOfDay(), Carbon::parse($fechas->last())->startOfDay()];
    }

    private function escribirEncabezado(Worksheet $hoja, array $encabezados): void
    {
        $hoja->fromArray($encabezados, null, 'A1');
        $ultima = Coordinate::stringFromColumnIndex(count($encabezados));
        $hoja->getStyle("A1:{$ultima}1")->applyFromArray($this->estiloEncabezado());
    }

    private function estiloEncabezado(): array
    {
        return [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_ENCABEZADO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
    }
}
