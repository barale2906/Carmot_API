<?php

namespace App\Traits\Academico;

/**
 * Trait para manejar los estados de las versiones de plantilla de documentos.
 *
 * Proporciona las opciones, el texto, las reglas de validación y los scopes
 * del flujo En Proceso → Aprobada → Activa, con Inactiva alcanzable desde
 * cualquier estado.
 *
 * @package App\Traits\Academico
 */
trait HasPlantillaDocumentoStatus
{
    /**
     * Obtiene las opciones de estado de una versión de plantilla.
     *
     * @return array<int, string> Array con los estados disponibles
     */
    public static function getStatusOptions(): array
    {
        return [
            0 => 'Inactiva',
            1 => 'En Proceso',
            2 => 'Aprobada',
            3 => 'Activa',
        ];
    }

    /**
     * Obtiene el texto del estado basado en el número de estado.
     *
     * @param int|null $status Número del estado
     * @return string Descripción del estado o 'Desconocido' si no existe
     */
    public static function getStatusText(?int $status): string
    {
        return self::getStatusOptions()[$status] ?? 'Desconocido';
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

    /**
     * Obtiene la regla de validación para el campo status.
     *
     * @return string String con los valores válidos para validación
     */
    public static function getStatusValidationRule(): string
    {
        return 'sometimes|integer|in:' . implode(',', array_keys(self::getStatusOptions()));
    }

    /**
     * Obtiene los mensajes de error para el campo status.
     *
     * @return array<string, string> Array con los mensajes de validación
     */
    public static function getStatusValidationMessages(): array
    {
        $statusList = [];

        foreach (self::getStatusOptions() as $key => $value) {
            $statusList[] = "$key ($value)";
        }

        return [
            'status.integer' => 'El estado debe ser un número entero.',
            'status.in'      => 'El estado debe ser uno de los valores válidos: ' . implode(', ', $statusList) . '.',
        ];
    }

    /**
     * Scope para filtrar por estado "En Proceso".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnProceso($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope para filtrar por estado "Aprobada".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAprobada($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Scope para filtrar por estado "Activa".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActiva($query)
    {
        return $query->where('status', 3);
    }

    /**
     * Scope para filtrar por estado "Inactiva".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactiva($query)
    {
        return $query->where('status', 0);
    }
}
