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
 * Modelo InvUnidadMedida — unidades como Unidad, Par, Caja, Docena, etc.
 *
 * @property int         $id
 * @property string      $nombre
 * @property string|null $abreviatura
 * @property int         $status
 */
class InvUnidadMedida extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasActiveStatus;
    use HasInvFilterScopes;
    use HasSortingScopes;

    protected $table = 'inv_unidades_medida';

    protected $guarded = ['id'];

    protected $casts = [
        'status'     => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Productos con esta unidad de medida.
     *
     * @return HasMany
     */
    public function productos(): HasMany
    {
        return $this->hasMany(InvProducto::class, 'unidad_medida_id');
    }

    /**
     * Campos permitidos para ordenamiento dinámico.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['nombre', 'abreviatura', 'status', 'created_at', 'updated_at'];
    }
}
