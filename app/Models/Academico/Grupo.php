<?php

namespace App\Models\Academico;

use App\Models\Configuracion\Sede;
use App\Models\Configuracion\Horario;
use App\Models\User;
use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGrupoFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grupo extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGrupoFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus {
        HasGrupoFilterScopes::scopeWithFilters insteadof HasFilterScopes;
    }

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'jornada' => 'integer',
        'status' => 'integer',
        'inscritos' => 'integer',
    ];

    /**
     * Relación con Sede (muchos a uno).
     * Un grupo pertenece a una sede.
     *
     * @return BelongsTo
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Relación con Modulo (muchos a uno).
     * Un grupo pertenece a un módulo.
     *
     * @return BelongsTo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Relación con User (muchos a uno).
     * Un grupo tiene un profesor asignado.
     *
     * @return BelongsTo
     */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    /**
     * Relación con Ciclo (muchos a muchos).
     * Un grupo puede pertenecer a múltiples ciclos.
     *
     * @return BelongsToMany
     */
    public function ciclos(): BelongsToMany
    {
        return $this->belongsToMany(Ciclo::class, 'ciclo_grupo')->withTimestamps();
    }

    /**
     * Relación con Horario (uno a muchos).
     * Un grupo puede tener múltiples horarios específicos.
     * Los horarios específicos del grupo tienen tipo = false.
     *
     * @return HasMany
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'grupo_id')->where('tipo', false);
    }

    /**
     * Scope para filtrar por búsqueda de nombre.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
    }


    /**
     * Obtiene los campos permitidos para ordenamiento.
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'inscritos',
            'jornada',
            'status',
            'sede_id',
            'modulo_id',
            'profesor_id',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     */
    protected function getAllowedRelations(): array
    {
        return [
            'sede',
            'modulo',
            'profesor',
            'ciclos',
            'horarios'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     */
    protected function getDefaultRelations(): array
    {
        return ['sede', 'modulo', 'profesor', 'ciclos', 'horarios'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     */
    protected function getCountableRelations(): array
    {
        return ['ciclos', 'horarios'];
    }

    /**
     * Obtiene el nombre de la jornada.
     *
     * @return string
     */
    public function getJornadaNombreAttribute(): string
    {
        $jornadas = [
            0 => 'Mañana',
            1 => 'Tarde',
            2 => 'Noche',
            3 => 'Fin de semana'
        ];

        return $jornadas[$this->jornada] ?? 'Desconocida';
    }

    /**
     * Obtiene el total de horas de clase por semana del grupo.
     *
     * @return int
     */
    public function getTotalHorasSemanaAttribute(): int
    {
        return $this->horarios()->sum('duracion_horas');
    }

    /**
     * Obtiene las horas de clase por día (usando la duración real de cada horario).
     *
     * @return int
     */
    public function getHorasPorDia(): int
    {
        // Ahora usa la duración real de cada horario
        return $this->horarios()->sum('duracion_horas');
    }

    /**
     * Obtiene los días de la semana en que tiene clases el grupo.
     *
     * @return array
     */
    public function getDiasClaseAttribute(): array
    {
        return $this->horarios()->pluck('dia')->unique()->toArray();
    }

    /**
     * Verifica si el grupo tiene horarios configurados.
     *
     * @return bool
     */
    public function tieneHorarios(): bool
    {
        return $this->horarios()->exists();
    }
}
