<?php

namespace App\Traits;

/**
 * Scopes de filtrado para el catálogo de cuentas bancarias del instituto.
 */
trait HasCuentaBancariaFilterScopes
{
    /**
     * Scope de búsqueda por número, titular, descripción o nombre del banco.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('numero_cuenta', 'like', '%'.$search.'%')
                ->orWhere('titular', 'like', '%'.$search.'%')
                ->orWhere('descripcion', 'like', '%'.$search.'%')
                ->orWhereHas('banco', fn ($b) => $b->where('nombre', 'like', '%'.$search.'%'));
        });
    }

    /**
     * Scope para filtrar por ciudad (cuentas asociadas a toda la ciudad).
     */
    public function scopeByPoblacion($query, int $poblacionId)
    {
        return $query->where('poblacion_id', $poblacionId);
    }

    /**
     * Scope para filtrar por banco.
     */
    public function scopeByBanco($query, int $bancoId)
    {
        return $query->where('banco_id', $bancoId);
    }

    /**
     * Scope para filtrar por tipo de cuenta.
     */
    public function scopeByTipoCuenta($query, string $tipo)
    {
        return $query->where('tipo_cuenta', $tipo);
    }

    /**
     * Scope para filtrar por status.
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope compuesto que aplica los filtros disponibles.
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(! empty($filters['search']), fn ($q) => $q->search($filters['search']))
            // Por sede: las cuentas que le aplican, asociadas a ella o a su ciudad
            ->when(! empty($filters['sede_id']), fn ($q) => $q->aplicaASede((int) $filters['sede_id']))
            ->when(! empty($filters['poblacion_id']), fn ($q) => $q->byPoblacion((int) $filters['poblacion_id']))
            ->when(! empty($filters['banco_id']), fn ($q) => $q->byBanco((int) $filters['banco_id']))
            ->when(! empty($filters['tipo_cuenta']), fn ($q) => $q->byTipoCuenta($filters['tipo_cuenta']))
            ->when(isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '',
                fn ($q) => $q->byStatus((int) $filters['status']));
    }
}
