<?php

namespace App\Models\Financiero\LibroDiario;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo LdTurnoEvento
 *
 * Bitácora de un turno de caja: apertura, cierre, aprobación o rechazo del cierre, reversión y
 * anulaciones de registros después del cierre, con quién y por qué.
 *
 * @property int $id
 * @property int $turno_caja_id
 * @property string $accion
 * @property int|null $usuario_id
 * @property string|null $motivo
 * @property array|null $datos
 * @property \Carbon\Carbon $created_at
 */
class LdTurnoEvento extends Model
{
    public const ACCION_APERTURA = 'apertura';

    public const ACCION_CIERRE = 'cierre';

    public const ACCION_APROBACION = 'aprobacion';

    public const ACCION_RECHAZO = 'rechazo';

    public const ACCION_REVERSION = 'reversion';

    public const ACCION_ANULACION = 'anulacion';

    public const UPDATED_AT = null;

    protected $table = 'ld_turno_eventos';

    protected $guarded = ['id', 'created_at'];

    protected $casts = [
        'datos' => 'array',
    ];

    public function turnoCaja(): BelongsTo
    {
        return $this->belongsTo(LdTurnoCaja::class, 'turno_caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
