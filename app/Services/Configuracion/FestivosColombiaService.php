<?php

namespace App\Services\Configuracion;

use Carbon\Carbon;

/**
 * FestivosColombiaService
 *
 * Calcula los días festivos nacionales de Colombia para un año:
 *  - Fijos que no se trasladan: 1 ene, 1 may, 20 jul, 7 ago, 8 dic, 25 dic.
 *  - Trasladables al lunes siguiente (Ley 51 de 1983, "Ley Emiliani"):
 *    6 ene, 19 mar, 29 jun, 15 ago, 12 oct, 1 nov, 11 nov.
 *  - Dependientes de la Pascua: Jueves y Viernes Santo (no se trasladan);
 *    Ascensión (+39), Corpus Christi (+60) y Sagrado Corazón (+68), que se
 *    trasladan al lunes siguiente (+43, +64 y +71 respectivamente).
 *
 * No consulta la base de datos: solo calcula. La persistencia la decide quien lo usa.
 */
class FestivosColombiaService
{
    /** Festivos de fecha fija que no se trasladan: [mes, día, nombre]. */
    private const FIJOS = [
        [1, 1, 'Año Nuevo'],
        [5, 1, 'Día del Trabajo'],
        [7, 20, 'Día de la Independencia'],
        [8, 7, 'Batalla de Boyacá'],
        [12, 8, 'Inmaculada Concepción'],
        [12, 25, 'Navidad'],
    ];

    /** Festivos que se trasladan al lunes siguiente: [mes, día, nombre]. */
    private const TRASLADABLES = [
        [1, 6, 'Día de los Reyes Magos'],
        [3, 19, 'Día de San José'],
        [6, 29, 'San Pedro y San Pablo'],
        [8, 15, 'Asunción de la Virgen'],
        [10, 12, 'Día de la Raza'],
        [11, 1, 'Día de Todos los Santos'],
        [11, 11, 'Independencia de Cartagena'],
    ];

    /** Festivos relativos al Domingo de Pascua: [días desde Pascua, nombre]. */
    private const RELATIVOS_PASCUA = [
        [-3, 'Jueves Santo'],
        [-2, 'Viernes Santo'],
        [43, 'Ascensión del Señor'],
        [64, 'Corpus Christi'],
        [71, 'Sagrado Corazón de Jesús'],
    ];

    /**
     * Devuelve los festivos del año ordenados por fecha.
     *
     * @param int $anio
     * @return array<int, array{fecha: string, nombre: string}>
     */
    public function festivos(int $anio): array
    {
        $festivos = [];

        foreach (self::FIJOS as [$mes, $dia, $nombre]) {
            $festivos[] = ['fecha' => Carbon::create($anio, $mes, $dia), 'nombre' => $nombre];
        }

        foreach (self::TRASLADABLES as [$mes, $dia, $nombre]) {
            $festivos[] = ['fecha' => $this->siguienteLunes(Carbon::create($anio, $mes, $dia)), 'nombre' => $nombre];
        }

        $pascua = $this->domingoDePascua($anio);
        foreach (self::RELATIVOS_PASCUA as [$offset, $nombre]) {
            $festivos[] = ['fecha' => $pascua->copy()->addDays($offset), 'nombre' => $nombre];
        }

        usort($festivos, fn ($a, $b) => $a['fecha'] <=> $b['fecha']);

        return array_map(fn ($f) => [
            'fecha'  => $f['fecha']->toDateString(),
            'nombre' => $f['nombre'],
        ], $festivos);
    }

    /**
     * Domingo de Pascua (calendario gregoriano) por el algoritmo de Meeus/Jones/Butcher.
     * Se calcula sin la extensión ext-calendar para no agregar dependencias.
     *
     * @param int $anio
     * @return Carbon
     */
    public function domingoDePascua(int $anio): Carbon
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($anio, $mes, $dia)->startOfDay();
    }

    /**
     * Traslada la fecha al lunes siguiente; si ya es lunes, la conserva.
     */
    private function siguienteLunes(Carbon $fecha): Carbon
    {
        return $fecha->isMonday() ? $fecha : $fecha->next(Carbon::MONDAY);
    }
}
