<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de cambios del estado académico de una matrícula.
 *
 * @property int $id
 * @property int $matricula_id
 * @property int $estado_anterior
 * @property int $estado_nuevo
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $motivo
 * @property int|null $usuario_id
 */
class MatriculaEstado extends Model
{
    protected $table = 'matricula_estados';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha'           => 'date',
        'estado_anterior' => 'integer',
        'estado_nuevo'    => 'integer',
    ];

    /**
     * Matrícula a la que pertenece el cambio.
     */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    /**
     * Usuario que registró el cambio.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
