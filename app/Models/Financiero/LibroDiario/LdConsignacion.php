<?php

namespace App\Models\Financiero\LibroDiario;

use App\Models\Configuracion\Banco;
use App\Models\Configuracion\Sede;
use App\Models\User;
use App\Traits\Financiero\HasLdRegistroFilterScopes;
use App\Traits\Financiero\HasLdRegistroStatus;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modelo LdConsignacion
 *
 * Consignación del efectivo de la caja en una cuenta bancaria. No es un gasto:
 * es un traslado que descuenta el efectivo esperado del turno.
 *
 * @property int $id
 * @property int $turno_caja_id
 * @property int $sede_id
 * @property int $usuario_id
 * @property \Carbon\Carbon $fecha
 * @property int $banco_id
 * @property float $valor
 * @property string $titular A nombre de quién se consigna
 * @property string|null $numero_cuenta
 * @property string|null $numero_comprobante
 * @property string|null $observaciones
 * @property int $status 1=Registrado, 2=Anulado
 */
class LdConsignacion extends Model
{
    use HasFactory, HasLdRegistroFilterScopes, HasLdRegistroStatus, HasSortingScopes;

    public const STATUS_REGISTRADO = 1;

    public const STATUS_ANULADO = 2;

    /** Columnas en que busca el scope search. */
    public const CAMPOS_BUSQUEDA = ['titular', 'numero_comprobante', 'numero_cuenta'];

    protected $table = 'ld_consignaciones';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha' => 'date',
        'valor' => 'decimal:2',
        'status' => 'integer',
        'anulado_at' => 'datetime',
    ];

    public function turnoCaja(): BelongsTo
    {
        return $this->belongsTo(LdTurnoCaja::class, 'turno_caja_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class)->withTrashed();
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_id');
    }

    /**
     * Archivos que soportan la consignación (comprobante del banco).
     */
    public function soportes(): MorphMany
    {
        return $this->morphMany(LdSoporte::class, 'soportable');
    }

    /**
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['fecha', 'valor', 'titular', 'status', 'created_at'];
    }
}
