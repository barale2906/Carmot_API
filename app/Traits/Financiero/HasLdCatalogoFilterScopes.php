<?php

namespace App\Traits\Financiero;

/**
 * Scopes de filtrado para los catálogos del libro diario (tipos de movimiento e impuestos).
 *
 * El modelo indica con CAMPO_CLASIFICACION la columna enumerada por la que se
 * puede filtrar (`clase` en tipos de movimiento, `efecto` en impuestos).
 */
trait HasLdCatalogoFilterScopes
{
    /**
     * Scope de búsqueda por nombre.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('nombre', 'like', '%'.$search.'%');
    }

    /**
     * Scope compuesto con los filtros disponibles: search, status y la clasificación del catálogo.
     */
    public function scopeWithFilters($query, array $filters)
    {
        $campo = self::CAMPO_CLASIFICACION;

        return $query
            ->when(! empty($filters['search']), fn ($q) => $q->search($filters['search']))
            ->when(isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(isset($filters[$campo]) && $filters[$campo] !== '',
                fn ($q) => $q->where($campo, (int) $filters[$campo]));
    }
}
