<?php

namespace App\Models\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo CicloCierreNota
 *
 * Bitácora de cierres y reaperturas del registro de notas de un ciclo. El estado
 * vigente vive en `ciclos.notas_cerradas_at`; esta tabla conserva quién y por qué.
 *
 * @property int $id
 * @property int $ciclo_id Ciclo afectado
 * @property string $accion cierre | reapertura
 * @property int $usuario_id Usuario que realizó la acción
 * @property string|null $motivo Motivo (obligatorio al reabrir)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Ciclo $ciclo
 * @property-read User $usuario
 */
class CicloCierreNota extends Model
{
    public const ACCION_CIERRE     = 'cierre';
    public const ACCION_REAPERTURA = 'reapertura';

    protected $table = 'ciclo_cierre_notas';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Ciclo al que pertenece el registro.
     *
     * @return BelongsTo
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    /**
     * Usuario que cerró o reabrió las notas.
     *
     * @return BelongsTo
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
