<?php

namespace App\Services\Concerns;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Utilidades comunes de las plantillas Excel de carga masiva (libro diario, activos):
 * encabezados con el estilo del sistema, hoja de catálogos "Catalogos", listas
 * desplegables, validación de fecha y lectura de fechas escritas por el usuario.
 */
trait ArmaPlantillaExcel
{
    /**
     * Fecha de la fila: hoy si viene vacía, false si no se reconoce. Acepta la fecha de
     * Excel (número de serie) o texto en AAAA-MM-DD, DD/MM/AAAA o DD-MM-AAAA.
     */
    protected function fecha(mixed $valor): Carbon|false
    {
        if ($valor === null || $valor === '') {
            return today();
        }
        if (is_numeric($valor)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $valor))->startOfDay();
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            $fecha = \DateTime::createFromFormat("!{$formato}", (string) $valor);
            if ($fecha && $fecha->format($formato) === (string) $valor) {
                return Carbon::instance($fecha);
            }
        }

        return false;
    }

    /**
     * Escribe la fila de encabezados con el estilo de los Excel del sistema.
     *
     * @param  array<int, string>  $encabezados
     */
    protected function encabezado(Worksheet $hoja, array $encabezados, int $fila = 1, int $columna = 1): void
    {
        $inicio = Coordinate::stringFromColumnIndex($columna);
        $fin = Coordinate::stringFromColumnIndex($columna + count($encabezados) - 1);
        $hoja->fromArray($encabezados, null, "{$inicio}{$fila}");
        $hoja->getStyle("{$inicio}{$fila}:{$fin}{$fila}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    /**
     * Escribe cada catálogo en un par de columnas (valor, detalle) y devuelve el rango de sus valores.
     *
     * @param  array<string, array<int, array{0: string, 1: string}>>  $catalogos
     * @return array<string, string>
     */
    protected function hojaCatalogos(Worksheet $hoja, array $catalogos): array
    {
        $hoja->setTitle('Catalogos');
        $rangos = [];
        $columna = 1;
        foreach ($catalogos as $titulo => $valores) {
            $this->encabezado($hoja, [$titulo, 'Detalle'], 1, $columna);
            if ($valores) {
                $hoja->fromArray($valores, null, Coordinate::stringFromColumnIndex($columna).'2');
            }

            $letra = Coordinate::stringFromColumnIndex($columna);
            $rangos[$titulo] = "'".'Catalogos'."'!\${$letra}\$2:\${$letra}\$".max(2, count($valores) + 1);
            $hoja->getColumnDimension($letra)->setAutoSize(true);
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($columna + 1))->setAutoSize(true);
            $columna += 3;
        }

        return $rangos;
    }

    /**
     * Lista desplegable sobre un rango de celdas a partir de un rango del catálogo.
     */
    protected function listaDesplegable(Worksheet $hoja, string $celdas, string $origen): void
    {
        $validacion = new DataValidation;
        $validacion->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Valor no válido')
            ->setError('Seleccione un valor de la lista (hoja '.'Catalogos'.').')
            ->setFormula1($origen);
        $hoja->setDataValidation($celdas, $validacion);
    }

    /**
     * Restringe la columna de fecha a fechas hasta hoy.
     */
    protected function validacionFecha(Worksheet $hoja, string $celdas): void
    {
        $validacion = new DataValidation;
        $validacion->setType(DataValidation::TYPE_DATE)
            ->setOperator(DataValidation::OPERATOR_LESSTHANOREQUAL)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Fecha no válida')
            ->setError('Escriba una fecha (AAAA-MM-DD) que no sea posterior a hoy.')
            ->setFormula1('TODAY()');
        $hoja->setDataValidation($celdas, $validacion);
    }

    /**
     * Pares [código, descripción] de un arreglo constante.
     *
     * @param  array<string, string>  $opciones
     * @return array<int, array{0: string, 1: string}>
     */
    protected function pares(array $opciones): array
    {
        return collect($opciones)->map(fn ($etiqueta, $codigo) => [$codigo, $etiqueta])->values()->all();
    }
}
