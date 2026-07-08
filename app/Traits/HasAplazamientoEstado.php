<?php

namespace App\Traits;

/**
 * Trait HasAplazamientoEstado
 *
 * Gestiona los estados del ciclo de vida de un Aplazamiento.
 * Fuente única de verdad: no usar enteros crudos fuera de este trait.
 *
 *  0 → Pendiente    — aplicado, esperando confirmación de reinicio
 *  1 → Confirmado   — ciclo reinició en la fecha probable, sin ajuste de fechas
 *  2 → Ampliado     — se extendió a fecha posterior (se crea un hijo)
 *  3 → Revertido    — se deshizo completamente; fechas vuelven al origen
 *  4 → Interrumpido — reinició antes de la fecha probable; fechas ajustadas hacia atrás
 */
trait HasAplazamientoEstado
{
    /**
     * Mapa completo de estados válidos.
     *
     * @return array<int, string>
     */
    public static function getEstadoOptions(): array
    {
        return [
            0 => 'Pendiente',
            1 => 'Confirmado',
            2 => 'Ampliado',
            3 => 'Revertido',
            4 => 'Interrumpido',
        ];
    }

    /**
     * Texto legible del estado dado.
     *
     * @param int|null $estado
     * @return string
     */
    public static function getEstadoText(?int $estado): string
    {
        return self::getEstadoOptions()[$estado] ?? 'Desconocido';
    }

    /**
     * Accessor: $aplazamiento->estado_text
     */
    public function getEstadoTextAttribute(): string
    {
        return self::getEstadoText($this->estado);
    }

    /**
     * Retorna el entero correspondiente a un nombre de estado.
     *
     * @param string $nombre
     * @return int|null
     */
    public static function getEstadoKey(string $nombre): ?int
    {
        $clave = array_search($nombre, self::getEstadoOptions(), true);
        return $clave !== false ? (int) $clave : null;
    }

    /**
     * Regla de validación para el campo estado (uso en FormRequest).
     */
    public static function getEstadoValidationRule(): string
    {
        return 'sometimes|integer|in:' . implode(',', array_keys(self::getEstadoOptions()));
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopePendientes($query)
    {
        return $query->where('estado', self::getEstadoKey('Pendiente'));
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeConfirmados($query)
    {
        return $query->where('estado', self::getEstadoKey('Confirmado'));
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeAmpliados($query)
    {
        return $query->where('estado', self::getEstadoKey('Ampliado'));
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeRevertidos($query)
    {
        return $query->where('estado', self::getEstadoKey('Revertido'));
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeInterrumpidos($query)
    {
        return $query->where('estado', self::getEstadoKey('Interrumpido'));
    }
}
