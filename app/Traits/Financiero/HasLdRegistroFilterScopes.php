<?php

namespace App\Traits\Financiero;

/**
 * Scopes de filtrado para los registros del libro diario (movimientos y consignaciones).
 *
 * El modelo indica con CAMPOS_BUSQUEDA las columnas en que busca el texto libre.
 */
trait HasLdRegistroFilterScopes
{
    /**
     * Scope para limitar a las sedes indicadas (las accesibles por el usuario).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<int>  $sedeIds
     */
    public function scopeDeSedes($query, array $sedeIds)
    {
        return $query->whereIn($this->getTable().'.sede_id', $sedeIds);
    }

    /**
     * Scope de búsqueda por texto en las columnas del modelo.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            foreach (self::CAMPOS_BUSQUEDA as $campo) {
                $q->orWhere($campo, 'like', '%'.$search.'%');
            }
        });
    }

    /**
     * Scope compuesto: search, sede_id, turno_caja_id, status, fecha_desde, fecha_hasta,
     * y en movimientos clase, tipo_movimiento_id y tercero_id.
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(! empty($filters['search']), fn ($q) => $q->search($filters['search']))
            ->when(! empty($filters['sede_id']), fn ($q) => $q->where('sede_id', (int) $filters['sede_id']))
            ->when(! empty($filters['turno_caja_id']), fn ($q) => $q->where('turno_caja_id', (int) $filters['turno_caja_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['fecha_desde']), fn ($q) => $q->whereDate('fecha', '>=', $filters['fecha_desde']))
            ->when(! empty($filters['fecha_hasta']), fn ($q) => $q->whereDate('fecha', '<=', $filters['fecha_hasta']))
            ->when(! empty($filters['clase']), fn ($q) => $q->where('clase', (int) $filters['clase']))
            ->when(! empty($filters['tipo_movimiento_id']), fn ($q) => $q->where('tipo_movimiento_id', (int) $filters['tipo_movimiento_id']))
            ->when(! empty($filters['tercero_id']), fn ($q) => $q->where('tercero_id', (int) $filters['tercero_id']));
    }
}
