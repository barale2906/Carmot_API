<?php

namespace App\Traits\Financiero;

/**
 * Trait para los estados de los registros manuales del libro diario
 * (movimientos y consignaciones): Registrado o Anulado.
 *
 * Un registro contable no se borra: se anula dejando quién, cuándo y por qué.
 */
trait HasLdRegistroStatus
{
    /**
     * Opciones de estado del registro.
     *
     * @return array<int, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_REGISTRADO => 'Registrado',
            self::STATUS_ANULADO => 'Anulado',
        ];
    }

    /**
     * Texto del estado indicado.
     */
    public static function getStatusText(?int $status): string
    {
        return self::getStatusOptions()[$status] ?? 'Desconocido';
    }

    /**
     * Accessor `status_text` de la instancia.
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusText($this->status);
    }

    /**
     * Scope para los registros vigentes (no anulados).
     */
    public function scopeRegistrados($query)
    {
        return $query->where($this->getTable().'.status', self::STATUS_REGISTRADO);
    }

    /**
     * Indica si el registro está anulado.
     */
    public function estaAnulado(): bool
    {
        return $this->status === self::STATUS_ANULADO;
    }

    /**
     * Anula el registro con su motivo.
     */
    public function anular(int $usuarioId, string $motivo): bool
    {
        return $this->update([
            'status' => self::STATUS_ANULADO,
            'anulado_por_id' => $usuarioId,
            'anulado_at' => now(),
            'motivo_anulacion' => $motivo,
        ]);
    }
}
