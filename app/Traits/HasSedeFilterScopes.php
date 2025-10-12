<?php

namespace App\Traits;

trait HasSedeFilterScopes
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
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%')
              ->orWhere('direccion', 'like', '%' . $search . '%')
              ->orWhere('telefono', 'like', '%' . $search . '%')
              ->orWhere('email', 'like', '%' . $search . '%');
        });
    }

    /**
     * Scope para filtrar por población.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $poblacionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPoblacion($query, $poblacionId)
    {
        return $query->where('poblacion_id', $poblacionId);
    }

    /**
     * Scope para filtrar por nombre de sede.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $nombre
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByNombre($query, $nombre)
    {
        return $query->where('nombre', 'like', '%' . $nombre . '%');
    }

    /**
     * Scope para filtrar por dirección.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $direccion
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDireccion($query, $direccion)
    {
        return $query->where('direccion', 'like', '%' . $direccion . '%');
    }

    /**
     * Scope para filtrar por teléfono.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $telefono
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTelefono($query, $telefono)
    {
        return $query->where('telefono', 'like', '%' . $telefono . '%');
    }

    /**
     * Scope para filtrar por email.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $email
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByEmail($query, $email)
    {
        return $query->where('email', 'like', '%' . $email . '%');
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
            ->when(isset($filters['poblacion_id']) && $filters['poblacion_id'], function ($q) use ($filters) {
                return $q->byPoblacion($filters['poblacion_id']);
            })
            ->when(isset($filters['nombre']) && $filters['nombre'], function ($q) use ($filters) {
                return $q->byNombre($filters['nombre']);
            })
            ->when(isset($filters['direccion']) && $filters['direccion'], function ($q) use ($filters) {
                return $q->byDireccion($filters['direccion']);
            })
            ->when(isset($filters['telefono']) && $filters['telefono'], function ($q) use ($filters) {
                return $q->byTelefono($filters['telefono']);
            })
            ->when(isset($filters['email']) && $filters['email'], function ($q) use ($filters) {
                return $q->byEmail($filters['email']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
