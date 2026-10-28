<?php

namespace App\Traits;

trait HasActivoFilterScopes
{
    /**
     * Scope de búsqueda por ID exacto (si el texto es numérico), nombre o serial.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->when(ctype_digit($search), fn ($q) => $q->orWhere('id', (int) $search))
              ->orWhere('nombre', 'like', '%' . $search . '%')
              ->orWhere('serial', 'like', '%' . $search . '%');
        });
    }

    /**
     * Activos de las sedes indicadas (las que el usuario puede ver).
     *
     * @param array<int> $sedeIds
     */
    public function scopeDeSedes($query, array $sedeIds)
    {
        return $query->whereIn('sede_id', $sedeIds);
    }

    /**
     * Scope compuesto que aplica los filtros disponibles. Por defecto oculta los
     * activos dados de baja; se ven filtrando por `estado=baja`.
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(! empty($filters['search']), fn ($q) => $q->search($filters['search']))
            ->when(! empty($filters['sede_id']), fn ($q) => $q->where('sede_id', $filters['sede_id']))
            ->when(! empty($filters['area_id']), fn ($q) => $q->where('area_id', $filters['area_id']))
            ->when(! empty($filters['categoria_id']), fn ($q) => $q->where('categoria_id', $filters['categoria_id']))
            ->when(! empty($filters['responsable_id']), fn ($q) => $q->where('responsable_id', $filters['responsable_id']))
            ->when(
                ! empty($filters['estado']),
                fn ($q) => $q->where('estado', $filters['estado']),
                fn ($q) => $q->where('estado', '!=', 'baja')
            );
    }
}
