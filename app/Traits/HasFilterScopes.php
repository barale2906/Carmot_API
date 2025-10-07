<?php

namespace App\Traits;

trait HasFilterScopes
{
    /**
     * Scope para filtrar por búsqueda de nombre.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
    }

    /**
     * Scope para filtrar por ciudad.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $ciudad
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCiudad($query, $ciudad)
    {
        return $query->where('ciudad', $ciudad);
    }

    /**
     * Scope para filtrar por status.
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
     * Scope para filtrar por gestor.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $gestorId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByGestor($query, $gestorId)
    {
        return $query->where('gestor_id', $gestorId);
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
            ->when(isset($filters['search']) && $filters['search'], function ($q) use ($filters) {
                return $q->search($filters['search']);
            })
            ->when(isset($filters['ciudad']) && $filters['ciudad'], function ($q) use ($filters) {
                return $q->byCiudad($filters['ciudad']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== null, function ($q) use ($filters) {
                return $q->byStatus($filters['status']);
            })
            ->when(isset($filters['curso_id']) && $filters['curso_id'], function ($q) use ($filters) {
                return $q->byCurso($filters['curso_id']);
            })
            ->when(isset($filters['gestor_id']) && $filters['gestor_id'], function ($q) use ($filters) {
                return $q->byGestor($filters['gestor_id']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
