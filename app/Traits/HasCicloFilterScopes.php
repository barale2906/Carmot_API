<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait para agregar scopes de filtrado específicos para el modelo Ciclo.
 */
trait HasCicloFilterScopes
{
    /**
     * Scope para filtrar por sede específica del ciclo.
     *
     * @param Builder $query
     * @param int $sedeId
     * @return Builder
     */
    public function scopeBySedeCiclo(Builder $query, int $sedeId): Builder
    {
        return $query->where('sede_id', $sedeId);
    }

    /**
     * Scope para filtrar por curso específico del ciclo.
     *
     * @param Builder $query
     * @param int $cursoId
     * @return Builder
     */
    public function scopeByCursoCiclo(Builder $query, int $cursoId): Builder
    {
        return $query->where('curso_id', $cursoId);
    }

    /**
     * Scope para filtrar por ciclos con grupos.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeConGrupos(Builder $query): Builder
    {
        return $query->whereHas('grupos');
    }

    /**
     * Scope para filtrar por ciclos sin grupos.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSinGrupos(Builder $query): Builder
    {
        return $query->whereDoesntHave('grupos');
    }

    /**
     * Scope para filtrar por ciclos con muchos grupos.
     *
     * @param Builder $query
     * @param int $limite
     * @return Builder
     */
    public function scopeConMuchosGrupos(Builder $query, int $limite = 5): Builder
    {
        return $query->has('grupos', '>=', $limite);
    }

    /**
     * Scope para filtrar por ciclos con pocos grupos.
     *
     * @param Builder $query
     * @param int $limite
     * @return Builder
     */
    public function scopeConPocosGrupos(Builder $query, int $limite = 2): Builder
    {
        return $query->has('grupos', '<=', $limite);
    }

    /**
     * Scope para filtrar por ciclos activos en una sede específica.
     *
     * @param Builder $query
     * @param int $sedeId
     * @return Builder
     */
    public function scopeActivosEnSede(Builder $query, int $sedeId): Builder
    {
        return $query->where('sede_id', $sedeId)->where('status', 1);
    }

    /**
     * Scope para filtrar por ciclos activos en un curso específico.
     *
     * @param Builder $query
     * @param int $cursoId
     * @return Builder
     */
    public function scopeActivosEnCurso(Builder $query, int $cursoId): Builder
    {
        return $query->where('curso_id', $cursoId)->where('status', 1);
    }

    /**
     * Scope para filtrar por ciclos recientes.
     *
     * @param Builder $query
     * @param int $dias
     * @return Builder
     */
    public function scopeRecientes(Builder $query, int $dias = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }

    /**
     * Scope para filtrar por ciclos antiguos.
     *
     * @param Builder $query
     * @param int $dias
     * @return Builder
     */
    public function scopeAntiguos(Builder $query, int $dias = 365): Builder
    {
        return $query->where('created_at', '<=', now()->subDays($dias));
    }

    /**
     * Scope para aplicar múltiples filtros de manera dinámica.
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function scopeWithFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(isset($filters['search']) && $filters['search'], function ($q) use ($filters) {
                return $q->search($filters['search']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== null, function ($q) use ($filters) {
                return $q->byStatus($filters['status']);
            })
            ->when(isset($filters['sede_id']) && $filters['sede_id'], function ($q) use ($filters) {
                return $q->bySedeCiclo($filters['sede_id']);
            })
            ->when(isset($filters['curso_id']) && $filters['curso_id'], function ($q) use ($filters) {
                return $q->byCursoCiclo($filters['curso_id']);
            })
            ->when(isset($filters['con_grupos']) && $filters['con_grupos'], function ($q) {
                return $q->conGrupos();
            })
            ->when(isset($filters['sin_grupos']) && $filters['sin_grupos'], function ($q) {
                return $q->sinGrupos();
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
