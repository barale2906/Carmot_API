<?php

namespace App\Models\Financiero\LibroDiario;

use App\Traits\Financiero\HasLdCatalogoFilterScopes;
use App\Traits\HasActiveStatus;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo LdImpuesto
 *
 * Catálogo de impuestos y retenciones aplicables a los movimientos del libro diario.
 * Los que suman (IVA, impoconsumo) se agregan al subtotal; los que restan
 * (retención en la fuente, ReteICA, ReteIVA) se descuentan del total a pagar.
 *
 * @property int $id
 * @property string $nombre
 * @property float $porcentaje Porcentaje sugerido sobre el subtotal
 * @property int $efecto 1=Suma, 2=Resta
 * @property int $status 0=Inactivo, 1=Activo
 */
class LdImpuesto extends Model
{
    use HasActiveStatus, HasFactory, HasLdCatalogoFilterScopes, HasSortingScopes, SoftDeletes;

    public const EFECTO_SUMA = 1;

    public const EFECTO_RESTA = 2;

    /** Columna enumerada que filtra HasLdCatalogoFilterScopes. */
    public const CAMPO_CLASIFICACION = 'efecto';

    protected $table = 'ld_impuestos';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'porcentaje' => 'decimal:3',
        'efecto' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Efectos posibles del impuesto sobre el total.
     *
     * @return array<int, string>
     */
    public static function getEfectoOptions(): array
    {
        return [
            self::EFECTO_SUMA => 'Suma (impuesto)',
            self::EFECTO_RESTA => 'Resta (retención)',
        ];
    }

    /**
     * Aplicaciones del impuesto en movimientos.
     */
    public function aplicaciones(): HasMany
    {
        return $this->hasMany(LdMovimientoImpuesto::class, 'impuesto_id');
    }

    /**
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['nombre', 'porcentaje', 'efecto', 'status', 'created_at', 'updated_at'];
    }
}
