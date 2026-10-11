<?php

namespace App\Models\Financiero\LibroDiario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo LdMovimientoImpuesto
 *
 * Impuesto o retención aplicado a un movimiento. Guarda nombre, efecto y
 * porcentaje vigentes al registrar, para que un cambio posterior del catálogo
 * no altere los movimientos ya registrados.
 *
 * @property int $id
 * @property int $movimiento_id
 * @property int $impuesto_id
 * @property string $nombre
 * @property int $efecto 1=Suma, 2=Resta
 * @property float $porcentaje
 * @property float $valor
 */
class LdMovimientoImpuesto extends Model
{
    protected $table = 'ld_movimiento_impuesto';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'efecto' => 'integer',
        'porcentaje' => 'decimal:3',
        'valor' => 'decimal:2',
    ];

    /**
     * Movimiento al que se aplica.
     */
    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(LdMovimiento::class, 'movimiento_id');
    }

    /**
     * Impuesto del catálogo.
     */
    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(LdImpuesto::class, 'impuesto_id')->withTrashed();
    }
}
