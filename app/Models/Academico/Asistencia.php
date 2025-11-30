<?php

namespace App\Models\Academico;

use App\Models\User;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Asistencia
 *
 * Representa un registro de asistencia de un estudiante a una clase programada.
 * Las asistencias se registran para cada estudiante en cada clase programada.
 *
 * @property int $id Identificador único de la asistencia
 * @property int $estudiante_id ID del estudiante
 * @property int $clase_programada_id ID de la clase programada
 * @property int $grupo_id ID del grupo
 * @property int $ciclo_id ID del ciclo
 * @property int $modulo_id ID del módulo
 * @property int $curso_id ID del curso
 * @property string $estado Estado de la asistencia (presente, ausente, justificado, tardanza)
 * @property string|null $hora_registro Hora de registro de la asistencia
 * @property string|null $observaciones Observaciones adicionales
 * @property int $registrado_por_id ID del usuario que registró la asistencia
 * @property \Carbon\Carbon $fecha_registro Fecha de registro
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\User $estudiante Estudiante al que pertenece
 * @property-read \App\Models\Academico\AsistenciaClaseProgramada $claseProgramada Clase programada a la que pertenece
 * @property-read \App\Models\Academico\Grupo $grupo Grupo al que pertenece
 * @property-read \App\Models\Academico\Ciclo $ciclo Ciclo al que pertenece
 * @property-read \App\Models\Academico\Modulo $modulo Módulo al que pertenece
 * @property-read \App\Models\Academico\Curso $curso Curso al que pertenece
 * @property-read \App\Models\User $registradoPor Usuario que registró la asistencia
 */
class Asistencia extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'estado' => 'string',
        'hora_registro' => 'string',
        'fecha_registro' => 'datetime',
    ];

    /**
     * Relación con User (muchos a uno) - Estudiante.
     * Una asistencia pertenece a un estudiante.
     *
     * @return BelongsTo
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /**
     * Relación con AsistenciaClaseProgramada (muchos a uno).
     * Una asistencia pertenece a una clase programada.
     *
     * @return BelongsTo
     */
    public function claseProgramada(): BelongsTo
    {
        return $this->belongsTo(AsistenciaClaseProgramada::class, 'clase_programada_id');
    }

    /**
     * Relación con Grupo (muchos a uno).
     * Una asistencia pertenece a un grupo.
     *
     * @return BelongsTo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /**
     * Relación con Ciclo (muchos a uno).
     * Una asistencia pertenece a un ciclo.
     *
     * @return BelongsTo
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    /**
     * Relación con Modulo (muchos a uno).
     * Una asistencia pertenece a un módulo.
     *
     * @return BelongsTo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Relación con Curso (muchos a uno).
     * Una asistencia pertenece a un curso.
     *
     * @return BelongsTo
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Relación con User (muchos a uno) - Usuario que registró.
     * Una asistencia fue registrada por un usuario.
     *
     * @return BelongsTo
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    /**
     * Scope para filtrar por estudiante.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $estudianteId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByEstudiante($query, $estudianteId)
    {
        return $query->where('estudiante_id', $estudianteId);
    }

    /**
     * Scope para filtrar por grupo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $grupoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByGrupo($query, $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }

    /**
     * Scope para filtrar por ciclo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $cicloId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCiclo($query, $cicloId)
    {
        return $query->where('ciclo_id', $cicloId);
    }

    /**
     * Scope para filtrar por curso.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $cursoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCurso($query, $cursoId)
    {
        return $query->where('curso_id', $cursoId);
    }

    /**
     * Scope para filtrar asistencias presentes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePresentes($query)
    {
        return $query->where('estado', 'presente');
    }

    /**
     * Scope para filtrar asistencias ausentes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAusentes($query)
    {
        return $query->where('estado', 'ausente');
    }

    /**
     * Scope para filtrar asistencias justificadas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeJustificadas($query)
    {
        return $query->where('estado', 'justificado');
    }

    /**
     * Verifica si la asistencia es presente.
     *
     * @return bool
     */
    public function esPresente(): bool
    {
        return $this->estado === 'presente' || $this->estado === 'tardanza';
    }

    /**
     * Verifica si la asistencia está justificada.
     *
     * @return bool
     */
    public function esJustificada(): bool
    {
        return $this->estado === 'justificado';
    }

    /**
     * Verifica si la asistencia cuenta para el mínimo requerido.
     * Las asistencias presentes y justificadas cuentan, las ausentes no.
     *
     * @return bool
     */
    public function contarParaMinimo(): bool
    {
        return $this->esPresente() || $this->esJustificada();
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'estado',
            'fecha_registro',
            'hora_registro',
            'estudiante_id',
            'grupo_id',
            'ciclo_id',
            'curso_id',
            'modulo_id',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     *
     * @return array
     */
    protected function getAllowedRelations(): array
    {
        return [
            'estudiante',
            'claseProgramada',
            'grupo',
            'ciclo',
            'modulo',
            'curso',
            'registradoPor'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['estudiante', 'claseProgramada', 'grupo', 'ciclo'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array
     */
    protected function getCountableRelations(): array
    {
        return [];
    }

    /**
     * Scope para aplicar múltiples filtros de manera dinámica.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(isset($filters['estudiante_id']) && $filters['estudiante_id'], function ($q) use ($filters) {
                return $q->byEstudiante($filters['estudiante_id']);
            })
            ->when(isset($filters['grupo_id']) && $filters['grupo_id'], function ($q) use ($filters) {
                return $q->byGrupo($filters['grupo_id']);
            })
            ->when(isset($filters['ciclo_id']) && $filters['ciclo_id'], function ($q) use ($filters) {
                return $q->byCiclo($filters['ciclo_id']);
            })
            ->when(isset($filters['curso_id']) && $filters['curso_id'], function ($q) use ($filters) {
                return $q->byCurso($filters['curso_id']);
            })
            ->when(isset($filters['modulo_id']) && $filters['modulo_id'], function ($q) use ($filters) {
                return $q->where('modulo_id', $filters['modulo_id']);
            })
            ->when(isset($filters['clase_programada_id']) && $filters['clase_programada_id'], function ($q) use ($filters) {
                return $q->where('clase_programada_id', $filters['clase_programada_id']);
            })
            ->when(isset($filters['estado']) && $filters['estado'], function ($q) use ($filters) {
                return $q->where('estado', $filters['estado']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
