<?php

namespace App\Traits;

trait HasAgendaScopes
{
    /**
     * Scope para filtrar por referido.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $referidoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByReferido($query, $referidoId)
    {
        return $query->where('referido_id', $referidoId);
    }

    /**
     * Scope para filtrar por agendador.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $agendadorId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAgendador($query, $agendadorId)
    {
        return $query->where('agendador_id', $agendadorId);
    }

    /**
     * Scope para filtrar por jornada.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $jornada
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByJornada($query, $jornada)
    {
        return $query->where('jornada', $jornada);
    }

    /**
     * Scope para filtrar por fecha desde.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fechaDesde
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFechaDesde($query, $fechaDesde)
    {
        return $query->where('fecha', '>=', $fechaDesde);
    }

    /**
     * Scope para filtrar por fecha hasta.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fechaHasta
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFechaHasta($query, $fechaHasta)
    {
        return $query->where('fecha', '<=', $fechaHasta);
    }

    /**
     * Scope para filtrar por rango de fechas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fechaDesde
     * @param string $fechaHasta
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByRangoFechas($query, $fechaDesde, $fechaHasta)
    {
        return $query->whereBetween('fecha', [$fechaDesde, $fechaHasta]);
    }

    /**
     * Scope para filtrar por fecha específica.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fecha
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFecha($query, $fecha)
    {
        return $query->whereDate('fecha', $fecha);
    }

    /**
     * Scope para filtrar por hora específica.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $hora
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByHora($query, $hora)
    {
        return $query->where('hora', $hora);
    }

    /**
     * Scope para filtrar por rango de horas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $horaDesde
     * @param string $horaHasta
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByRangoHoras($query, $horaDesde, $horaHasta)
    {
        return $query->whereBetween('hora', [$horaDesde, $horaHasta]);
    }

    /**
     * Scope para filtrar agendas del día actual.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHoy($query)
    {
        return $query->whereDate('fecha', today());
    }

    /**
     * Scope para filtrar agendas de la semana actual.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEstaSemana($query)
    {
        return $query->whereBetween('fecha', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope para filtrar agendas del mes actual.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEsteMes($query)
    {
        return $query->whereMonth('fecha', now()->month)
                    ->whereYear('fecha', now()->year);
    }

    /**
     * Scope para filtrar agendas pendientes (status 0).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePendientes($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope para filtrar agendas completadas (status 1).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompletadas($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope para filtrar agendas canceladas (status 4).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCanceladas($query)
    {
        return $query->where('status', 4);
    }

    /**
     * Busca por nombre o celular del lead agendado.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchReferido($query, $search)
    {
        return $query->whereHas('referido', function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%')
                ->orWhere('celular', 'like', '%' . $search . '%');
        });
    }

    /**
     * Aplica los filtros del listado de agendas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithAgendaFilters($query, array $filters)
    {
        return $query
            ->when(! empty($filters['search']), fn ($q) => $q->searchReferido($filters['search']))
            ->when(! empty($filters['referido_id']), fn ($q) => $q->byReferido($filters['referido_id']))
            ->when(! empty($filters['agendador_id']), fn ($q) => $q->byAgendador($filters['agendador_id']))
            ->when(! empty($filters['jornada']), fn ($q) => $q->byJornada($filters['jornada']))
            ->when(! empty($filters['tipo']), fn ($q) => $q->where('tipo', $filters['tipo']))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['fecha_desde']), fn ($q) => $q->byFechaDesde($filters['fecha_desde']))
            ->when(! empty($filters['fecha_hasta']), fn ($q) => $q->byFechaHasta($filters['fecha_hasta']));
    }
}
