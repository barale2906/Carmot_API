<?php

namespace App\Traits;

trait HasActiveStatus
{
    /**
     * Obtiene las opciones de estado para modelos con status activo/inactivo.
     *
     * Para agregar nuevos status, simplemente modifica este array:
     * Ejemplo: Agregar status "Pendiente" con valor 2:
     * return [
     *     0 => 'Inactivo',
     *     1 => 'Activo',
     *     2 => 'Pendiente',
     * ];
     *
     * @return array<string, string> Array con los estados disponibles
     */
    public static function getActiveStatusOptions(): array
    {
        return [
            0 => 'Inactivo',
            1 => 'Activo',
        ];
    }

    /**
     * Obtiene el texto del estado basado en el número de estado.
     *
     * @param int|null $status Número del estado
     * @return string Descripción del estado
     */
    public static function getActiveStatusText(?int $status): string
    {
        $statusOptions = self::getActiveStatusOptions();

        return $statusOptions[$status] ?? 'Desconocido';
    }

    /**
     * Obtiene el texto del estado para la instancia actual del modelo.
     *
     * @return string Descripción del estado
     */
    public function getActiveStatusTextAttribute(): string
    {
        return self::getActiveStatusText($this->status);
    }

    /**
     * Scope para filtrar por estado activo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope para filtrar por estado inactivo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 0);
    }

}
