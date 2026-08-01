<?php

namespace App\Models\Inventarios;

use App\Traits\HasActiveStatus;
use App\Traits\HasInvFilterScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo InvCategoria — clasificación de productos del inventario.
 *
 * @property int         $id
 * @property string      $nombre
 * @property string|null $descripcion
 * @property int         $status
 */
class InvCategoria extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasActiveStatus;
    use HasInvFilterScopes;
    use HasSortingScopes;

    protected $table = 'inv_categorias';

    protected $guarded = ['id'];

    protected $casts = [
        'status'     => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Productos pertenecientes a esta categoría.
     *
     * @return HasMany
     */
    public function productos(): HasMany
    {
        return $this->hasMany(InvProducto::class, 'categoria_id');
    }

    /**
     * Campos permitidos para ordenamiento dinámico.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['nombre', 'status', 'created_at', 'updated_at'];
    }
}
