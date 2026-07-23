<?php

namespace App\Models\Configuracion;

use App\Traits\HasActiveStatus;
use App\Traits\HasEpsFilterScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Eps
 *
 * Representa una Entidad Promotora de Salud registrada en el sistema.
 * Una EPS puede estar asociada a múltiples matrículas.
 *
 * @property int $id Identificador único
 * @property string $nombre Razón social de la EPS
 * @property string|null $direccion Dirección de la EPS
 * @property int $status 0: Inactivo, 1: Activo
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación lógica
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Academico\Matricula[] $matriculas Matrículas asociadas
 */
class Eps extends Model
{
    use HasFactory, SoftDeletes, HasActiveStatus, HasEpsFilterScopes, HasSortingScopes;

    protected $table = 'eps';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Relación con Matricula (uno a muchos).
     * Una EPS puede tener múltiples matrículas asociadas.
     *
     * @return HasMany
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(\App\Models\Academico\Matricula::class);
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'direccion',
            'status',
            'created_at',
            'updated_at',
        ];
    }
}
