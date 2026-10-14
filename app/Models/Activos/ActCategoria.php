<?php

namespace App\Models\Activos;

use App\Traits\HasActCategoriaFilterScopes;
use App\Traits\HasActiveStatus;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Categoría de activos fijos (herramienta, equipo de cómputo, mobiliario...).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property int $status 0 Inactivo | 1 Activo
 */
class ActCategoria extends Model
{
    use HasFactory, SoftDeletes, HasActiveStatus, HasActCategoriaFilterScopes, HasSortingScopes;

    protected $table = 'act_categorias';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Activos de la categoría.
     */
    public function activos(): HasMany
    {
        return $this->hasMany(ActActivo::class, 'categoria_id');
    }

    /**
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['nombre', 'status', 'created_at', 'updated_at'];
    }
}
