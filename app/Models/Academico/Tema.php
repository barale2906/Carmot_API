<?php

namespace App\Models\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use App\Traits\HasFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Tema
 *
 * Representa un tema académico que puede estar asociado a múltiples tópicos.
 * Un tema puede tener múltiples tópicos y un tópico puede tener múltiples temas (relación muchos a muchos).
 *
 * @property int $id
 * @property string $nombre Nombre del tema
 * @property string $descripcion Descripción del tema
 * @property float $duracion Duración del tema en horas
 * @property int $status Estado del tema (0 = Inactivo, 1 = Activo)
 * @property \Illuminate\Support\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 * @property \Illuminate\Support\Carbon|null $created_at Fecha de creación
 * @property \Illuminate\Support\Carbon|null $updated_at Fecha de actualización
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Academico\Topico> $topicos Tópicos asociados al tema
 * @property-read int|null $topicos_count Número de tópicos asociados
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Tema active() Filtrar temas activos
 * @method static \Illuminate\Database\Eloquent\Builder|Tema inactive() Filtrar temas inactivos
 * @method static \Illuminate\Database\Eloquent\Builder|Tema byStatus(int $status) Filtrar por estado
 * @method static \Illuminate\Database\Eloquent\Builder|Tema search(string $search) Buscar por nombre y descripción
 * @method static \Illuminate\Database\Eloquent\Builder|Tema withFilters(array $filters) Aplicar múltiples filtros
 * @method static \Illuminate\Database\Eloquent\Builder|Tema withRelations(array $relations = []) Cargar relaciones específicas
 * @method static \Illuminate\Database\Eloquent\Builder|Tema withCounts(bool $includeCount = false) Incluir contadores
 * @method static \Illuminate\Database\Eloquent\Builder|Tema withRelationsAndCounts(array $relations = [], bool $includeCounts = false) Cargar relaciones y contadores
 * @method static \Illuminate\Database\Eloquent\Builder|Tema withSorting(string $sortBy = 'created_at', string $sortDirection = 'desc') Ordenar resultados
 */
class Tema extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus, HasActiveStatusValidation;

    /**
     * Atributos que no se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Atributos que deben ser tratados como fechas.
     *
     * @var array<int, string>
     */
    protected $dates = ['deleted_at'];

    /**
     * Tópicos asociados al tema (relación muchos a muchos).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function topicos(): BelongsToMany
    {
        return $this->belongsToMany(Topico::class, 'tema_topico')
                    ->withTimestamps();
    }

    /**
     * Scope para filtrar por búsqueda de nombre y descripción.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search Término de búsqueda
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%')
              ->orWhere('descripcion', 'like', '%' . $search . '%');
        });
    }

    /**
     * Scope para aplicar múltiples filtros de manera dinámica.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters Filtros a aplicar
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
     * @return array<string> Campos permitidos para ordenar
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'descripcion',
            'duracion',
            'status',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     *
     * @return array<string> Relaciones permitidas
     */
    protected function getAllowedRelations(): array
    {
        return [
            'topicos'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array<string> Relaciones por defecto
     */
    protected function getDefaultRelations(): array
    {
        return ['topicos'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array<string> Relaciones contables
     */
    protected function getCountableRelations(): array
    {
        return ['topicos'];
    }
}
