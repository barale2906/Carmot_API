<?php

namespace App\Traits;

trait HasSortingScopes
{
    /**
     * Scope para ordenamiento dinámico con campos permitidos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $sortBy
     * @param string $sortDirection
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithSorting($query, $sortBy = 'created_at', $sortDirection = 'desc')
    {
        $allowedSortFields = $this->getAllowedSortFields();

        if (in_array($sortBy, $allowedSortFields)) {
            return $query->orderBy($sortBy, $sortDirection);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     * Este método puede ser sobrescrito en el modelo para personalizar los campos.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'ciudad',
            'status',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Scope para ordenamiento por nombre ascendente.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByName($query)
    {
        return $query->orderBy('nombre', 'asc');
    }

    /**
     * Scope para ordenamiento por fecha de creación descendente.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByCreated($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope para ordenamiento por fecha de actualización descendente.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByUpdated($query)
    {
        return $query->orderBy('updated_at', 'desc');
    }
}
