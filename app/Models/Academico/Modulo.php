<?php

namespace App\Models\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Modulo extends Model
{
    use HasFactory, HasTranslations, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Cursos asociados al módulo (relación muchos a muchos).
     */
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'modulo_curso')
                    ->withTimestamps();
    }

    /**
     * Tópicos asociados al módulo (relación muchos a muchos).
     */
    public function topicos(): BelongsToMany
    {
        return $this->belongsToMany(Topico::class, 'topico_modulo')
                    ->withTimestamps();
    }

    /**
     * Grupos asociados al módulo (relación uno a muchos).
     * Un módulo puede tener múltiples grupos.
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }

    /**
     * Esquemas de calificación asociados al módulo (relación uno a muchos).
     * Un módulo puede tener múltiples esquemas de calificación.
     */
    public function esquemasCalificacion(): HasMany
    {
        return $this->hasMany(EsquemaCalificacion::class);
    }

    /**
     * Asistencias asociadas al módulo (relación uno a muchos).
     * Un módulo puede tener múltiples asistencias registradas.
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Configuraciones de asistencia asociadas al módulo (relación uno a muchos).
     * Un módulo puede tener múltiples configuraciones de asistencia.
     */
    public function configuracionesAsistencia(): HasMany
    {
        return $this->hasMany(AsistenciaConfiguracion::class);
    }

    /**
     * Scope para filtrar por búsqueda de nombre.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
    }

    /**
     * Scope para aplicar múltiples filtros de manera dinámica.
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
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'status',
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
            'cursos',
            'topicos',
            'grupos',
            'asistencias',
            'configuracionesAsistencia'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     */
    protected function getDefaultRelations(): array
    {
        return ['cursos', 'topicos'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     */
    protected function getCountableRelations(): array
    {
        return ['cursos', 'topicos', 'grupos'];
    }
}
