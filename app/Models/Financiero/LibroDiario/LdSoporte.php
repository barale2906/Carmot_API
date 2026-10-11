<?php

namespace App\Models\Financiero\LibroDiario;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modelo LdSoporte
 *
 * Archivo que soporta un movimiento o una consignación del libro diario
 * (factura, recibo, comprobante bancario). Se guarda en el disco privado y
 * solo se descarga por el endpoint protegido.
 *
 * @property int $id
 * @property string $soportable_type
 * @property int $soportable_id
 * @property string $tipo
 * @property string $nombre_original
 * @property string $archivo_path
 * @property string|null $mime_type
 * @property int|null $tamano_bytes
 * @property int|null $usuario_id
 * @property-read LdMovimiento|LdConsignacion $soportable
 */
class LdSoporte extends Model
{
    /** Disco privado donde se guardan los archivos. */
    public const DISCO = 'local';

    /** Tipos de soporte. */
    public const TIPOS = [
        'factura' => 'Factura',
        'recibo' => 'Recibo',
        'comprobante' => 'Comprobante bancario',
        'otro' => 'Otro',
    ];

    /** Registros que admiten soportes, por el nombre que usa la API. */
    public const SOPORTABLES = [
        'movimiento' => LdMovimiento::class,
        'consignacion' => LdConsignacion::class,
    ];

    protected $table = 'ld_soportes';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'tamano_bytes' => 'integer',
    ];

    public function soportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
