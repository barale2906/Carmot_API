<?php

namespace App\Traits\Financiero;

/**
 * Trait HasCarteraStatus
 *
 * Gestiona los estados de Cartera mediante un array como única fuente de verdad.
 * No se declaran constantes STATUS_* en el modelo; todo el código que necesite
 * comparar estados usa self::getStatusKey('Activa') en lugar de enteros hardcodeados.
 *
 * Estados disponibles:
 *  0 → Activa      (deuda pendiente de pago)
 *  1 → Abonada     (pago parcial registrado, aún tiene saldo)
 *  2 → Cerrada     (saldo = 0, deuda saldada)
 *  3 → Anulada     (cancelada administrativamente)
 *  4 → En Acuerdo  (reestructurada en un acuerdo de pago)
 */
trait HasCarteraStatus
{
    /**
     * Retorna el mapa completo de estados válidos para Cartera.
     *
     * @return array<int, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            0 => 'Activa',
            1 => 'Abonada',
            2 => 'Cerrada',
            3 => 'Anulada',
            4 => 'En Acuerdo',
        ];
    }

    /**
     * Devuelve el entero correspondiente a un nombre de estado.
     * Úsalo en lugar de hardcodear un integer: Cartera::getStatusKey('Activa').
     *
     * @param  string   $nombre  Nombre del estado (sensible a mayúsculas)
     * @return int|null          null si el nombre no existe en el mapa
     */
    public static function getStatusKey(string $nombre): ?int
    {
        $clave = array_search($nombre, self::getStatusOptions(), true);

        return $clave !== false ? (int) $clave : null;
    }

    /**
     * Devuelve la etiqueta legible del estado dado.
     *
     * @param  int|null $status
     * @return string
     */
    public static function getStatusText(?int $status): string
    {
        return self::getStatusOptions()[$status] ?? 'Desconocido';
    }

    /**
     * Accessor: $cartera->status_text
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusText($this->status);
    }

    /**
     * Regla de validación para el campo status (uso en FormRequest).
     */
    public static function getStatusValidationRule(): string
    {
        $claves = array_keys(self::getStatusOptions());

        return 'sometimes|integer|in:'.implode(',', $claves);
    }

    /**
     * Mensajes de validación para el campo status (uso en FormRequest).
     *
     * @return array<string, string>
     */
    public static function getStatusValidationMessages(): array
    {
        $lista = array_map(
            static fn ($k, $v) => "$k ($v)",
            array_keys(self::getStatusOptions()),
            self::getStatusOptions()
        );

        return [
            'status.integer' => 'El estado debe ser un número entero.',
            'status.in'      => 'El estado debe ser uno de los valores válidos: '.implode(', ', $lista).'.',
        ];
    }

    // -------------------------------------------------------------------------
    // Scopes — derivados del array, sin enteros hardcodeados
    // -------------------------------------------------------------------------

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeActivas($query)
    {
        return $query->where('status', self::getStatusKey('Activa'));
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeAbonadas($query)
    {
        return $query->where('status', self::getStatusKey('Abonada'));
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeCerradas($query)
    {
        return $query->where('status', self::getStatusKey('Cerrada'));
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeAnuladas($query)
    {
        return $query->where('status', self::getStatusKey('Anulada'));
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    public function scopeEnAcuerdo($query)
    {
        return $query->where('status', self::getStatusKey('En Acuerdo'));
    }

    /**
     * Cuotas con saldo pendiente (Activas o Abonadas).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopePendientes($query)
    {
        return $query->whereIn('status', [
            self::getStatusKey('Activa'),
            self::getStatusKey('Abonada'),
        ]);
    }

    /**
     * Cuotas vencidas con saldo pendiente.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|string|null            $fecha  referencia (default: hoy)
     */
    public function scopeVencidas($query, $fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();

        return $query->pendientes()->where('fecha_vencimiento', '<', $fecha);
    }

    /**
     * Cuotas próximas a vencer (pendientes, fecha_vencimiento >= hoy).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|string|null            $fecha  referencia (default: hoy)
     */
    public function scopeProximas($query, $fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();

        return $query->pendientes()->where('fecha_vencimiento', '>=', $fecha);
    }
}
