<?php

namespace App\Models\Configuracion;

use App\Traits\HasActiveStatus;
use App\Traits\HasDiaNoLaborableFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo DiaNoLaborable
 *
 * Día del calendario en que el instituto no dicta clases. Lo consumen el
 * cálculo de fechas de los ciclos y la generación de clases programadas:
 * una sesión que cae en un día no laborable se omite y se recorre a la
 * siguiente sesión disponible del grupo.
 *
 * Un registro con sede_id NULL aplica a todas las sedes; con sede_id
 * aplica solo a esa sede (cierres locales, festivos municipales).
 *
 * @property int $id Identificador único
 * @property \Carbon\Carbon $fecha Día no laborable
 * @property string $nombre Motivo corto
 * @property string $tipo festivo | vacaciones | institucional | otro
 * @property int|null $sede_id Sede a la que aplica (NULL = todas)
 * @property string|null $observaciones Notas adicionales
 * @property int $status 0: Inactivo, 1: Activo (solo los activos afectan el calendario)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación lógica
 *
 * @property-read \App\Models\Configuracion\Sede|null $sede Sede a la que aplica
 */
class DiaNoLaborable extends Model
{
    use HasFactory, SoftDeletes, HasActiveStatus, HasDiaNoLaborableFilterScopes, HasSortingScopes, HasRelationScopes;

    /** Tipos de día no laborable admitidos. */
    public const TIPOS = [
        'festivo'       => 'Festivo',
        'vacaciones'    => 'Vacaciones',
        'institucional' => 'Institucional',
        'otro'          => 'Otro',
    ];

    protected $table = 'dias_no_laborables';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'fecha'   => 'date',
        'sede_id' => 'integer',
        'status'  => 'integer',
    ];

    /**
     * Relación con Sede (muchos a uno). NULL significa "todas las sedes".
     *
     * @return BelongsTo
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Indica por qué la fecha no se puede registrar para la sede, o null si se puede.
     * Reglas: no repetir la fecha para la misma sede (o para "todas"), y no
     * registrar un día de sede cuando ya existe como día general.
     *
     * @param string   $fecha      Y-m-d
     * @param int|null $sedeId     NULL = todas las sedes
     * @param int|null $ignorarId  Registro a excluir (actualización)
     * @return string|null
     */
    public static function motivoDuplicado(string $fecha, ?int $sedeId, ?int $ignorarId = null): ?string
    {
        $existentes = static::whereDate('fecha', $fecha)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->aplicaASede($sedeId)
            ->get(['sede_id']);

        if ($existentes->contains(fn ($dia) => $dia->sede_id === null)) {
            return "La fecha {$fecha} ya está registrada como día no laborable para todas las sedes.";
        }

        if ($sedeId !== null && $existentes->isNotEmpty()) {
            return "La fecha {$fecha} ya está registrada como día no laborable para esta sede.";
        }

        return null;
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return ['fecha', 'nombre', 'tipo', 'sede_id', 'status', 'created_at', 'updated_at'];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     *
     * @return array
     */
    protected function getAllowedRelations(): array
    {
        return ['sede'];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['sede'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array
     */
    protected function getCountableRelations(): array
    {
        return [];
    }
}
