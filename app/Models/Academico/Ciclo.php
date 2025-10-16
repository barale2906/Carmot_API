<?php

namespace App\Models\Academico;

use App\Models\Configuracion\Sede;
use App\Traits\HasActiveStatus;
use App\Traits\HasCicloFilterScopes;
use App\Traits\HasFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property int $status Estado del ciclo (1: Activo, 0: Inactivo)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Configuracion\Sede $sede Sede a la que pertenece
 * @property-read \App\Models\Academico\Curso $curso Curso al que pertenece
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Academico\Grupo[] $grupos Grupos asociados al ciclo
 */
class Ciclo extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasCicloFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus {
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
        return $this->belongsToMany(Grupo::class, 'ciclo_grupo')->withTimestamps();
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
            'grupos'
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
}
