<?php

namespace App\Traits;

trait HasStatus
{
    /**
     * Obtiene las opciones de estado para Referido.
     *
     * @return array<string, string> Array con los estados disponibles
     */
    public static function getStatusOptions(): array
    {
        return [
            0 => 'Creado',
            1 => 'Interesado',
            2 => 'Pendiente por matricular',
            3 => 'Matriculado',
            4 => 'Declinado',
        ];
    }

    /**
     * Obtiene el texto del estado basado en el número de estado.
     *
     * @param int|null $status Número del estado
     * @return string Descripción del estado
     */
    public static function getStatusText(?int $status): string
    {
        $statusOptions = self::getStatusOptions();

        return $statusOptions[$status] ?? 'Desconocido';
    }

    /**
     * Obtiene el texto del estado para la instancia actual del modelo.
     *
     * @return string Descripción del estado
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusText($this->status);
    }
}
