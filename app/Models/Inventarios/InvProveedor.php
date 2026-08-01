<?php

namespace App\Models\Inventarios;

use App\Traits\HasActiveStatus;
use App\Traits\HasInvFilterScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo InvProveedor — estructura base de proveedores (CRUD completo en Sprint 4).
 *
 * @property int         $id
 * @property string      $razon_social
 * @property string|null $nit
 * @property string|null $contacto
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $direccion
 * @property string|null $notas
 * @property int         $status
 */
class InvProveedor extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasActiveStatus;
    use HasInvFilterScopes;
    use HasSortingScopes;

    protected $table = 'inv_proveedores';

    protected $guarded = ['id'];

    protected $casts = [
        'status'     => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Campos de búsqueda: razón social o NIT.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('razon_social', 'like', '%' . $search . '%')
              ->orWhere('nit', 'like', '%' . $search . '%');
        });
    }

    /**
     * Campos permitidos para ordenamiento dinámico.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['razon_social', 'nit', 'status', 'created_at', 'updated_at'];
    }
}
