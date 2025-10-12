<?php

namespace App\Models\Configuracion;

use App\Traits\HasSedeFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sede extends Model
{
    use HasFactory, SoftDeletes, HasSedeFilterScopes, HasSortingScopes, HasRelationScopes;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'hora_inicio' => 'datetime:H:i:s',
        'hora_fin' => 'datetime:H:i:s',
    ];

    /**
     * Relación con Poblacion (muchos a uno).
     * Una sede pertenece a una población.
     *
     * @return BelongsTo
     */
    public function poblacion(): BelongsTo
    {
        return $this->belongsTo(Poblacion::class);
    }

    /**
     * Relación con Area (muchos a muchos).
     * Una sede puede pertenecer a múltiples áreas.
     *
     * @return BelongsToMany
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_sede');
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
            'poblacion',
            'areas'
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
            'poblacion'
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
            'areas'
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
            'direccion',
            'telefono',
            'email',
            'hora_inicio',
            'hora_fin',
            'poblacion_id',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Calcula la duración entre hora_inicio y hora_fin.
     *
     * @return \Carbon\CarbonInterval|null
     */
    public function getDuracionAttribute()
    {
        if (!$this->hora_inicio || !$this->hora_fin) {
            return null;
        }

        return $this->hora_inicio->diffAsCarbonInterval($this->hora_fin);
    }

    /**
     * Obtiene la duración en horas.
     *
     * @return float|null
     */
    public function getDuracionEnHorasAttribute()
    {
        $duracion = $this->getDuracionAttribute();
        return $duracion ? $duracion->totalHours : null;
    }

    /**
     * Obtiene la duración en minutos.
     *
     * @return int|null
     */
    public function getDuracionEnMinutosAttribute()
    {
        $duracion = $this->getDuracionAttribute();
        return $duracion ? $duracion->totalMinutes : null;
    }
}
