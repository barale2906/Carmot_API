<?php

namespace App\Models\Academico;

use App\Models\Crm\Referido;
use App\Models\User;
use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Curso extends Model
{
    use HasFactory, HasTranslations, SoftDeletes, HasFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Relación uno a muchos con referidos.
     */
    public function referidos(): HasMany
    {
        return $this->hasMany(Referido::class);
    }

    /**
     * Estudiantes registrados al curso (relación muchos a muchos).
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'curso_user')
                    ->withTimestamps();
    }

    /**
     * Módulos asociados al curso (relación muchos a muchos).
     */
    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(Modulo::class, 'modulo_curso')
                    ->withTimestamps();
    }

    /**
     * Ciclos asociados al curso (relación uno a muchos).
     */
    public function ciclos(): HasMany
    {
        return $this->hasMany(Ciclo::class);
    }

    /**
     * Programaciones asociadas al curso (relación uno a muchos).
     */
    public function programaciones(): HasMany
    {
        return $this->hasMany(Programacion::class);
    }

    /**
     * Scope para filtrar por búsqueda de nombre (sobrescribe el del trait).
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
    }

    /**
     * Scope para filtrar por duración mínima.
     */
    public function scopeByDuracionMin($query, $duracionMin)
    {
        return $query->where('duracion', '>=', $duracionMin);
    }

    /**
     * Scope para filtrar por duración máxima.
     */
    public function scopeByDuracionMax($query, $duracionMax)
    {
        return $query->where('duracion', '<=', $duracionMax);
    }

    /**
     * Scope para filtrar por rango de duración.
     */
    public function scopeByDuracionRange($query, $duracionMin, $duracionMax)
    {
        return $query->whereBetween('duracion', [$duracionMin, $duracionMax]);
    }

    /**
     * Scope para aplicar múltiples filtros de manera dinámica (sobrescribe el del trait).
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
            ->when(isset($filters['duracion_min']) && $filters['duracion_min'], function ($q) use ($filters) {
                return $q->byDuracionMin($filters['duracion_min']);
            })
            ->when(isset($filters['duracion_max']) && $filters['duracion_max'], function ($q) use ($filters) {
                return $q->byDuracionMax($filters['duracion_max']);
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
            'duracion',
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
            'referidos',
            'estudiantes',
            'modulos',
            'ciclos',
            'programaciones'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     */
    protected function getDefaultRelations(): array
    {
        return ['referidos', 'estudiantes', 'modulos', 'ciclos'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     */
    protected function getCountableRelations(): array
    {
        return ['referidos', 'estudiantes', 'modulos', 'ciclos', 'programaciones'];
    }
}
