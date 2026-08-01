<?php

namespace App\Models\Inventarios;

use App\Models\User;
use App\Traits\HasActiveStatus;
use App\Traits\HasInvFilterScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo InvAlmacen — bodega física asociada a una sede.
 *
 * Una sede puede tener múltiples almacenes; cada cajero puede tener
 * acceso a uno o varios almacenes de su sede.
 *
 * @property int         $id
 * @property string      $nombre
 * @property string|null $descripcion
 * @property int         $sede_id
 * @property int         $status
 */
class InvAlmacen extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasActiveStatus;
    use HasInvFilterScopes;
    use HasSortingScopes;

    protected $table = 'inv_almacenes';

    protected $guarded = ['id'];

    protected $casts = [
        'sede_id'    => 'integer',
        'status'     => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Sede propietaria del almacén.
     *
     * @return BelongsTo
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Configuracion\Sede::class, 'sede_id');
    }

    /**
     * Usuarios (cajeros) con acceso a este almacén.
     *
     * @return BelongsToMany
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'inv_almacen_usuario', 'almacen_id', 'user_id');
    }

    /**
     * Campos permitidos para ordenamiento dinámico.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['nombre', 'sede_id', 'status', 'created_at', 'updated_at'];
    }
}
