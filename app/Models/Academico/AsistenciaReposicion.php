<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo AsistenciaReposicion
 *
 * El estudiante notifica que tomará una clase de su grupo (origen) en otro grupo de su mismo
 * curso (destino), en la misma semana y con el mismo módulo (los mismos temas). Solo se registra:
 * no requiere aprobación. La clase de origen deja de contar como falla y el estudiante aparece
 * en la planilla de la clase de destino; si allí no asiste, esa ausencia sí cuenta.
 * Cancelar la reposición es un soft delete.
 *
 * @property int $id
 * @property int $estudiante_id Estudiante que repone la clase
 * @property int $clase_origen_id Clase de su grupo que no tomará
 * @property int $clase_destino_id Clase de otro grupo en la que la tomará
 * @property string|null $motivo Razón de la reposición
 * @property int|null $registrada_por_id Quien notificó la reposición
 * @property-read User $estudiante
 * @property-read AsistenciaClaseProgramada $claseOrigen
 * @property-read AsistenciaClaseProgramada $claseDestino
 * @property-read User|null $registradaPor
 */
class AsistenciaReposicion extends Model
{
    use SoftDeletes;

    protected $table = 'asistencia_reposiciones';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** Estudiante que repone la clase. */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /** Clase de su grupo que no tomará. */
    public function claseOrigen(): BelongsTo
    {
        return $this->belongsTo(AsistenciaClaseProgramada::class, 'clase_origen_id');
    }

    /** Clase de otro grupo en la que la tomará. */
    public function claseDestino(): BelongsTo
    {
        return $this->belongsTo(AsistenciaClaseProgramada::class, 'clase_destino_id');
    }

    /** Usuario que notificó la reposición. */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por_id');
    }

    /**
     * Reposiciones que el usuario puede ver: un alumno solo las suyas; el resto, las de grupos de
     * sus sedes o de los que es profesor (en origen o destino).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVisiblesPara($query, User $usuario)
    {
        if ($usuario->esSoloEstudiante()) {
            return $query->where('estudiante_id', $usuario->id);
        }

        $sedes = $usuario->idsSedesAccesibles();
        $delGrupo = fn ($grupo) => $grupo->where('profesor_id', $usuario->id)
            ->when(! $usuario->esSoloProfesor(), fn ($q) => $q->orWhereIn('sede_id', $sedes));

        return $query->where(fn ($q) => $q
            ->whereHas('claseDestino.grupo', $delGrupo)
            ->orWhereHas('claseOrigen.grupo', $delGrupo));
    }

    /**
     * Filtros del listado: estudiante, grupo (de origen o de destino) y rango de la fecha de la clase de destino.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeConFiltros($query, array $filtros)
    {
        return $query
            ->when(! empty($filtros['estudiante_id']), fn ($q) => $q->where('estudiante_id', $filtros['estudiante_id']))
            ->when(! empty($filtros['grupo_id']), fn ($q) => $q->where(fn ($g) => $g
                ->whereHas('claseOrigen', fn ($c) => $c->where('grupo_id', $filtros['grupo_id']))
                ->orWhereHas('claseDestino', fn ($c) => $c->where('grupo_id', $filtros['grupo_id']))))
            ->when(! empty($filtros['fecha_desde']), fn ($q) => $q->whereHas('claseDestino', fn ($c) => $c->whereDate('fecha_clase', '>=', $filtros['fecha_desde'])))
            ->when(! empty($filtros['fecha_hasta']), fn ($q) => $q->whereHas('claseDestino', fn ($c) => $c->whereDate('fecha_clase', '<=', $filtros['fecha_hasta'])));
    }
}
