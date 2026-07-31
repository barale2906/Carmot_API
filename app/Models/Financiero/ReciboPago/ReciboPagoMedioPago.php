<?php

namespace App\Models\Financiero\ReciboPago;

use App\Models\Configuracion\Banco;
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
 * @property string|null $banco Banco relacionado (texto libre, legado)
 * @property int|null $banco_id FK al catálogo de bancos
 * @property string|null $numero_transaccion Número único de transacción bancaria
 * @property string|null $comprobante_path Ruta del comprobante en storage
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de actualización
 *
 * @property-read \App\Models\Financiero\ReciboPago\ReciboPago $reciboPago Recibo de pago asociado
 * @property-read \App\Models\Configuracion\Banco|null $banco_rel Entidad bancaria del catálogo
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

    protected $casts = [
        'valor'    => 'decimal:2',
        'banco_id' => 'integer',
    ];

    /**
     * Relación con ReciboPago (muchos a uno).
     *
     * @return BelongsTo
     */
    public function reciboPago(): BelongsTo
    {
        return $this->belongsTo(ReciboPago::class, 'recibo_pago_id');
    }

    /**
     * Entidad bancaria del catálogo (solo para transferencias).
     *
     * @return BelongsTo
     */
    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class, 'banco_id');
    }

    /**
     * Devuelve la URL pública del comprobante si existe.
     *
     * @return string|null
     */
    public function getComprobanteUrlAttribute(): ?string
    {
        return $this->comprobante_path
            ? \Illuminate\Support\Facades\Storage::url($this->comprobante_path)
            : null;
    }
}

