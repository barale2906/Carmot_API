<?php

namespace App\Models\Configuracion;

use App\Traits\HasActiveStatus;
use App\Traits\HasHorarioFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Horario
 *
 * Representa los horarios de atención de una sede.
 * Puede ser horario general de la sede o horario específico de un grupo.
 *
 * @property int $id Identificador único del horario
 * @property int $sede_id ID de la sede a la que pertenece el horario
 * @property int $area_id ID del área asociada al horario
 * @property int|null $grupo_id ID del grupo (opcional) - Identificador del grupo específico
 * @property string|null $grupo_nombre Nombre del grupo (opcional) - Nombre descriptivo del grupo
 * @property bool $tipo Tipo de horario: true = horario de sede, false = horario de grupo
 * @property bool $periodo Período del horario: true = inicio, false = fin (aplica para el horario de la sede)
 * @property string $dia Día de la semana (lunes, martes, miércoles, jueves, viernes, sábado, domingo)
 * @property \Carbon\Carbon|null $hora Hora de inicio o cierre del horario
 * @property int $status Estado del horario: 1 = activo, 0 = inactivo
 * @property \Carbon\Carbon $created_at Fecha de creación del registro
 * @property \Carbon\Carbon $updated_at Fecha de última actualización del registro
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Configuracion\Sede $sede Sede a la que pertenece el horario
 * @property-read \App\Models\Configuracion\Area $area Área asociada al horario
 */
class Horario extends Model
{
    use HasFactory, SoftDeletes, HasActiveStatus, HasHorarioFilterScopes, HasSortingScopes, HasRelationScopes;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'hora' => 'datetime:H:i:s',
        'tipo' => 'boolean',
        'periodo' => 'boolean',
        'duracion_horas' => 'integer',
    ];

    /**
     * Relación con Sede (muchos a uno).
     * Un horario pertenece a una sede.
     *
     * @return BelongsTo
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Relación con Area (muchos a uno).
     * Un horario pertenece a un área.
     *
     * @return BelongsTo
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
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
            'sede',
            'area'
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
            'sede',
            'area'
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
            'sede',
            'area'
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
            'sede_id',
            'area_id',
            'grupo_id',
            'grupo_nombre',
            'tipo',
            'periodo',
            'dia',
            'hora',
            'status',
            'created_at',
            'updated_at'
        ];
    }
}
