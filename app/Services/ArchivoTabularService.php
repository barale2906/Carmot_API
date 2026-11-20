<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lectura común de los archivos de carga masiva: acepta Excel (.xlsx, .xls) y texto
 * delimitado (.csv, .txt, con coma, punto y coma o tabulación; con o sin BOM UTF-8).
 * La primera hoja (o el CSV) se entrega como encabezados + filas, de modo que cada
 * carga trabaje igual sin importar el formato con que el usuario guardó el archivo.
 */
class ArchivoTabularService
{
    /** Regla de validación de los FormRequest de carga masiva: extensiones admitidas. */
    public const REGLA_MIMES = 'mimes:xlsx,xls,csv,txt';

    /** Mensaje de la regla `mimes`. */
    public const MENSAJE_MIMES = 'El archivo debe ser Excel (.xlsx, .xls) o CSV.';

    /**
     * Lee el archivo. Los encabezados se devuelven en minúscula y sin espacios sobrantes;
     * las celdas, como texto recortado (null si están vacías). Las filas completamente
     * vacías se descartan y cada fila conserva su número real en el archivo como clave.
     *
     * @return array{encabezados: array<int, string>, filas: array<int, array<int, string|null>>}
     *
     * @throws ValidationException Si el archivo no se puede abrir.
     */
    public function leer(string $ruta): array
    {
        try {
            $contenido = IOFactory::load($ruta)->getSheet(0)->toArray(null, true, false, false);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el archivo. Use la plantilla en formato Excel (.xlsx) o CSV.']);
        }

        $encabezados = array_map(fn ($valor) => strtolower(trim((string) $valor)), array_shift($contenido) ?? []);

        $filas = [];
        foreach ($contenido as $indice => $valores) {
            $fila = array_map(fn ($valor) => $valor === null || trim((string) $valor) === '' ? null : trim((string) $valor), $valores);
            if (array_filter($fila, fn ($valor) => $valor !== null)) {
                $filas[$indice + 2] = $fila;
            }
        }

        return ['encabezados' => $encabezados, 'filas' => $filas];
    }
}
