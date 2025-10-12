<?php

namespace App\Models\Configuracion;

use App\Traits\HasAreaFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use App\Traits\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Area extends Model
{
    use HasFactory, SoftDeletes, HasAreaFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Relación con Sede (muchos a muchos).
     * Un área puede tener múltiples sedes.
     *
     * @return BelongsToMany
     */
    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'area_sede');
    }

    /**
     * Relación con Horario (uno a muchos).
     * Un área puede tener múltiples horarios.
     *
     * @return HasMany
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     * Sobrescribe el método del trait HasRelationScopes.
     *
     * @return array
     */
    protected function getAllowedRelations(): array
    {
        return [
            'sedes',
            'sedes.poblacion',
            'horarios'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     * Sobrescribe el método del trait HasRelationScopes.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return [
            'sedes'
        ];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     * Sobrescribe el método del trait HasRelationScopes.
     *
     * @return array
     */
    protected function getCountableRelations(): array
    {
        return [
            'sedes',
            'horarios'
        ];
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     * Sobrescribe el método del trait HasSortingScopes.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'status',
            'created_at',
            'updated_at'
        ];
    }
}
