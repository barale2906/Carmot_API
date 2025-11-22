<?php

namespace App\Models\Academico;

use App\Models\Configuracion\Sede;
use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasJornadaStatus;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Programacion
 *
 * Representa una programación académica en el sistema.
 * Una programación pertenece a un curso y una sede, y puede tener múltiples grupos
 * con fechas específicas de inicio y fin para cada grupo.
 *
 * @property int $id Identificador único de la programación
 * @property int $curso_id ID del curso al que pertenece
 * @property int $sede_id ID de la sede donde se realiza la programación
 * @property string $nombre Nombre de la programación
 * @property string|null $descripcion Descripción de la programación
 * @property \Carbon\Carbon $fecha_inicio Fecha de inicio de la programación
 * @property \Carbon\Carbon $fecha_fin Fecha de fin de la programación
 * @property int $registrados Cantidad de estudiantes registrados en la programación
 * @property int $jornada Jornada de la programación (0: mañana, 1: tarde, 2: noche, 3: fin de semana mañana, 4: fin de semana tarde)
 * @property int $status Estado de la programación (1: activo, 0: inactivo)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Academico\Curso $curso Curso al que pertenece
 * @property-read \App\Models\Configuracion\Sede $sede Sede donde se realiza la programación
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Academico\Grupo[] $grupos Grupos asociados a la programación
 */
class Programacion extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus, HasJornadaStatus;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'registrados' => 'integer',
        'jornada' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Relación con Curso (muchos a uno).
     * Una programación pertenece a un curso.
     *
     * @return BelongsTo
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Relación con Sede (muchos a uno).
     * Una programación pertenece a una sede.
     *
     * @return BelongsTo
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Relación con Grupo (muchos a muchos).
     * Una programación puede tener múltiples grupos.
     * La tabla pivot incluye las fechas de inicio y fin específicas de cada grupo.
     *
     * @return BelongsToMany
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'programacion_grupo')
            ->withPivot(['fecha_inicio_grupo', 'fecha_fin_grupo'])
            ->withTimestamps();
    }

    /**
     * Scope para filtrar por búsqueda de nombre.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
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
     * Scope para filtrar por sede.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $sedeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    /**
     * Scope para filtrar por jornada.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $jornada
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByJornada($query, $jornada)
    {
        return $query->where('jornada', $jornada);
    }

    /**
     * Scope para filtrar por rango de fechas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFechaRange($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_inicio', [$fechaInicio, $fechaFin])
            ->orWhereBetween('fecha_fin', [$fechaInicio, $fechaFin])
            ->orWhere(function ($q) use ($fechaInicio, $fechaFin) {
                $q->where('fecha_inicio', '<=', $fechaInicio)
                  ->where('fecha_fin', '>=', $fechaFin);
            });
    }

    /**
     * Scope para filtrar por fecha de inicio.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fecha
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFechaInicio($query, $fecha)
    {
        return $query->where('fecha_inicio', $fecha);
    }

    /**
     * Scope para filtrar por fecha de fin.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fecha
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFechaFin($query, $fecha)
    {
        return $query->where('fecha_fin', $fecha);
    }

    /**
     * Scope para filtrar programaciones activas en una fecha específica.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $fecha Fecha a verificar (por defecto: hoy)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActivasEnFecha($query, $fecha = null)
    {
        $fecha = $fecha ? \Carbon\Carbon::parse($fecha) : now();
        return $query->where('fecha_inicio', '<=', $fecha)
            ->where('fecha_fin', '>=', $fecha);
    }

    /**
     * Scope para aplicar múltiples filtros de manera dinámica (sobrescribe el del trait).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(isset($filters['search']) && $filters['search'], function ($q) use ($filters) {
                return $q->search($filters['search']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== null, function ($q) use ($filters) {
                return $q->byStatus($filters['status']);
            })
            ->when(isset($filters['curso_id']) && $filters['curso_id'], function ($q) use ($filters) {
                return $q->byCurso($filters['curso_id']);
            })
            ->when(isset($filters['sede_id']) && $filters['sede_id'], function ($q) use ($filters) {
                return $q->bySede($filters['sede_id']);
            })
            ->when(isset($filters['jornada']) && $filters['jornada'] !== null, function ($q) use ($filters) {
                return $q->byJornada($filters['jornada']);
            })
            ->when(isset($filters['fecha_inicio']) && $filters['fecha_inicio'], function ($q) use ($filters) {
                return $q->byFechaInicio($filters['fecha_inicio']);
            })
            ->when(isset($filters['fecha_fin']) && $filters['fecha_fin'], function ($q) use ($filters) {
                return $q->byFechaFin($filters['fecha_fin']);
            })
            ->when(isset($filters['fecha_inicio_range']) && isset($filters['fecha_fin_range']), function ($q) use ($filters) {
                return $q->byFechaRange($filters['fecha_inicio_range'], $filters['fecha_fin_range']);
            })
            ->when(isset($filters['activas_en_fecha']) && $filters['activas_en_fecha'], function ($q) use ($filters) {
                return $q->activasEnFecha($filters['activas_en_fecha']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
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
            'fecha_inicio',
            'fecha_fin',
            'registrados',
            'jornada',
            'status',
            'curso_id',
            'sede_id',
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
            'curso',
            'sede',
            'grupos'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['curso', 'sede', 'grupos'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array
     */
    protected function getCountableRelations(): array
    {
        return ['grupos'];
    }


    /**
     * Obtiene la duración de la programación en días.
     *
     * @return int
     */
    public function getDuracionDiasAttribute(): int
    {
        if (!$this->fecha_inicio || !$this->fecha_fin) {
            return 0;
        }

        return $this->fecha_inicio->diffInDays($this->fecha_fin);
    }

    /**
     * Verifica si la programación está en curso.
     *
     * @return bool
     */
    public function getEnCursoAttribute(): bool
    {
        $ahora = now();
        return $this->fecha_inicio &&
               $this->fecha_inicio <= $ahora &&
               (!$this->fecha_fin || $this->fecha_fin >= $ahora);
    }

    /**
     * Verifica si la programación ha finalizado.
     *
     * @return bool
     */
    public function getFinalizadaAttribute(): bool
    {
        return $this->fecha_fin && $this->fecha_fin < now();
    }

    /**
     * Verifica si la programación está por iniciar.
     *
     * @return bool
     */
    public function getPorIniciarAttribute(): bool
    {
        return $this->fecha_inicio && $this->fecha_inicio > now();
    }

    /**
     * Obtiene el total de horas de la programación basado en los módulos de los grupos.
     *
     * @return int
     */
    public function getTotalHorasAttribute(): int
    {
        return $this->grupos->sum(function ($grupo) {
            return $grupo->modulo->duracion ?? 0;
        });
    }

    /**
     * Obtiene las horas por semana de la programación.
     *
     * @return int
     */
    public function getHorasPorSemanaAttribute(): int
    {
        return $this->grupos->sum(function ($grupo) {
            return $grupo->getTotalHorasSemanaAttribute();
        });
    }

    /**
     * Asigna grupos a la programación con fechas específicas.
     *
     * @param array $gruposConFechas Array de grupos con sus fechas: [['grupo_id' => 1, 'fecha_inicio_grupo' => '2025-01-01', 'fecha_fin_grupo' => '2025-01-31'], ...]
     * @return void
     */
    public function asignarGruposConFechas(array $gruposConFechas): void
    {
        $datosPivot = [];

        foreach ($gruposConFechas as $grupo) {
            $datosPivot[$grupo['grupo_id']] = [
                'fecha_inicio_grupo' => $grupo['fecha_inicio_grupo'] ?? null,
                'fecha_fin_grupo' => $grupo['fecha_fin_grupo'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        $this->grupos()->sync($datosPivot);
    }

    /**
     * Actualiza las fechas de un grupo específico en la programación.
     *
     * @param int $grupoId ID del grupo
     * @param string|null $fechaInicio Fecha de inicio del grupo
     * @param string|null $fechaFin Fecha de fin del grupo
     * @return bool
     */
    public function actualizarFechasGrupo(int $grupoId, ?string $fechaInicio = null, ?string $fechaFin = null): bool
    {
        $datos = ['updated_at' => now()];

        if ($fechaInicio !== null) {
            $datos['fecha_inicio_grupo'] = $fechaInicio;
        }

        if ($fechaFin !== null) {
            $datos['fecha_fin_grupo'] = $fechaFin;
        }

        return $this->grupos()->updateExistingPivot($grupoId, $datos) > 0;
    }

    /**
     * Obtiene información detallada del cronograma de la programación.
     *
     * @return array
     */
    public function getCronogramaAttribute(): array
    {
        $cronograma = [];

        foreach ($this->grupos as $grupo) {
            $cronograma[] = [
                'grupo_id' => $grupo->id,
                'grupo_nombre' => $grupo->nombre,
                'modulo' => [
                    'id' => $grupo->modulo->id ?? null,
                    'nombre' => $grupo->modulo->nombre ?? null,
                    'duracion' => $grupo->modulo->duracion ?? 0,
                ],
                'fecha_inicio_grupo' => $grupo->pivot->fecha_inicio_grupo,
                'fecha_fin_grupo' => $grupo->pivot->fecha_fin_grupo,
                'horas_por_semana' => $grupo->getTotalHorasSemanaAttribute(),
                'semanas_estimadas' => $grupo->getTotalHorasSemanaAttribute() > 0 && $grupo->pivot->fecha_inicio_grupo && $grupo->pivot->fecha_fin_grupo
                    ? \Carbon\Carbon::parse($grupo->pivot->fecha_inicio_grupo)->diffInWeeks(\Carbon\Carbon::parse($grupo->pivot->fecha_fin_grupo))
                    : 0
            ];
        }

        return $cronograma;
    }
}
