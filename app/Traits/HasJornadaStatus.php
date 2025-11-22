<?php

namespace App\Traits;

trait HasJornadaStatus
{
    /**
     * Obtiene las opciones de jornada disponibles.
     *
     * Para agregar nuevas jornadas, simplemente modifica este array:
     * Ejemplo: Agregar jornada "Intensiva" con valor 5:
     * return [
     *     0 => 'Mañana',
     *     1 => 'Tarde',
     *     2 => 'Noche',
     *     3 => 'Fin de semana mañana',
     *     4 => 'Fin de semana tarde',
     *     5 => 'Intensiva',
     * ];
     *
     * @return array<int, string> Array con las jornadas disponibles
     */
    public static function getJornadaOptions(): array
    {
        return [
            0 => 'Mañana',
            1 => 'Tarde',
            2 => 'Noche',
            3 => 'Fin de semana mañana',
            4 => 'Fin de semana tarde',
        ];
    }

    /**
     * Obtiene el texto de la jornada basado en el número de jornada.
     *
     * @param int|null $jornada Número de la jornada
     * @return string Descripción de la jornada
     */
    public static function getJornadaText(?int $jornada): string
    {
        $jornadaOptions = self::getJornadaOptions();

        return $jornadaOptions[$jornada] ?? 'Desconocida';
    }

    /**
     * Obtiene el texto de la jornada para la instancia actual del modelo.
     *
     * @return string Descripción de la jornada
     */
    public function getJornadaNombreAttribute(): string
    {
        return self::getJornadaText($this->jornada);
    }
}

