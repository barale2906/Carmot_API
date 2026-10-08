<?php

namespace App\Traits;

trait HasDiaNoLaborableFilterScopes
{
    /**
     * Scope para búsqueda por nombre u observaciones.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%')
              ->orWhere('observaciones', 'like', '%' . $search . '%');
        });
    }

    /**
     * Scope para filtrar los días que aplican a una sede: los de esa sede
     * más los generales (sede_id NULL).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $sedeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAplicaASede($query, ?int $sedeId)
    {
        return $query->where(function ($q) use ($sedeId) {
            $q->whereNull('sede_id');
            if ($sedeId !== null) {
                $q->orWhere('sede_id', $sedeId);
            }
        });
    }

    /**
     * Scope para filtrar por rango de fechas (inclusive).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $desde
     * @param string $hasta
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnRango($query, string $desde, string $hasta)
    {
        return $query->whereBetween('fecha', [$desde, $hasta]);
    }

    /**
     * Scope para aplicar múltiples filtros de manera dinámica.
     * Filtros: search, tipo, sede_id (incluye los generales), solo_generales, anio, fecha_desde, fecha_hasta, status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(! empty($filters['search']), fn ($q) => $q->search($filters['search']))
            ->when(! empty($filters['tipo']), fn ($q) => $q->where('tipo', $filters['tipo']))
            ->when(! empty($filters['sede_id']), fn ($q) => $q->aplicaASede((int) $filters['sede_id']))
            ->when(! empty($filters['solo_generales']), fn ($q) => $q->whereNull('sede_id'))
            ->when(! empty($filters['anio']), fn ($q) => $q->whereYear('fecha', (int) $filters['anio']))
            ->when(! empty($filters['fecha_desde']), fn ($q) => $q->where('fecha', '>=', $filters['fecha_desde']))
            ->when(! empty($filters['fecha_hasta']), fn ($q) => $q->where('fecha', '<=', $filters['fecha_hasta']))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']));
    }
}
