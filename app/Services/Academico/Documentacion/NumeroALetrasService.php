<?php

namespace App\Services\Academico\Documentacion;

/**
 * Servicio NumeroALetrasService
 *
 * Convierte valores numéricos a su representación en letras en español,
 * necesaria en documentos legales como pagarés y contratos
 * ("UN MILLÓN DOSCIENTOS MIL PESOS M/CTE").
 *
 * @package App\Services\Academico\Documentacion
 */
class NumeroALetrasService
{
    /** Números con nombre propio (0 a 29). */
    private const UNIDADES = [
        0  => '',            1  => 'UNO',          2  => 'DOS',          3  => 'TRES',
        4  => 'CUATRO',      5  => 'CINCO',        6  => 'SEIS',         7  => 'SIETE',
        8  => 'OCHO',        9  => 'NUEVE',        10 => 'DIEZ',         11 => 'ONCE',
        12 => 'DOCE',        13 => 'TRECE',        14 => 'CATORCE',      15 => 'QUINCE',
        16 => 'DIECISEIS',   17 => 'DIECISIETE',   18 => 'DIECIOCHO',    19 => 'DIECINUEVE',
        20 => 'VEINTE',      21 => 'VEINTIUNO',    22 => 'VEINTIDOS',    23 => 'VEINTITRES',
        24 => 'VEINTICUATRO', 25 => 'VEINTICINCO', 26 => 'VEINTISEIS',   27 => 'VEINTISIETE',
        28 => 'VEINTIOCHO',  29 => 'VEINTINUEVE',
    ];

    /** Decenas exactas a partir de treinta. */
    private const DECENAS = [
        3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA',
        6 => 'SESENTA', 7 => 'SETENTA',  8 => 'OCHENTA', 9 => 'NOVENTA',
    ];

    /** Centenas exactas. */
    private const CENTENAS = [
        1 => 'CIENTO',      2 => 'DOSCIENTOS',  3 => 'TRESCIENTOS',
        4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS', 6 => 'SEISCIENTOS',
        7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS',
    ];

    /**
     * Convierte un valor monetario a letras.
     *
     * @param float  $numero Valor a convertir.
     * @param string $moneda Nombre de la moneda en plural (PESOS, DÓLARES…).
     * @param string $sufijo Sufijo legal agregado al final (M/CTE).
     * @return string Valor en letras, en mayúsculas.
     */
    public function convertir(float $numero, string $moneda = 'PESOS', string $sufijo = 'M/CTE'): string
    {
        $absoluto = abs($numero);
        $entero   = (int) floor($absoluto);
        $centavos = (int) round(($absoluto - $entero) * 100);

        if ($centavos === 100) {
            $entero++;
            $centavos = 0;
        }

        $texto = $this->enteroALetras($entero) . ' ' . $moneda;

        if ($centavos > 0) {
            $texto .= ' CON ' . $this->enteroALetras($centavos) . ' CENTAVOS';
        }

        if ($numero < 0) {
            $texto = 'MENOS ' . $texto;
        }

        return trim($texto . ' ' . $sufijo);
    }

    /**
     * Convierte un número entero a letras.
     *
     * @param int $numero
     * @return string
     */
    public function enteroALetras(int $numero): string
    {
        if ($numero === 0) {
            return 'CERO';
        }

        if ($numero < 0) {
            return 'MENOS ' . $this->enteroALetras(abs($numero));
        }

        return trim($this->componer($numero));
    }

    /**
     * Compone recursivamente el texto de un entero positivo.
     *
     * @param int $numero
     * @return string
     */
    private function componer(int $numero): string
    {
        if ($numero >= 1000000) {
            $millones = intdiv($numero, 1000000);
            $resto    = $numero % 1000000;
            $prefijo  = $millones === 1
                ? 'UN MILLON'
                : $this->apocopar($this->componer($millones)) . ' MILLONES';

            return trim($prefijo . ' ' . ($resto > 0 ? $this->componer($resto) : ''));
        }

        if ($numero >= 1000) {
            $miles   = intdiv($numero, 1000);
            $resto   = $numero % 1000;
            $prefijo = $miles === 1
                ? 'MIL'
                : $this->apocopar($this->componer($miles)) . ' MIL';

            return trim($prefijo . ' ' . ($resto > 0 ? $this->componer($resto) : ''));
        }

        if ($numero === 100) {
            return 'CIEN';
        }

        if ($numero > 100) {
            $centena = intdiv($numero, 100);
            $resto   = $numero % 100;

            return trim(self::CENTENAS[$centena] . ' ' . ($resto > 0 ? $this->componer($resto) : ''));
        }

        if ($numero < 30) {
            return self::UNIDADES[$numero];
        }

        $decena = intdiv($numero, 10);
        $unidad = $numero % 10;

        return trim(self::DECENAS[$decena] . ($unidad > 0 ? ' Y ' . self::UNIDADES[$unidad] : ''));
    }

    /**
     * Aplica la apócope de "UNO" a "UN" antes de MIL/MILLONES.
     *
     * @param string $texto
     * @return string
     */
    private function apocopar(string $texto): string
    {
        return preg_replace('/UNO$/', 'UN', $texto);
    }
}
