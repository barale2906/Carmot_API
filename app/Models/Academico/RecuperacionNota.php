<?php

namespace App\Models\Academico;

use App\Models\User;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo RecuperacionNota
 *
 * Nota de recuperación de un módulo reprobado. Reemplaza la nota final del esquema:
 * con ella el estudiante aprueba (si alcanza `academico.nota_aprobatoria`) o reprueba
 * definitivamente el módulo. Hay una sola por estudiante, grupo y módulo.
 *
 * @property int $id
 * @property int $estudiante_id Estudiante que presenta la recuperación
 * @property int $grupo_id Grupo donde cursó el módulo
 * @property int $modulo_id Módulo que recupera
 * @property float $nota Nota de la recuperación
 * @property \Carbon\Carbon $fecha_registro Fecha en que se presentó
 * @property int $registrado_por_id Usuario que la registró
 * @property string|null $observaciones
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read User $estudiante
 * @property-read Grupo $grupo
 * @property-read Modulo $modulo
 * @property-read User $registradoPor
 */
class RecuperacionNota extends Model
{
    use SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'nota'           => 'decimal:2',
        'fecha_registro' => 'date',
    ];

    /**
     * Estudiante que presenta la recuperación.
     *
     * @return BelongsTo
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /**
     * Grupo donde el estudiante cursó el módulo.
     *
     * @return BelongsTo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /**
     * Módulo que se recupera.
     *
     * @return BelongsTo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Usuario que registró la recuperación.
     *
     * @return BelongsTo
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return ['nota', 'fecha_registro', 'created_at', 'updated_at'];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     *
     * @return array
     */
    protected function getAllowedRelations(): array
    {
        return ['estudiante', 'grupo', 'modulo', 'registradoPor'];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['estudiante', 'grupo', 'modulo'];
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
     * @param array $filters estudiante_id, grupo_id, modulo_id, include_trashed, only_trashed
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when($filters['estudiante_id'] ?? null, fn ($q, $id) => $q->where('estudiante_id', $id))
            ->when($filters['grupo_id'] ?? null, fn ($q, $id) => $q->where('grupo_id', $id))
            ->when($filters['modulo_id'] ?? null, fn ($q, $id) => $q->where('modulo_id', $id))
            ->when($filters['include_trashed'] ?? false, fn ($q) => $q->withTrashed())
            ->when($filters['only_trashed'] ?? false, fn ($q) => $q->onlyTrashed());
    }
}
