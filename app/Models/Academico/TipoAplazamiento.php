<?php

namespace App\Models\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo TipoAplazamiento
 *
 * Catálogo de razones por las que puede aplazarse un ciclo académico.
 *
 * @property int         $id
 * @property string      $nombre
 * @property string|null $descripcion
 * @property int         $status
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Aplazamiento> $aplazamientos
 */
class TipoAplazamiento extends Model
{
    use HasFactory, SoftDeletes, HasGenericScopes, HasFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus;

    protected $table = 'tipo_aplazamientos';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Aplazamientos que usan este tipo.
     */
    public function aplazamientos(): HasMany
    {
        return $this->hasMany(Aplazamiento::class);
    }

    /**
     * Scope de búsqueda por nombre.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
    }

    protected function getAllowedSortFields(): array
    {
        return ['nombre', 'status', 'created_at', 'updated_at'];
    }

    protected function getAllowedRelations(): array
    {
        return ['aplazamientos'];
    }

    protected function getDefaultRelations(): array
    {
        return [];
    }

    protected function getCountableRelations(): array
    {
        return ['aplazamientos'];
    }
}
