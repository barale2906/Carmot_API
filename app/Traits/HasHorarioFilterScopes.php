<?php

namespace App\Traits;

trait HasHorarioFilterScopes
{
    /**
     * Scope para filtrar por búsqueda general.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('dia', 'like', '%' . $search . '%')
              ->orWhere('grupo_nombre', 'like', '%' . $search . '%')
              ->orWhereHas('sede', function ($sedeQuery) use ($search) {
                  $sedeQuery->where('nombre', 'like', '%' . $search . '%')
                           ->orWhere('direccion', 'like', '%' . $search . '%');
              })
              ->orWhereHas('area', function ($areaQuery) use ($search) {
                  $areaQuery->where('nombre', 'like', '%' . $search . '%');
              });
        });
    }

    /**
     * Scope para filtrar por sede.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $sedeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    /**
     * Scope para filtrar por área.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $areaId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByArea($query, $areaId)
    {
        return $query->where('area_id', $areaId);
    }

    /**
     * Scope para filtrar por día.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $dia
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDia($query, $dia)
    {
        return $query->where('dia', $dia);
    }

    /**
     * Scope para filtrar por tipo de horario.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool $tipo
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope para filtrar por período.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool $periodo
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPeriodo($query, $periodo)
    {
        return $query->where('periodo', $periodo);
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
     * Scope para filtrar por grupo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $grupoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByGrupo($query, $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }

    /**
     * Scope para filtrar por nombre de grupo.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $grupoNombre
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByGrupoNombre($query, $grupoNombre)
    {
        return $query->where('grupo_nombre', 'like', '%' . $grupoNombre . '%');
    }

    /**
     * Scope para filtrar por rango de hora.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $horaInicio
     * @param string $horaFin
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByRangoHora($query, $horaInicio, $horaFin = null)
    {
        if ($horaFin) {
            return $query->whereBetween('hora', [$horaInicio, $horaFin]);
        }

        return $query->where('hora', '>=', $horaInicio);
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
            ->when(isset($filters['sede_id']) && $filters['sede_id'], function ($q) use ($filters) {
                return $q->bySede($filters['sede_id']);
            })
            ->when(isset($filters['area_id']) && $filters['area_id'], function ($q) use ($filters) {
                return $q->byArea($filters['area_id']);
            })
            ->when(isset($filters['dia']) && $filters['dia'], function ($q) use ($filters) {
                return $q->byDia($filters['dia']);
            })
            ->when(isset($filters['tipo']) && $filters['tipo'] !== null, function ($q) use ($filters) {
                return $q->byTipo($filters['tipo']);
            })
            ->when(isset($filters['periodo']) && $filters['periodo'] !== null, function ($q) use ($filters) {
                return $q->byPeriodo($filters['periodo']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== null, function ($q) use ($filters) {
                return $q->byStatus($filters['status']);
            })
            ->when(isset($filters['grupo_id']) && $filters['grupo_id'], function ($q) use ($filters) {
                return $q->byGrupo($filters['grupo_id']);
            })
            ->when(isset($filters['grupo_nombre']) && $filters['grupo_nombre'], function ($q) use ($filters) {
                return $q->byGrupoNombre($filters['grupo_nombre']);
            })
            ->when(isset($filters['hora_inicio']) && $filters['hora_inicio'], function ($q) use ($filters) {
                return $q->byRangoHora($filters['hora_inicio'], $filters['hora_fin'] ?? null);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
