<?php

namespace App\Traits;

trait HasSeguimientoScopes
{
    /**
     * Filtra los seguimientos por referido específico.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $referidoId ID del referido
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByReferido($query, $referidoId)
    {
        return $query->where('referido_id', $referidoId);
    }

    /**
     * Filtra los seguimientos por seguidor específico.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $seguidorId ID del seguidor
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySeguidor($query, $seguidorId)
    {
        return $query->where('seguidor_id', $seguidorId);
    }

    /**
     * Filtra los seguimientos por rango de fechas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate Fecha de inicio
     * @param string $endDate Fecha de fin
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('fecha', [$startDate, $endDate]);
    }

    /**
     * Busca en el contenido del seguimiento.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search Término de búsqueda
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchInContent($query, $search)
    {
        return $query->where('seguimiento', 'like', '%' . $search . '%');
    }

    /**
     * Aplica filtros dinámicos específicos para seguimientos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithSeguimientoFilters($query, array $filters)
    {
        return $query
            ->when(isset($filters['referido_id']) && $filters['referido_id'], function ($q) use ($filters) {
                return $q->byReferido($filters['referido_id']);
            })
            ->when(isset($filters['seguidor_id']) && $filters['seguidor_id'], function ($q) use ($filters) {
                return $q->bySeguidor($filters['seguidor_id']);
            })
            ->when(isset($filters['search']) && $filters['search'], function ($q) use ($filters) {
                return $q->searchInContent($filters['search']);
            })
            ->when(isset($filters['fecha_desde']) && isset($filters['fecha_hasta']), function ($q) use ($filters) {
                return $q->byDateRange($filters['fecha_desde'], $filters['fecha_hasta']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
