<?php

namespace App\Models\Configuracion;

use App\Models\Financiero\Descuento\Descuento;
use App\Traits\HasFilterScopes;
use App\Traits\HasPoblacionFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Poblacion
 *
 * Representa una población (ciudad) en el sistema.
 * Una población puede tener múltiples sedes y puede estar asociada a descuentos.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Configuracion\Sede> $sedes Sedes que pertenecen a esta población
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Descuento> $descuentos Descuentos asociados a esta población
 */
class Poblacion extends Model
{
    use HasFactory, HasPoblacionFilterScopes, HasSortingScopes, HasRelationScopes;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Relación con Sedes (uno a muchos).
     * Una población puede tener muchas sedes.
     *
     * @return HasMany
     */
    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class);
    }

    /**
     * Relación con Descuentos (muchos a muchos).
     * Una población puede tener múltiples descuentos asociados.
     * La relación se establece a través de la tabla pivot descuento_poblacion.
     *
     * @return BelongsToMany
     */
    public function descuentos(): BelongsToMany
    {
        return $this->belongsToMany(
            Descuento::class,
            'descuento_poblacion',
            'poblacion_id',
            'descuento_id'
        )->withTimestamps();
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
            'descuentos'
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
            'descuentos'
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
            'pais',
            'provincia',
            'latitud',
            'longitud',
            'created_at',
            'updated_at'
        ];
    }
}
