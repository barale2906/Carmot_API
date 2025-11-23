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
 * Modelo NotaEstudiante
 *
 * Representa una nota registrada de un estudiante en un módulo específico.
 * Las notas se crean bajo demanda cuando el profesor las registra.
 *
 * @property int $id Identificador único de la nota
 * @property int $estudiante_id ID del estudiante
 * @property int $grupo_id ID del grupo
 * @property int $modulo_id ID del módulo
 * @property int $esquema_calificacion_id ID del esquema usado
 * @property int $tipo_nota_esquema_id ID del tipo de nota
 * @property float $nota Valor de la nota
 * @property float $nota_ponderada Nota ponderada (nota × peso / 100)
 * @property \Carbon\Carbon $fecha_registro Fecha de registro
 * @property int $registrado_por_id ID del profesor que registró
 * @property string|null $observaciones Observaciones adicionales
 * @property int $status Estado (0: pendiente, 1: registrada, 2: cerrada)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\User $estudiante Estudiante al que pertenece
 * @property-read \App\Models\Academico\Grupo $grupo Grupo al que pertenece
 * @property-read \App\Models\Academico\Modulo $modulo Módulo al que pertenece
 * @property-read \App\Models\Academico\EsquemaCalificacion $esquemaCalificacion Esquema usado
 * @property-read \App\Models\Academico\TipoNotaEsquema $tipoNotaEsquema Tipo de nota
 * @property-read \App\Models\User $registradoPor Profesor que registró
 */
class NotaEstudiante extends Model
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
        'nota' => 'decimal:2',
        'nota_ponderada' => 'decimal:2',
        'fecha_registro' => 'date',
        'status' => 'integer',
    ];

    /**
     * Relación con User - Estudiante (muchos a uno).
     *
     * @return BelongsTo
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /**
     * Relación con Grupo (muchos a uno).
     *
     * @return BelongsTo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /**
     * Relación con Modulo (muchos a uno).
     *
     * @return BelongsTo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Relación con EsquemaCalificacion (muchos a uno).
     *
     * @return BelongsTo
     */
    public function esquemaCalificacion(): BelongsTo
    {
        return $this->belongsTo(EsquemaCalificacion::class);
    }

    /**
     * Relación con TipoNotaEsquema (muchos a uno).
     *
     * @return BelongsTo
     */
    public function tipoNotaEsquema(): BelongsTo
    {
        return $this->belongsTo(TipoNotaEsquema::class);
    }

    /**
     * Relación con User - Registrado Por (muchos a uno).
     *
     * @return BelongsTo
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    /**
     * Calcula la nota ponderada basándose en el peso del tipo de nota.
     *
     * @param float $nota
     * @param float $peso
     * @return float
     */
    public static function calcularNotaPonderada(float $nota, float $peso): float
    {
        return round($nota * ($peso / 100), 2);
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
     * Scope para filtrar por estudiante y módulo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $estudianteId
     * @param int $moduloId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByEstudianteModulo($query, $estudianteId, $moduloId)
    {
        return $query->where('estudiante_id', $estudianteId)
            ->where('modulo_id', $moduloId);
    }

    /**
     * Scope para filtrar por grupo y módulo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $grupoId
     * @param int $moduloId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByGrupoModulo($query, $grupoId, $moduloId)
    {
        return $query->where('grupo_id', $grupoId)
            ->where('modulo_id', $moduloId);
    }

    /**
     * Scope para filtrar por estado.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nota',
            'nota_ponderada',
            'fecha_registro',
            'status',
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
            'grupo',
            'modulo',
            'esquemaCalificacion',
            'tipoNotaEsquema',
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
        return ['estudiante', 'grupo', 'modulo', 'tipoNotaEsquema'];
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
            ->when(isset($filters['modulo_id']) && $filters['modulo_id'], function ($q) use ($filters) {
                return $q->byModulo($filters['modulo_id']);
            })
            ->when(isset($filters['esquema_calificacion_id']) && $filters['esquema_calificacion_id'], function ($q) use ($filters) {
                return $q->where('esquema_calificacion_id', $filters['esquema_calificacion_id']);
            })
            ->when(isset($filters['tipo_nota_esquema_id']) && $filters['tipo_nota_esquema_id'], function ($q) use ($filters) {
                return $q->where('tipo_nota_esquema_id', $filters['tipo_nota_esquema_id']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== null, function ($q) use ($filters) {
                return $q->byStatus($filters['status']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
