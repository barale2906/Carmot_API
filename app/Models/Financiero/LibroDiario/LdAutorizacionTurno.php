<?php

namespace App\Models\Financiero\LibroDiario;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo LdAutorizacionTurno
 *
 * Permiso de un solo uso para que un cajero abra un nuevo turno de caja el
 * mismo día en que ya cerró uno. Se consume al abrir el turno (turno_caja_id).
 *
 * @property int $id
 * @property int $cajero_id
 * @property \Carbon\Carbon $fecha
 * @property int $autorizado_por_id
 * @property string $motivo
 * @property int|null $turno_caja_id Turno que consumió la autorización
 */
class LdAutorizacionTurno extends Model
{
    use HasFactory;

    protected $table = 'ld_autorizaciones_turno';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cajero_id');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por_id');
    }

    public function turnoCaja(): BelongsTo
    {
        return $this->belongsTo(LdTurnoCaja::class, 'turno_caja_id');
    }

    /**
     * Scope de autorizaciones aún no usadas de un cajero para una fecha.
     */
    public function scopeDisponibleDe($query, int $cajeroId, string $fecha)
    {
        return $query->where('cajero_id', $cajeroId)
            ->whereDate('fecha', $fecha)
            ->whereNull('turno_caja_id');
    }
}
