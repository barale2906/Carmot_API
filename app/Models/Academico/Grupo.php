<?php

namespace App\Models\Academico;

use App\Models\Academico\EsquemaCalificacion;
use App\Models\Configuracion\Sede;
use App\Models\Configuracion\Horario;
use App\Models\User;
use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasGrupoFilterScopes;
use App\Traits\HasJornadaStatus;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grupo extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasGrupoFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus, HasJornadaStatus {
        HasGrupoFilterScopes::scopeWithFilters insteadof HasFilterScopes;
    }

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'jornada' => 'integer',
        'status' => 'integer',
        'inscritos' => 'integer',
    ];

    /**
     * Relación con Sede (muchos a uno).
     * Un grupo pertenece a una sede.
     *
     * @return BelongsTo
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Relación con Modulo (muchos a uno).
     * Un grupo pertenece a un módulo.
     *
     * @return BelongsTo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Relación con User (muchos a uno).
     * Un grupo tiene un profesor asignado.
     *
     * @return BelongsTo
     */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    /**
     * Relación con Ciclo (muchos a muchos).
     * Un grupo puede pertenecer a múltiples ciclos.
     *
     * @return BelongsToMany
     */
    public function ciclos(): BelongsToMany
    {
        return $this->belongsToMany(Ciclo::class, 'ciclo_grupo')->withTimestamps();
    }

    /**
     * Advertencias de cruce de horario del profesor: otros grupos activos suyos con una
     * sesión el mismo día que se solapa en hora (B03). No bloquea: en el calendario cíclico
     * dos grupos del mismo curso pueden compartir franja si se dictan en épocas distintas.
     *
     * @return array<int, string>
     */
    public function crucesHorarioProfesor(): array
    {
        if (! $this->profesor_id) {
            return [];
        }

        $propios = $this->horarios()->get(['dia', 'hora', 'duracion_horas']);
        if ($propios->isEmpty()) {
            return [];
        }

        $nombres = self::where('profesor_id', $this->profesor_id)
            ->where('status', 1)
            ->whereKeyNot($this->id)
            ->pluck('nombre', 'id');

        $otros = Horario::query()
            ->where('tipo', false)
            ->whereIn('grupo_id', $nombres->keys())
            ->whereIn('dia', $propios->pluck('dia')->unique())
            ->get(['grupo_id', 'dia', 'hora', 'duracion_horas']);

        $minutos = fn ($h) => \Carbon\Carbon::parse($h->hora)->hour * 60 + \Carbon\Carbon::parse($h->hora)->minute;
        $mensajes = [];

        foreach ($propios as $propio) {
            $inicio = $minutos($propio);
            $fin    = $inicio + 60 * max(1, (int) $propio->duracion_horas);

            foreach ($otros->where('dia', $propio->dia) as $otro) {
                $inicioOtro = $minutos($otro);
                $finOtro    = $inicioOtro + 60 * max(1, (int) $otro->duracion_horas);

                if ($inicio < $finOtro && $inicioOtro < $fin) {
                    $mensajes[] = sprintf(
                        'El profesor también dicta el grupo %s el %s a las %s: verifique que no se ejecuten en las mismas fechas.',
                        $nombres[$otro->grupo_id],
                        $otro->dia,
                        \Carbon\Carbon::parse($otro->hora)->format('H:i')
                    );
                }
            }
        }

        return array_values(array_unique($mensajes));
    }

    /**
     * IDs de los estudiantes con matrícula vigente en algún ciclo que incluye este grupo:
     * los únicos a los que se les puede registrar asistencia o notas en él.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function idsEstudiantesVigentes(): \Illuminate\Support\Collection
    {
        return Matricula::vigentes()
            ->whereIn('ciclo_id', $this->ciclos()->pluck('ciclos.id'))
            ->pluck('estudiante_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Relación con Programacion (muchos a muchos).
     * Un grupo puede pertenecer a múltiples programaciones.
     * La tabla pivot incluye las fechas de inicio y fin específicas del grupo en cada programación.
     *
     * @return BelongsToMany
     */
    public function programaciones(): BelongsToMany
    {
        return $this->belongsToMany(Programacion::class, 'programacion_grupo')
            ->withPivot(['fecha_inicio_grupo', 'fecha_fin_grupo'])
            ->withTimestamps();
    }

    /**
     * Relación con Horario (uno a muchos).
     * Un grupo puede tener múltiples horarios específicos.
     * Los horarios específicos del grupo tienen tipo = false.
     *
     * @return HasMany
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'grupo_id')->where('tipo', false);
    }

    /**
     * Relación con EsquemaCalificacion (uno a muchos).
     * Un grupo puede tener múltiples esquemas de calificación.
     *
     * @return HasMany
     */
    public function esquemasCalificacion(): HasMany
    {
        return $this->hasMany(EsquemaCalificacion::class);
    }

    /**
     * Relación con AsistenciaClaseProgramada (uno a muchos).
     * Un grupo puede tener múltiples clases programadas.
     *
     * @return HasMany
     */
    public function clasesProgramadas(): HasMany
    {
        return $this->hasMany(AsistenciaClaseProgramada::class);
    }

    /**
     * Relación con Asistencia (uno a muchos).
     * Un grupo puede tener múltiples asistencias registradas.
     *
     * @return HasMany
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
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
            'inscritos',
            'jornada',
            'status',
            'sede_id',
            'modulo_id',
            'profesor_id',
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
            'modulo',
            'profesor',
            'ciclos',
            'horarios',
            'programaciones',
            'clasesProgramadas',
            'asistencias'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     */
    protected function getDefaultRelations(): array
    {
        return ['sede', 'modulo', 'profesor', 'ciclos', 'horarios'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     */
    protected function getCountableRelations(): array
    {
        return ['ciclos', 'horarios', 'programaciones'];
    }


    /**
     * Obtiene el total de horas de clase por semana del grupo.
     *
     * @return int
     */
    public function getTotalHorasSemanaAttribute(): int
    {
        return $this->horarios()->sum('duracion_horas');
    }

    /**
     * Obtiene las horas de clase por día (usando la duración real de cada horario).
     *
     * @return int
     */
    public function getHorasPorDia(): int
    {
        // Ahora usa la duración real de cada horario
        return $this->horarios()->sum('duracion_horas');
    }

    /**
     * Obtiene los días de la semana en que tiene clases el grupo.
     *
     * @return array
     */
    public function getDiasClaseAttribute(): array
    {
        return $this->horarios()->pluck('dia')->unique()->toArray();
    }

    /**
     * Verifica si el grupo tiene horarios configurados.
     *
     * @return bool
     */
    public function tieneHorarios(): bool
    {
        return $this->horarios()->exists();
    }
}
