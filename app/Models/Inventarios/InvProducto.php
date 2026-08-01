<?php

namespace App\Models\Inventarios;

use App\Traits\HasActiveStatus;
use App\Traits\HasInvProductoFilterScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo InvProducto — catálogo de productos del inventario.
 *
 * Tipos:
 *  - simple: producto individual con stock propio.
 *  - kit:    compuesto de simples o grupos; stock calculado como el mínimo de sus componentes.
 *  - grupo:  agrupa variantes (ej. "Camisa"); no tiene stock ni precio.
 *
 * @property int         $id
 * @property string      $codigo
 * @property string      $nombre
 * @property string|null $descripcion
 * @property string      $tipo         simple|kit|grupo
 * @property string|null $imagen
 * @property int|null    $categoria_id
 * @property int|null    $unidad_medida_id
 * @property int|null    $producto_padre_id
 * @property int         $status
 */
class InvProducto extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasActiveStatus;
    use HasInvProductoFilterScopes;
    use HasSortingScopes;

    protected $table = 'inv_productos';

    protected $guarded = ['id'];

    protected $casts = [
        'status'            => 'integer',
        'categoria_id'      => 'integer',
        'unidad_medida_id'  => 'integer',
        'producto_padre_id' => 'integer',
        'punto_reorden'     => 'integer',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    /**
     * Categoría del producto.
     *
     * @return BelongsTo
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(InvCategoria::class, 'categoria_id');
    }

    /**
     * Unidad de medida del producto.
     *
     * @return BelongsTo
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(InvUnidadMedida::class, 'unidad_medida_id');
    }

    /**
     * Grupo padre cuando este producto es una variante.
     *
     * @return BelongsTo
     */
    public function productoPadre(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'producto_padre_id');
    }

    /**
     * Variantes (hijos) cuando este producto es un grupo.
     *
     * @return HasMany
     */
    public function variantes(): HasMany
    {
        return $this->hasMany(InvProducto::class, 'producto_padre_id');
    }

    /**
     * Componentes cuando este producto es un kit.
     *
     * @return HasMany
     */
    public function kitComponentes(): HasMany
    {
        return $this->hasMany(InvKitComponente::class, 'kit_id');
    }

    /**
     * Kits a los que pertenece este producto como componente.
     *
     * @return HasMany
     */
    public function enKits(): HasMany
    {
        return $this->hasMany(InvKitComponente::class, 'grupo_producto_id');
    }

    /**
     * Campos permitidos para ordenamiento dinámico.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['codigo', 'nombre', 'tipo', 'status', 'created_at', 'updated_at'];
    }
}
