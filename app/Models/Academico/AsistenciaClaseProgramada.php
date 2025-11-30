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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo AsistenciaClaseProgramada
 *
 * Representa una clase programada para el registro de asistencia.
 * Las clases programadas se crean manualmente o automáticamente basándose en los horarios del grupo.
 *
 * @property int $id Identificador único de la clase programada
 * @property int $grupo_id ID del grupo
 * @property int $ciclo_id ID del ciclo
 * @property \Carbon\Carbon $fecha_clase Fecha de la clase
 * @property string $hora_inicio Hora de inicio de la clase
 * @property string $hora_fin Hora de fin de la clase
 * @property float $duracion_horas Duración de la clase en horas
 * @property string $estado Estado de la clase (programada, dictada, cancelada, reprogramada)
 * @property string|null $observaciones Observaciones adicionales
 * @property int $creado_por_id ID del usuario que creó la clase programada
 * @property \Carbon\Carbon $fecha_programacion Fecha en que se programó la clase
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Academico\Grupo $grupo Grupo al que pertenece
 * @property-read \App\Models\Academico\Ciclo $ciclo Ciclo al que pertenece
 * @property-read \App\Models\User $creadoPor Usuario que creó la clase programada
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Academico\Asistencia[] $asistencias Asistencias registradas para esta clase
 */
class AsistenciaClaseProgramada extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'asistencia_clases_programadas';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'fecha_clase' => 'date',
        'hora_inicio' => 'string',
        'hora_fin' => 'string',
        'duracion_horas' => 'decimal:2',
        'estado' => 'string',
        'fecha_programacion' => 'datetime',
    ];

    /**
     * Relación con Grupo (muchos a uno).
     * Una clase programada pertenece a un grupo.
     *
     * @return BelongsTo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /**
     * Relación con Ciclo (muchos a uno).
     * Una clase programada pertenece a un ciclo.
     *
     * @return BelongsTo
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    /**
     * Relación con User (muchos a uno).
     * Una clase programada fue creada por un usuario.
     *
     * @return BelongsTo
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /**
     * Relación con Asistencia (uno a muchos).
     * Una clase programada puede tener múltiples asistencias.
     *
     * @return HasMany
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'clase_programada_id');
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
     * Scope para filtrar por fecha.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fecha
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFecha($query, $fecha)
    {
        return $query->whereDate('fecha_clase', $fecha);
    }

    /**
     * Scope para filtrar clases dictadas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDictadas($query)
    {
        return $query->where('estado', 'dictada');
    }

    /**
     * Scope para filtrar clases programadas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProgramadas($query)
    {
        return $query->where('estado', 'programada');
    }

    /**
     * Scope para filtrar por ciclos activos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCiclosActivos($query)
    {
        $hoy = now()->toDateString();
        return $query->whereHas('ciclo', function ($q) use ($hoy) {
            $q->where('status', 1)
              ->where('fecha_inicio', '<=', $hoy)
              ->where(function ($q2) use ($hoy) {
                  $q2->whereNull('fecha_fin')
                      ->orWhere('fecha_fin', '>=', $hoy);
              });
        });
    }

    /**
     * Calcula la duración en horas basada en hora_inicio y hora_fin.
     *
     * @return float
     */
    public function calcularDuracionHoras(): float
    {
        if (!$this->hora_inicio || !$this->hora_fin) {
            return 0.0;
        }

        $inicio = \Carbon\Carbon::parse($this->hora_inicio);
        $fin = \Carbon\Carbon::parse($this->hora_fin);

        return round($inicio->diffInMinutes($fin) / 60, 2);
    }

    /**
     * Verifica si la clase está en el rango de fechas del grupo en el ciclo.
     *
     * @return bool
     */
    public function estaEnRangoFechasGrupo(): bool
    {
        if (!$this->grupo || !$this->ciclo) {
            return false;
        }

        $pivot = $this->ciclo->grupos()
            ->where('grupos.id', $this->grupo_id)
            ->first();

        if (!$pivot || !$pivot->pivot->fecha_inicio_grupo || !$pivot->pivot->fecha_fin_grupo) {
            return false;
        }

        $fechaClase = \Carbon\Carbon::parse($this->fecha_clase);
        $fechaInicio = \Carbon\Carbon::parse($pivot->pivot->fecha_inicio_grupo);
        $fechaFin = \Carbon\Carbon::parse($pivot->pivot->fecha_fin_grupo);

        return $fechaClase->between($fechaInicio, $fechaFin);
    }

    /**
     * Verifica si se puede registrar asistencia para esta clase.
     *
     * @return bool
     */
    public function puedeRegistrarAsistencia(): bool
    {
        return $this->estado === 'programada' || $this->estado === 'dictada';
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'fecha_clase',
            'hora_inicio',
            'hora_fin',
            'duracion_horas',
            'estado',
            'grupo_id',
            'ciclo_id',
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
            'grupo',
            'ciclo',
            'creadoPor',
            'asistencias'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['grupo', 'ciclo'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array
     */
    protected function getCountableRelations(): array
    {
        return ['asistencias'];
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
            ->when(isset($filters['grupo_id']) && $filters['grupo_id'], function ($q) use ($filters) {
                return $q->byGrupo($filters['grupo_id']);
            })
            ->when(isset($filters['ciclo_id']) && $filters['ciclo_id'], function ($q) use ($filters) {
                return $q->byCiclo($filters['ciclo_id']);
            })
            ->when(isset($filters['fecha_clase']) && $filters['fecha_clase'], function ($q) use ($filters) {
                return $q->byFecha($filters['fecha_clase']);
            })
            ->when(isset($filters['estado']) && $filters['estado'], function ($q) use ($filters) {
                return $q->where('estado', $filters['estado']);
            })
            ->when(isset($filters['fecha_desde']) && $filters['fecha_desde'], function ($q) use ($filters) {
                return $q->whereDate('fecha_clase', '>=', $filters['fecha_desde']);
            })
            ->when(isset($filters['fecha_hasta']) && $filters['fecha_hasta'], function ($q) use ($filters) {
                return $q->whereDate('fecha_clase', '<=', $filters['fecha_hasta']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
