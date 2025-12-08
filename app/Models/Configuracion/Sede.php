<?php

namespace App\Models\Configuracion;

use App\Models\Financiero\Descuento\Descuento;
use App\Traits\HasSedeFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Sede
 *
 * Representa una sede física de la organización.
 * Cada sede tiene horarios de atención, áreas asignadas y pertenece a una población.
 *
 * @property int $id Identificador único de la sede
 * @property string $nombre Nombre de la sede
 * @property string $direccion Dirección de la sede
 * @property string $telefono Teléfono de la sede
 * @property string $email Email de la sede
 * @property \Carbon\Carbon $hora_inicio Hora de inicio de la sede
 * @property \Carbon\Carbon $hora_fin Hora de fin de la sede
 * @property int $poblacion_id ID de la población a la que pertenece
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Configuracion\Poblacion $poblacion Población a la que pertenece
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Configuracion\Area[] $areas Áreas asignadas a la sede
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Configuracion\Horario[] $horarios Horarios de atención de la sede
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Descuento> $descuentos Descuentos asociados a esta sede
 * @property-read \Carbon\CarbonInterval|null $duracion Duración entre hora_inicio y hora_fin
 * @property-read float|null $duracion_en_horas Duración en horas
 * @property-read int|null $duracion_en_minutos Duración en minutos
 */
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
     * Relación con Descuentos (muchos a muchos).
     * Una sede puede tener múltiples descuentos asociados.
     * La relación se establece a través de la tabla pivot descuento_sede.
     *
     * @return BelongsToMany
     */
    public function descuentos(): BelongsToMany
    {
        return $this->belongsToMany(
            Descuento::class,
            'descuento_sede',
            'sede_id',
            'descuento_id'
        )->withTimestamps();
    }

    /**
     * Relación con Horario (uno a muchos).
     * Una sede puede tener múltiples horarios.
     *
     * @return HasMany
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /**
     * Relación con Grupo (uno a muchos).
     * Una sede puede tener múltiples grupos.
     *
     * @return HasMany
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(\App\Models\Academico\Grupo::class);
    }

    /**
     * Relación con Ciclo (uno a muchos).
     * Una sede puede tener múltiples ciclos.
     *
     * @return HasMany
     */
    public function ciclos(): HasMany
    {
        return $this->hasMany(\App\Models\Academico\Ciclo::class);
    }

    /**
     * Relación con Programacion (uno a muchos).
     * Una sede puede tener múltiples programaciones.
     *
     * @return HasMany
     */
    public function programaciones(): HasMany
    {
        return $this->hasMany(\App\Models\Academico\Programacion::class);
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
            'areas',
            'horarios',
            'grupos',
            'ciclos',
            'programaciones',
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
            'areas',
            'horarios',
            'grupos',
            'ciclos',
            'programaciones',
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
