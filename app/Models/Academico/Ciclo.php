<?php

namespace App\Models\Academico;

use App\Models\Configuracion\Sede;
use App\Services\Academico\CalendarioGrupoService;
use App\Services\Academico\CalendarioLaboralService;
use App\Traits\HasActiveStatus;
use App\Traits\HasCicloFilterScopes;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Ciclo
 *
 * Representa un ciclo académico en el sistema.
 * Un ciclo pertenece a una sede y un curso, y puede tener múltiples grupos.
 *
 * @property int $id Identificador único del ciclo
 * @property string $nombre Nombre del ciclo
 * @property string $descripcion Descripción del ciclo
 * @property int $sede_id ID de la sede a la que pertenece
 * @property int $curso_id ID del curso al que pertenece
 * @property int|null $inscritos Cantidad de estudiantes inscritos al ciclo
 * @property \Carbon\Carbon|null $fecha_inicio Fecha de inicio del ciclo
 * @property \Carbon\Carbon|null $fecha_fin Fecha fin: calculada (automática) o fijada por el usuario (modo fijo)
 * @property \Carbon\Carbon|null $fecha_fin_teorica Fecha en que terminaría dictando el 100 % de las horas
 * @property bool $fecha_fin_automatica true: la fecha fin se calcula; false: fecha fin fija y el contenido se ajusta
 * @property float|null $factor_ajuste Proporción de horas dictadas en modo fijo (1 = completo; null = no aplica o no cabe)
 * @property string|null $advertencia_ajuste Advertencia del último cálculo (factor bajo el umbral, fecha insuficiente...)
 * @property \Carbon\Carbon|null $notas_cerradas_at Fecha de cierre de notas (null = registro de notas abierto)
 * @property int|null $notas_cerradas_por_id Usuario que cerró las notas
 * @property int $status Estado del ciclo (1: Activo, 0: Inactivo)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Configuracion\Sede $sede Sede a la que pertenece
 * @property-read \App\Models\Academico\Curso $curso Curso al que pertenece
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Academico\Grupo[] $grupos Grupos asociados al ciclo
 * @property-read \App\Models\User|null $notasCerradasPor Usuario que cerró las notas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Academico\CicloCierreNota[] $cierresNotas Historial de cierres y reaperturas de notas
 */
class Ciclo extends Model
{
    use HasFactory, SoftDeletes, HasGenericScopes, HasFilterScopes, HasCicloFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus {
        HasCicloFilterScopes::scopeWithFilters insteadof HasFilterScopes;
    }

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_fin_teorica' => 'date',
        'duracion_dias' => 'integer',
        'fecha_fin_automatica' => 'boolean',
        'factor_ajuste' => 'float',
        'inscritos' => 'integer',
        'notas_cerradas_at' => 'datetime',
    ];

    /**
     * Relación con Sede (muchos a uno).
     * Un ciclo pertenece a una sede.
     *
     * @return BelongsTo
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Relación con Curso (muchos a uno).
     * Un ciclo pertenece a un curso.
     *
     * @return BelongsTo
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Relación con Grupo (muchos a muchos).
     * Un ciclo puede tener múltiples grupos.
     *
     * @return BelongsToMany
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'ciclo_grupo')
            ->withPivot(['orden', 'fecha_inicio_grupo', 'fecha_fin_grupo', 'ciclo_origen_id', 'horas_planeadas'])
            ->withTimestamps()
            ->orderBy('ciclo_grupo.orden');
    }

    /**
     * Relación con AsistenciaClaseProgramada (uno a muchos).
     * Un ciclo puede tener múltiples clases programadas.
     *
     * @return HasMany
     */
    public function clasesProgramadas(): HasMany
    {
        return $this->hasMany(AsistenciaClaseProgramada::class);
    }

    /**
     * Relación con Asistencia (uno a muchos).
     * Un ciclo puede tener múltiples asistencias registradas.
     *
     * @return HasMany
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Matrículas del ciclo (uno a muchos).
     *
     * @return HasMany
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * Usuario que cerró las notas del ciclo.
     *
     * @return BelongsTo
     */
    public function notasCerradasPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'notas_cerradas_por_id');
    }

    /**
     * Historial de cierres y reaperturas de notas, del más reciente al más antiguo.
     *
     * @return HasMany
     */
    public function cierresNotas(): HasMany
    {
        return $this->hasMany(CicloCierreNota::class)->latest('id');
    }

    /**
     * Indica si las notas del ciclo están cerradas.
     *
     * @return bool
     */
    public function notasCerradas(): bool
    {
        return $this->notas_cerradas_at !== null;
    }

    /**
     * Scope de ciclos con las notas cerradas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeConNotasCerradas($query)
    {
        return $query->whereNotNull('notas_cerradas_at');
    }

    /**
     * Historial de aplazamientos del ciclo.
     */
    public function aplazamientos(): HasMany
    {
        return $this->hasMany(Aplazamiento::class)->orderBy('created_at', 'desc');
    }

    /**
     * Aplazamiento activo (estado Pendiente), si existe.
     */
    public function aplazamientoActivo(): HasMany
    {
        return $this->hasMany(Aplazamiento::class)->where('estado', 0);
    }

    /**
     * Scope para filtrar por búsqueda de nombre.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'descripcion',
            'status',
            'sede_id',
            'curso_id',
            'inscritos',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     */
    protected function getAllowedRelations(): array
    {
        return [
            'sede',
            'curso',
            'grupos',
            'clasesProgramadas',
            'asistencias',
            'aplazamientos',
            'aplazamientoActivo',
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     */
    protected function getDefaultRelations(): array
    {
        return ['sede', 'curso', 'grupos'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     */
    protected function getCountableRelations(): array
    {
        return ['grupos'];
    }

    /**
     * Recalcula las fechas de los grupos (pivot) y la fecha fin cuando el
     * cálculo automático está habilitado. Delega en CalendarioGrupoService,
     * que considera horarios, sede y días no laborables.
     * No persiste el ciclo: quien lo llama decide cuándo guardar.
     */
    public function actualizarFechaFin(): void
    {
        if (! $this->fecha_fin_automatica || ! $this->fecha_inicio) {
            return;
        }

        $fechaFinCalculada = app(CalendarioGrupoService::class)->calcularYAsignarFechas($this);

        if ($fechaFinCalculada) {
            $this->fecha_fin     = $fechaFinCalculada;
            $this->duracion_dias = $this->fecha_inicio->diffInDays($fechaFinCalculada);
        }
    }

    /**
     * Obtiene la duración estimada del ciclo en días.
     *
     * @return int
     */
    public function getDuracionEstimadaAttribute(): int
    {
        if ($this->duracion_dias) {
            return $this->duracion_dias;
        }

        if ($this->fecha_inicio && $this->fecha_fin) {
            return $this->fecha_inicio->diffInDays($this->fecha_fin);
        }

        return 0;
    }

    /**
     * Obtiene el total de horas del ciclo basado en los módulos de los grupos.
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
     * Obtiene las horas por semana del ciclo.
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
     * Verifica si el ciclo está en curso.
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
     * Verifica si el ciclo ha finalizado.
     *
     * @return bool
     */
    public function getFinalizadoAttribute(): bool
    {
        return $this->fecha_fin && $this->fecha_fin < now();
    }

    /**
     * Verifica si el ciclo está por iniciar.
     *
     * @return bool
     */
    public function getPorIniciarAttribute(): bool
    {
        return $this->fecha_inicio && $this->fecha_inicio > now();
    }

    /**
     * Asigna grupos al ciclo con orden específico.
     *
     * @param array $gruposConOrden Array de grupos con su orden: [['grupo_id' => 1, 'orden' => 1], ...]
     * @return void
     */
    public function asignarGruposConOrden(array $gruposConOrden): void
    {
        $datosPivot = [];

        foreach ($gruposConOrden as $grupo) {
            $datosPivot[$grupo['grupo_id']] = [
                'orden' => $grupo['orden'],
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        $this->grupos()->sync($datosPivot);
    }

    /**
     * Actualiza el orden de un grupo específico.
     *
     * @param int $grupoId ID del grupo
     * @param int $nuevoOrden Nuevo orden del grupo
     * @return bool
     */
    public function actualizarOrdenGrupo(int $grupoId, int $nuevoOrden): bool
    {
        return $this->grupos()->updateExistingPivot($grupoId, [
            'orden' => $nuevoOrden,
            'updated_at' => now()
        ]) > 0;
    }

    /**
     * Reordena todos los grupos del ciclo.
     *
     * @param array $nuevoOrden Array con los IDs de grupos en el nuevo orden
     * @return void
     */
    public function reordenarGrupos(array $nuevoOrden): void
    {
        foreach ($nuevoOrden as $index => $grupoId) {
            $this->grupos()->updateExistingPivot($grupoId, [
                'orden' => $index + 1,
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Obtiene el siguiente orden disponible para un nuevo grupo.
     *
     * @return int
     */
    public function getSiguienteOrden(): int
    {
        $maxOrden = $this->grupos()->max('ciclo_grupo.orden');
        return ($maxOrden ?? 0) + 1;
    }

    /**
     * Obtiene información detallada del cronograma del ciclo.
     * Incluye las sesiones laborables reales de cada grupo dentro de su rango
     * y los días no laborables que se omitieron.
     *
     * @return array
     */
    public function getCronogramaAttribute(): array
    {
        $gruposOrdenados   = $this->grupos()->with(['modulo', 'horarios'])->orderBy('ciclo_grupo.orden')->get();
        $calendarioLaboral = app(CalendarioLaboralService::class);
        $cronograma        = [];

        foreach ($gruposOrdenados as $grupo) {
            $sesiones = 0;
            $diasOmitidos = [];

            if ($grupo->pivot->fecha_inicio_grupo && $grupo->pivot->fecha_fin_grupo) {
                $sesionesGrupo = $calendarioLaboral->recorrerSesiones(
                    $grupo,
                    \Carbon\Carbon::parse($grupo->pivot->fecha_inicio_grupo),
                    \Carbon\Carbon::parse($grupo->pivot->fecha_fin_grupo)
                );

                foreach ($sesionesGrupo as $sesion) {
                    if ($sesion['no_laborable'] === null) {
                        $sesiones++;
                    } else {
                        $fecha = $sesion['fecha']->toDateString();
                        $diasOmitidos[$fecha] = ['fecha' => $fecha, 'nombre' => $sesion['no_laborable']];
                    }
                }
            }

            $cronograma[] = [
                'grupo_id' => $grupo->id,
                'grupo_nombre' => $grupo->nombre,
                'orden' => $grupo->pivot->orden,
                'modulo' => [
                    'id' => $grupo->modulo->id,
                    'nombre' => $grupo->modulo->nombre,
                    'duracion' => $grupo->modulo->duracion,
                ],
                'fecha_inicio_grupo' => $grupo->pivot->fecha_inicio_grupo,
                'fecha_fin_grupo' => $grupo->pivot->fecha_fin_grupo,
                'ciclo_origen_id' => $grupo->pivot->ciclo_origen_id,
                'horas_por_semana' => $grupo->getTotalHorasSemanaAttribute(),
                'semanas_estimadas' => $grupo->getTotalHorasSemanaAttribute() > 0
                    ? ceil($grupo->modulo->duracion / $grupo->getTotalHorasSemanaAttribute())
                    : 0,
                'sesiones' => $sesiones,
                'dias_omitidos' => array_values($diasOmitidos),
            ];
        }

        return $cronograma;
    }

    /**
     * Scope de ciclos abiertos para registrar asistencia y notas: activos, ya iniciados
     * y sin terminar, o terminados hace a lo sumo `academico.dias_cierre_ciclo` días
     * (plazo para la asistencia de la última clase y el cierre de notas, N17).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAbiertosParaRegistro($query)
    {
        $hoy    = now()->toDateString();
        $limite = now()->subDays(config('academico.dias_cierre_ciclo'))->toDateString();

        return $query->where('status', 1)
            ->where('fecha_inicio', '<=', $hoy)
            ->where(function ($q) use ($limite) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $limite);
            });
    }
}
