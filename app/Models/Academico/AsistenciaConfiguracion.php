<?php

namespace App\Models\Academico;

use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo AsistenciaConfiguracion
 *
 * Representa la configuración de los requisitos mínimos de asistencia.
 * Las configuraciones pueden ser generales o específicas por curso o módulo.
 *
 * @property int $id Identificador único de la configuración
 * @property int|null $curso_id ID del curso (null para configuración general)
 * @property int|null $modulo_id ID del módulo (null para configuración general o por curso)
 * @property float $porcentaje_minimo Porcentaje mínimo de asistencia requerido
 * @property int|null $horas_minimas Horas mínimas de asistencia requeridas
 * @property bool $aplicar_justificaciones Si las justificaciones cuentan para el mínimo
 * @property bool $perder_por_fallas Si se pierde el curso por faltas
 * @property \Carbon\Carbon|null $fecha_inicio_vigencia Fecha de inicio de vigencia
 * @property \Carbon\Carbon|null $fecha_fin_vigencia Fecha de fin de vigencia
 * @property string|null $observaciones Observaciones adicionales
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Academico\Curso|null $curso Curso al que pertenece (si es específica)
 * @property-read \App\Models\Academico\Modulo|null $modulo Módulo al que pertenece (si es específica)
 */
class AsistenciaConfiguracion extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'asistencia_configuraciones';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'porcentaje_minimo' => 'decimal:2',
        'horas_minimas' => 'integer',
        'aplicar_justificaciones' => 'boolean',
        'perder_por_fallas' => 'boolean',
        'fecha_inicio_vigencia' => 'date',
        'fecha_fin_vigencia' => 'date',
    ];

    /**
     * Relación con Curso (muchos a uno, nullable).
     * Una configuración puede pertenecer a un curso específico o ser general.
     *
     * @return BelongsTo
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Relación con Modulo (muchos a uno, nullable).
     * Una configuración puede pertenecer a un módulo específico o ser general.
     *
     * @return BelongsTo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Scope para filtrar configuraciones vigentes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $fecha Fecha a verificar (por defecto hoy)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVigente($query, $fecha = null)
    {
        $fecha = $fecha ? \Carbon\Carbon::parse($fecha) : now();

        return $query->where(function ($q) use ($fecha) {
            $q->whereNull('fecha_inicio_vigencia')
              ->orWhere('fecha_inicio_vigencia', '<=', $fecha);
        })->where(function ($q) use ($fecha) {
            $q->whereNull('fecha_fin_vigencia')
              ->orWhere('fecha_fin_vigencia', '>=', $fecha);
        });
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
     * Scope para filtrar por módulo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $moduloId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByModulo($query, $moduloId)
    {
        return $query->where('modulo_id', $moduloId);
    }

    /**
     * Verifica si la configuración está vigente en una fecha específica.
     *
     * @param string|null $fecha Fecha a verificar (por defecto hoy)
     * @return bool
     */
    public function esVigente($fecha = null): bool
    {
        $fecha = $fecha ? \Carbon\Carbon::parse($fecha) : now();

        $inicioValido = !$this->fecha_inicio_vigencia ||
                       \Carbon\Carbon::parse($this->fecha_inicio_vigencia) <= $fecha;

        $finValido = !$this->fecha_fin_vigencia ||
                    \Carbon\Carbon::parse($this->fecha_fin_vigencia) >= $fecha;

        return $inicioValido && $finValido;
    }

    /**
     * Verifica si esta configuración aplica a un curso y módulo específicos.
     *
     * @param int $cursoId ID del curso
     * @param int|null $moduloId ID del módulo (opcional)
     * @return bool
     */
    public function aplicarA($cursoId, $moduloId = null): bool
    {
        // Si tiene curso_id específico, debe coincidir
        if ($this->curso_id !== null && $this->curso_id != $cursoId) {
            return false;
        }

        // Si tiene modulo_id específico, debe coincidir
        if ($this->modulo_id !== null) {
            return $this->modulo_id == $moduloId;
        }

        // Si no tiene curso_id ni modulo_id, aplica a todos
        return true;
    }

    /**
     * Obtiene la configuración vigente para un curso y módulo específicos.
     *
     * @param int $cursoId ID del curso
     * @param int|null $moduloId ID del módulo (opcional)
     * @param string|null $fecha Fecha a verificar (por defecto hoy)
     * @return AsistenciaConfiguracion|null
     */
    public static function obtenerPara($cursoId, $moduloId = null, $fecha = null)
    {
        $fecha = $fecha ? \Carbon\Carbon::parse($fecha) : now();

        // Primero buscar configuración específica para curso y módulo
        $configuracion = static::vigente($fecha)
            ->where(function ($q) use ($cursoId, $moduloId) {
                $q->where(function ($q2) use ($cursoId, $moduloId) {
                    // Configuración específica para curso y módulo
                    $q2->where('curso_id', $cursoId)
                       ->where('modulo_id', $moduloId);
                })->orWhere(function ($q2) use ($cursoId) {
                    // Configuración específica solo para curso
                    $q2->where('curso_id', $cursoId)
                       ->whereNull('modulo_id');
                })->orWhere(function ($q2) {
                    // Configuración general (sin curso ni módulo)
                    $q2->whereNull('curso_id')
                       ->whereNull('modulo_id');
                });
            })
            ->orderByRaw('CASE
                WHEN curso_id IS NOT NULL AND modulo_id IS NOT NULL THEN 1
                WHEN curso_id IS NOT NULL AND modulo_id IS NULL THEN 2
                WHEN curso_id IS NULL AND modulo_id IS NULL THEN 3
                ELSE 4
            END')
            ->first();

        return $configuracion;
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
            'modulo'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['curso', 'modulo'];
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
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'porcentaje_minimo',
            'horas_minimas',
            'fecha_inicio_vigencia',
            'fecha_fin_vigencia',
            'curso_id',
            'modulo_id',
            'created_at',
            'updated_at'
        ];
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
            ->when(isset($filters['curso_id']) && $filters['curso_id'], function ($q) use ($filters) {
                return $q->byCurso($filters['curso_id']);
            })
            ->when(isset($filters['modulo_id']) && $filters['modulo_id'], function ($q) use ($filters) {
                return $q->byModulo($filters['modulo_id']);
            })
            ->when(isset($filters['vigente']) && $filters['vigente'], function ($q) use ($filters) {
                return $q->vigente($filters['fecha'] ?? null);
            })
            ->when(isset($filters['porcentaje_minimo']) && $filters['porcentaje_minimo'], function ($q) use ($filters) {
                return $q->where('porcentaje_minimo', '>=', $filters['porcentaje_minimo']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
