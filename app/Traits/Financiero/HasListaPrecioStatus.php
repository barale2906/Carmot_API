<?php

namespace App\Traits\Financiero;

/**
 * Trait para manejar los estados de las listas de precios.
 *
 * Este trait proporciona métodos y scopes para trabajar con los estados
 * de las listas de precios: Inactiva, En Proceso, Aprobada y Activa.
 *
 * @package App\Traits\Financiero
 */
trait HasListaPrecioStatus
{
    /**
     * Obtiene las opciones de estado para Lista de Precios.
     *
     * Retorna un array asociativo con los estados disponibles:
     * - 0: Inactiva
     * - 1: En Proceso
     * - 2: Aprobada
     * - 3: Activa
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
        $statusOptions = self::getStatusOptions();

        return $statusOptions[$status] ?? 'Desconocido';
    }

    /**
     * Obtiene el texto del estado para la instancia actual del modelo.
     *
     * Este método funciona como accessor de Laravel, permitiendo
     * acceder al texto del estado mediante $modelo->status_text.
     *
     * @return string Descripción del estado
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusText($this->status);
    }

    /**
     * Obtiene las opciones de estado en formato para validación.
     *
     * Retorna una cadena con las reglas de validación que pueden ser
     * usadas en los FormRequest para validar el campo status.
     *
     * @return string String con los valores válidos para validación
     */
    public static function getStatusValidationRule(): string
    {
        $statuses = array_keys(self::getStatusOptions());
        return 'sometimes|integer|in:' . implode(',', $statuses);
    }

    /**
     * Obtiene los mensajes de error para el campo status.
     *
     * Retorna un array con los mensajes de validación en español
     * para el campo status de las listas de precios.
     *
     * @return array<string, string> Array con los mensajes de validación
     */
    public static function getStatusValidationMessages(): array
    {
        $statusOptions = self::getStatusOptions();
        $statusList = [];

        foreach ($statusOptions as $key => $value) {
            $statusList[] = "$key ($value)";
        }

        return [
            'status.integer' => 'El estado debe ser un número entero.',
            'status.in' => 'El estado debe ser uno de los valores válidos: ' . implode(', ', $statusList) . '.',
        ];
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
}

