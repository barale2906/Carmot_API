<?php

namespace App\Traits\Financiero;

/**
 * Trait para manejar los estados de los recibos de pago.
 *
 * Este trait proporciona métodos y scopes para trabajar con los estados
 * de los recibos de pago: En Proceso, Creado, Cerrado y Anulado.
 *
 * @package App\Traits\Financiero
 */
trait HasReciboPagoStatus
{
    /**
     * Obtiene las opciones de estado para Recibo de Pago.
     *
     * Retorna un array asociativo con los estados disponibles:
     * - 0: En Proceso
     * - 1: Creado
     * - 2: Cerrado
     * - 3: Anulado
     *
     * @return array<int, string> Array con los estados disponibles
     */
    public static function getStatusOptions(): array
    {
        return [
            0 => 'En Proceso',
            1 => 'Creado',
            2 => 'Cerrado',
            3 => 'Anulado',
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
     * para el campo status de los recibos de pago.
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
     * Obtiene las opciones de origen para Recibo de Pago.
     *
     * Retorna un array asociativo con los orígenes disponibles:
     * - 0: Inventarios
     * - 1: Académico
     *
     * @return array<int, string> Array con los orígenes disponibles
     */
    public static function getOrigenOptions(): array
    {
        return [
            0 => 'Inventarios',
            1 => 'Académico',
        ];
    }

    /**
     * Obtiene el texto del origen basado en el número de origen.
     *
     * @param int|null $origen Número del origen
     * @return string Descripción del origen o 'Desconocido' si no existe
     */
    public static function getOrigenText(?int $origen): string
    {
        $origenOptions = self::getOrigenOptions();

        return $origenOptions[$origen] ?? 'Desconocido';
    }

    /**
     * Obtiene el texto del origen para la instancia actual del modelo.
     *
     * Este método funciona como accessor de Laravel, permitiendo
     * acceder al texto del origen mediante $modelo->origen_text.
     *
     * @return string Descripción del origen
     */
    public function getOrigenTextAttribute(): string
    {
        return self::getOrigenText($this->origen);
    }

    /**
     * Scope para filtrar por estado "En Proceso".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnProceso($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope para filtrar por estado "Creado".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCreados($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope para filtrar por estado "Cerrado".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCerrados($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Scope para filtrar por estado "Anulado".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAnulados($query)
    {
        return $query->where('status', 3);
    }
}

