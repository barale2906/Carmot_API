<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait para agregar scopes de filtrado específicos para el modelo Grupo.
 */
trait HasGrupoFilterScopes
{
    /**
     * Scope para filtrar por sede.
     *
     * @param Builder $query
     * @param int $sedeId
     * @return Builder
     */
    public function scopeBySede(Builder $query, int $sedeId): Builder
    {
        return $query->where('sede_id', $sedeId);
    }

    /**
     * Scope para filtrar por módulo.
     *
     * @param Builder $query
     * @param int $moduloId
     * @return Builder
     */
    public function scopeByModulo(Builder $query, int $moduloId): Builder
    {
        return $query->where('modulo_id', $moduloId);
    }

    /**
     * Scope para filtrar por profesor.
     *
     * @param Builder $query
     * @param int $profesorId
     * @return Builder
     */
    public function scopeByProfesor(Builder $query, int $profesorId): Builder
    {
        return $query->where('profesor_id', $profesorId);
    }

    /**
     * Scope para filtrar por jornada.
     *
     * @param Builder $query
     * @param int $jornada
     * @return Builder
     */
    public function scopeByJornada(Builder $query, int $jornada): Builder
    {
        return $query->where('jornada', $jornada);
    }

    /**
     * Scope para filtrar por rango de inscritos.
     *
     * @param Builder $query
     * @param int $min
     * @param int $max
     * @return Builder
     */
    public function scopeByInscritosRange(Builder $query, int $min, int $max): Builder
    {
        return $query->whereBetween('inscritos', [$min, $max]);
    }

    /**
     * Scope para filtrar por grupos con pocos inscritos.
     *
     * @param Builder $query
     * @param int $limite
     * @return Builder
     */
    public function scopePocosInscritos(Builder $query, int $limite = 10): Builder
    {
        return $query->where('inscritos', '<=', $limite);
    }

    /**
     * Scope para filtrar por grupos con muchos inscritos.
     *
     * @param Builder $query
     * @param int $limite
     * @return Builder
     */
    public function scopeMuchosInscritos(Builder $query, int $limite = 20): Builder
    {
        return $query->where('inscritos', '>=', $limite);
    }

    /**
     * Scope para filtrar por grupos de mañana.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeManana(Builder $query): Builder
    {
        return $query->where('jornada', 0);
    }

    /**
     * Scope para filtrar por grupos de tarde.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeTarde(Builder $query): Builder
    {
        return $query->where('jornada', 1);
    }

    /**
     * Scope para filtrar por grupos de noche.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeNoche(Builder $query): Builder
    {
        return $query->where('jornada', 2);
    }

    /**
     * Scope para filtrar por grupos de fin de semana.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeFinDeSemana(Builder $query): Builder
    {
        return $query->where('jornada', 3);
    }

    /**
     * Scope para aplicar múltiples filtros de manera dinámica.
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function scopeWithFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(isset($filters['search']) && $filters['search'], function ($q) use ($filters) {
                return $q->search($filters['search']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== null, function ($q) use ($filters) {
                return $q->byStatus($filters['status']);
            })
            ->when(isset($filters['sede_id']) && $filters['sede_id'], function ($q) use ($filters) {
                return $q->bySede($filters['sede_id']);
            })
            ->when(isset($filters['modulo_id']) && $filters['modulo_id'], function ($q) use ($filters) {
                return $q->byModulo($filters['modulo_id']);
            })
            ->when(isset($filters['profesor_id']) && $filters['profesor_id'], function ($q) use ($filters) {
                return $q->byProfesor($filters['profesor_id']);
            })
            ->when(isset($filters['jornada']) && $filters['jornada'] !== null, function ($q) use ($filters) {
                return $q->byJornada($filters['jornada']);
            })
            ->when(isset($filters['inscritos_min']) && $filters['inscritos_min'], function ($q) use ($filters) {
                return $q->where('inscritos', '>=', $filters['inscritos_min']);
            })
            ->when(isset($filters['inscritos_max']) && $filters['inscritos_max'], function ($q) use ($filters) {
                return $q->where('inscritos', '<=', $filters['inscritos_max']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
