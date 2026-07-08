<?php

namespace App\Models\Financiero\ReciboPago;

use App\Models\Financiero\Descuento\Descuento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ReciboPagoSobrecargo
 *
 * Registra el detalle de cada sobrecargo aplicado a un recibo de pago.
 * Vincula la configuración del sobrecargo (Descuento) al medio de pago concreto
 * que lo disparó, permitiendo mostrar en el recibo el desglose exacto.
 *
 * @property int $id
 * @property int $recibo_pago_id
 * @property int $descuento_id FK a descuentos (tipo_movimiento='sobrecargo')
 * @property int $recibo_pago_medio_pago_id FK al medio de pago que originó el sobrecargo
 * @property float $valor_base Monto del medio de pago sobre el que se calculó el porcentaje
 * @property float $valor_sobrecargo Porcentaje * valor_base
 * @property float $valor_final valor_base + valor_sobrecargo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \App\Models\Financiero\ReciboPago\ReciboPago $reciboPago
 * @property-read \App\Models\Financiero\Descuento\Descuento $sobrecargo
 * @property-read \App\Models\Financiero\ReciboPago\ReciboPagoMedioPago $medioPago
 */
class ReciboPagoSobrecargo extends Model
{
    use HasFactory;

    protected $table = 'recibo_pago_sobrecargo';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'valor_base'       => 'decimal:2',
        'valor_sobrecargo' => 'decimal:2',
        'valor_final'      => 'decimal:2',
    ];

    /**
     * Recibo de pago al que pertenece este sobrecargo.
     */
    public function reciboPago(): BelongsTo
    {
        return $this->belongsTo(ReciboPago::class, 'recibo_pago_id');
    }

    /**
     * Configuración del sobrecargo aplicado.
     */
    public function sobrecargo(): BelongsTo
    {
        return $this->belongsTo(Descuento::class, 'descuento_id');
    }

    /**
     * Medio de pago que disparó este sobrecargo.
     */
    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(ReciboPagoMedioPago::class, 'recibo_pago_medio_pago_id');
    }
}
