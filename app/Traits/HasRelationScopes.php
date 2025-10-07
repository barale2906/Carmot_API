<?php

namespace App\Traits;

trait HasRelationScopes
{
    /**
     * Scope para cargar relaciones específicas con validación.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $relations
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithRelations($query, array $relations = [])
    {
        $defaultRelations = $this->getDefaultRelations();
        $relationsToLoad = empty($relations) ? $defaultRelations : $relations;

        $allowedRelations = $this->getAllowedRelations();
        $validRelations = array_intersect($relationsToLoad, $allowedRelations);

        return $query->with($validRelations);
    }

    /**
     * Scope para incluir contadores cuando sea necesario.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool $includeCount
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithCounts($query, bool $includeCount = false)
    {
        if (!$includeCount) {
            return $query;
        }

        $countableRelations = $this->getCountableRelations();

        return $query->withCount($countableRelations);
    }

    /**
     * Scope para cargar relaciones y contadores en una sola llamada.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $relations
     * @param bool $includeCounts
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithRelationsAndCounts($query, array $relations = [], bool $includeCounts = false)
    {
        return $query
            ->withRelations($relations)
            ->withCounts($includeCounts);
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     * Este método puede ser sobrescrito en el modelo.
     *
     * @return array
     */
    protected function getAllowedRelations(): array
    {
        return [
            'curso',
            'gestor',
            'seguimientos'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     * Este método puede ser sobrescrito en el modelo.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['curso', 'gestor'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     * Este método puede ser sobrescrito en el modelo.
     *
     * @return array
     */
    protected function getCountableRelations(): array
    {
        return ['seguimientos'];
    }
}
