<?php

namespace App\Models\Academico;

use App\Models\User;
use App\Traits\HasActiveStatus;
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
 * Modelo EsquemaCalificacion
 *
 * Representa un esquema de calificación definido por un profesor para un módulo.
 * Un esquema puede ser específico para un grupo o aplicarse a todos los grupos del módulo.
 *
 * @property int $id Identificador único del esquema
 * @property int $modulo_id ID del módulo al que pertenece
 * @property int|null $grupo_id ID del grupo específico (null si aplica a todos)
 * @property int $profesor_id ID del profesor que creó el esquema
 * @property string $nombre_esquema Nombre del esquema
 * @property string|null $descripcion Descripción del esquema
 * @property string|null $condicion_aplicacion Condición de aplicación
 * @property int $status Estado del esquema (0: Inactivo, 1: Activo)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Academico\Modulo $modulo Módulo al que pertenece
 * @property-read \App\Models\Academico\Grupo|null $grupo Grupo específico (si aplica)
 * @property-read \App\Models\User $profesor Profesor que creó el esquema
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Academico\TipoNotaEsquema[] $tiposNota Tipos de nota del esquema
 */
class EsquemaCalificacion extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Relación con Modulo (muchos a uno).
     * Un esquema pertenece a un módulo.
     *
     * @return BelongsTo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Relación con Grupo (muchos a uno, opcional).
     * Un esquema puede pertenecer a un grupo específico.
     *
     * @return BelongsTo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /**
     * Relación con User - Profesor (muchos a uno).
     * Un esquema fue creado por un profesor.
     *
     * @return BelongsTo
     */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    /**
     * Relación con TipoNotaEsquema (uno a muchos).
     * Un esquema tiene múltiples tipos de nota.
     *
     * @return HasMany
     */
    public function tiposNota(): HasMany
    {
        return $this->hasMany(TipoNotaEsquema::class)->orderBy('orden');
    }

    /**
     * Relación con NotaEstudiante (uno a muchos).
     * Un esquema tiene múltiples notas de estudiantes.
     *
     * @return HasMany
     */
    public function notasEstudiantes(): HasMany
    {
        return $this->hasMany(NotaEstudiante::class);
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
     * Scope para filtrar por grupo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $grupoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByGrupo($query, $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }

    /**
     * Scope para obtener esquemas activos de un módulo/grupo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $moduloId
     * @param int|null $grupoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActivoParaModuloGrupo($query, $moduloId, $grupoId = null)
    {
        return $query->where('modulo_id', $moduloId)
            ->where('status', 1)
            ->where(function ($q) use ($grupoId) {
                if ($grupoId) {
                    $q->where('grupo_id', $grupoId)
                      ->orWhereNull('grupo_id');
                } else {
                    $q->whereNull('grupo_id');
                }
            })
            ->orderByDesc('created_at');
    }

    /**
     * Verifica si la suma de pesos de los tipos de nota es 100%.
     *
     * @return bool
     */
    public function validarPesos(): bool
    {
        $sumaPesos = $this->tiposNota()->sum('peso');
        return abs($sumaPesos - 100) < 0.01; // Tolerancia de 0.01 para decimales
    }

    /**
     * Obtiene la suma de pesos de los tipos de nota.
     *
     * @return float
     */
    public function getSumaPesosAttribute(): float
    {
        return (float) $this->tiposNota()->sum('peso');
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre_esquema',
            'status',
            'modulo_id',
            'grupo_id',
            'profesor_id',
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
            'modulo',
            'grupo',
            'profesor',
            'tiposNota',
            'notasEstudiantes'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['modulo', 'profesor', 'tiposNota'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array
     */
    protected function getCountableRelations(): array
    {
        return ['tiposNota', 'notasEstudiantes'];
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
            ->when(isset($filters['status']) && $filters['status'] !== null, function ($q) use ($filters) {
                return $q->byStatus($filters['status']);
            })
            ->when(isset($filters['modulo_id']) && $filters['modulo_id'], function ($q) use ($filters) {
                return $q->byModulo($filters['modulo_id']);
            })
            ->when(isset($filters['grupo_id']) && $filters['grupo_id'] !== null, function ($q) use ($filters) {
                return $q->byGrupo($filters['grupo_id']);
            })
            ->when(isset($filters['profesor_id']) && $filters['profesor_id'], function ($q) use ($filters) {
                return $q->where('profesor_id', $filters['profesor_id']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
