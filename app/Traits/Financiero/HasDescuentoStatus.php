<?php

namespace App\Traits\Financiero;

use Carbon\Carbon;

/**
 * Trait para manejar los estados de los descuentos.
 *
 * Este trait proporciona métodos y scopes para trabajar con los estados
 * de los descuentos: Inactivo, En Proceso, Aprobado y Activo.
 *
 * @package App\Traits\Financiero
 */
trait HasDescuentoStatus
{
    /**
     * Obtiene el valor del estado Inactivo.
     *
     * @return int
     */
    public static function getStatusInactivo(): int
    {
        return 0;
    }

    /**
     * Obtiene el valor del estado En Proceso.
     *
     * @return int
     */
    public static function getStatusEnProceso(): int
    {
        return 1;
    }

    /**
     * Obtiene el valor del estado Aprobado.
     *
     * @return int
     */
    public static function getStatusAprobado(): int
    {
        return 2;
    }

    /**
     * Obtiene el valor del estado Activo.
     *
     * @return int
     */
    public static function getStatusActivo(): int
    {
        return 3;
    }

    /**
     * Obtiene las opciones de estado para Descuento.
     *
     * Retorna un array asociativo con los estados disponibles:
     * - 0: Inactivo
     * - 1: En Proceso
     * - 2: Aprobado
     * - 3: Activo
     *
     * @return array<int, string> Array con los estados disponibles
     */
    public static function getStatusOptions(): array
    {
        return [
            self::getStatusInactivo() => 'Inactivo',
            self::getStatusEnProceso() => 'En Proceso',
            self::getStatusAprobado() => 'Aprobado',
            self::getStatusActivo() => 'Activo',
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
     * para el campo status de los descuentos.
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
     * Scope para filtrar por estado "Inactivo".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactivo($query)
    {
        return $query->where('status', self::getStatusInactivo());
    }

    /**
     * Scope para filtrar por estado "En Proceso".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnProceso($query)
    {
        return $query->where('status', self::getStatusEnProceso());
    }

    /**
     * Scope para filtrar por estado "Aprobado".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAprobado($query)
    {
        return $query->where('status', self::getStatusAprobado());
    }

    /**
     * Scope para filtrar por estado "Activo".
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActivo($query)
    {
        return $query->where('status', self::getStatusActivo());
    }

    /**
     * Scope para filtrar descuentos aprobados que deben activarse automáticamente.
     * Retorna descuentos que están en estado "Aprobado" y cuya fecha de inicio ya llegó o pasó.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAprobadosParaActivar($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('status', self::getStatusAprobado())
                    ->where('fecha_inicio', '<=', $fecha);
    }

    /**
     * Scope para filtrar descuentos activos que deben inactivarse por pérdida de vigencia.
     * Retorna descuentos que están activos pero cuya fecha de fin ya pasó.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActivosParaInactivar($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('status', self::getStatusActivo())
                    ->where('fecha_fin', '<', $fecha);
    }

    /**
     * Activa automáticamente los descuentos aprobados cuando inicia su vigencia.
     * Este método debe ejecutarse mediante un comando programado (cron).
     *
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return void
     */
    public static function activarDescuentosAprobados(?Carbon $fecha = null): void
    {
        $fecha = $fecha ?? Carbon::now();

        static::aprobadosParaActivar($fecha)->update([
            'status' => self::getStatusActivo()
        ]);
    }

    /**
     * Inactiva automáticamente los descuentos activos que han perdido su vigencia.
     * Este método debe ejecutarse mediante un comando programado (cron).
     *
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return void
     */
    public static function inactivarDescuentosVencidos(?Carbon $fecha = null): void
    {
        $fecha = $fecha ?? Carbon::now();

        static::activosParaInactivar($fecha)->update([
            'status' => self::getStatusInactivo()
        ]);
    }
}

