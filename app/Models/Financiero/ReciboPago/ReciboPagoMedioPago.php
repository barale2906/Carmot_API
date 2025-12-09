<?php

namespace App\Models\Financiero\ReciboPago;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ReciboPagoMedioPago
 *
 * Representa un medio de pago utilizado en un recibo de pago.
 * Un recibo puede tener múltiples medios de pago (efectivo, tarjeta, transferencia, etc.).
 *
 * @property int $id Identificador único del registro
 * @property int $recibo_pago_id ID del recibo de pago
 * @property string $medio_pago Medio de pago (efectivo, tarjeta, transferencia, cheque, etc.)
 * @property float $valor Valor pagado con este medio
 * @property string|null $referencia Referencia del pago (número de cheque, transferencia, etc.)
 * @property string|null $banco Banco relacionado (si aplica)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de actualización
 *
 * @property-read \App\Models\Financiero\ReciboPago\ReciboPago $reciboPago Recibo de pago asociado
 */
class ReciboPagoMedioPago extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'recibo_pago_medio_pago';

    /**
     * Los atributos que no son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valor' => 'decimal:2',
    ];

    /**
     * Relación con ReciboPago (muchos a uno).
     * Un medio de pago pertenece a un recibo de pago.
     *
     * @return BelongsTo
     */
    public function reciboPago(): BelongsTo
    {
        return $this->belongsTo(ReciboPago::class, 'recibo_pago_id');
    }
}

