<?php

namespace App\Models\Academico;

use App\Models\User;
use App\Traits\HasAplazamientoEstado;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Aplazamiento
 *
 * Registra cada aplazamiento aplicado a un ciclo académico.
 * Ciclo de vida: Pendiente → Confirmado | Ampliado | Revertido | Interrumpido
 *
 * @property int              $id
 * @property int              $ciclo_id
 * @property int              $tipo_aplazamiento_id
 * @property int              $user_id
 * @property int|null         $aplazamiento_padre_id
 * @property \Carbon\Carbon   $fecha_aplazamiento
 * @property \Carbon\Carbon   $fecha_inicio_original
 * @property \Carbon\Carbon   $fecha_reinicio_probable
 * @property int              $dias_aplazamiento
 * @property \Carbon\Carbon|null $fecha_reinicio_real
 * @property int|null         $dias_reales
 * @property bool             $mover_cartera
 * @property int              $clases_movidas
 * @property int              $carteras_movidas
 * @property string|null      $observaciones
 * @property int              $estado
 *
 * @property-read Ciclo            $ciclo
 * @property-read TipoAplazamiento $tipoAplazamiento
 * @property-read User             $user
 * @property-read Aplazamiento|null $padre
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Aplazamiento> $hijos
 */
class Aplazamiento extends Model
{
    use HasFactory, HasGenericScopes, HasFilterScopes, HasSortingScopes, HasAplazamientoEstado;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha_aplazamiento'      => 'date',
        'fecha_inicio_original'   => 'date',
        'fecha_reinicio_probable'  => 'date',
        'fecha_reinicio_real'     => 'date',
        'dias_aplazamiento'       => 'integer',
        'dias_reales'             => 'integer',
        'mover_cartera'           => 'boolean',
        'clases_movidas'          => 'integer',
        'carteras_movidas'        => 'integer',
        'estado'                  => 'integer',
    ];

    /**
     * Ciclo al que pertenece el aplazamiento.
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    /**
     * Tipo (razón) del aplazamiento.
     */
    public function tipoAplazamiento(): BelongsTo
    {
        return $this->belongsTo(TipoAplazamiento::class);
    }

    /**
     * Usuario que registró el aplazamiento.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Aplazamiento padre (cuando este es una ampliación).
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(Aplazamiento::class, 'aplazamiento_padre_id');
    }

    /**
     * Aplazamientos hijos generados por ampliación.
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(Aplazamiento::class, 'aplazamiento_padre_id');
    }

    /**
     * Verifica si el aplazamiento puede ser modificado (solo desde Pendiente).
     */
    public function esPendiente(): bool
    {
        return $this->estado === self::getEstadoKey('Pendiente');
    }

    protected function getAllowedSortFields(): array
    {
        return ['fecha_aplazamiento', 'fecha_reinicio_probable', 'dias_aplazamiento', 'estado', 'created_at'];
    }

    protected function getAllowedRelations(): array
    {
        return ['ciclo', 'tipoAplazamiento', 'user', 'padre', 'hijos'];
    }

    protected function getDefaultRelations(): array
    {
        return ['tipoAplazamiento', 'user'];
    }

    protected function getCountableRelations(): array
    {
        return ['hijos'];
    }
}
