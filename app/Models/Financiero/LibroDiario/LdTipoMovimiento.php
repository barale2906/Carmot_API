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
 * Modelo LdTipoMovimiento
 *
 * Catálogo de tipos de movimiento manual del libro diario (arriendo, servicios,
 * proveedores, otros ingresos…). La clase define si el movimiento es egreso u otro ingreso.
 *
 * @property int $id
 * @property string $nombre
 * @property int $clase 1=Egreso, 2=Otro ingreso
 * @property string|null $descripcion
 * @property int $status 0=Inactivo, 1=Activo
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|LdMovimiento[] $movimientos
 */
class LdTipoMovimiento extends Model
{
    use HasActiveStatus, HasFactory, HasLdCatalogoFilterScopes, HasSortingScopes, SoftDeletes;

    public const CLASE_EGRESO = 1;

    public const CLASE_INGRESO = 2;

    /** Columna enumerada que filtra HasLdCatalogoFilterScopes. */
    public const CAMPO_CLASIFICACION = 'clase';

    protected $table = 'ld_tipos_movimiento';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'clase' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Clases de movimiento manual.
     *
     * @return array<int, string>
     */
    public static function getClaseOptions(): array
    {
        return [
            self::CLASE_EGRESO => 'Egreso',
            self::CLASE_INGRESO => 'Otro ingreso',
        ];
    }

    /**
     * Movimientos registrados con este tipo.
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(LdMovimiento::class, 'tipo_movimiento_id');
    }

    /**
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['nombre', 'clase', 'status', 'created_at', 'updated_at'];
    }
}
