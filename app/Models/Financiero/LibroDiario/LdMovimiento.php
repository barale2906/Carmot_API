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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modelo LdMovimiento
 *
 * Movimiento manual del libro diario: un egreso de la sede (pago a un tercero)
 * u otro ingreso que no proviene de recibos de pago. Siempre pertenece al turno
 * de caja abierto de quien lo registra.
 *
 * valor_total = subtotal + total_impuestos - total_retenciones
 *
 * @property int $id
 * @property int $turno_caja_id
 * @property int $sede_id
 * @property int $usuario_id
 * @property int $tipo_movimiento_id
 * @property int $clase 1=Egreso, 2=Otro ingreso
 * @property \Carbon\Carbon $fecha
 * @property string $tercero_tipo_identificacion
 * @property string $tercero_identificacion
 * @property string $tercero_nombre
 * @property string $documento_tipo
 * @property string|null $documento_numero
 * @property float $subtotal
 * @property float $total_impuestos
 * @property float $total_retenciones
 * @property float $valor_total
 * @property string $medio_pago
 * @property int|null $banco_id
 * @property string|null $observaciones
 * @property int $status 1=Registrado, 2=Anulado
 * @property-read LdTurnoCaja $turnoCaja
 * @property-read \Illuminate\Database\Eloquent\Collection|LdMovimientoImpuesto[] $impuestos
 * @property-read \Illuminate\Database\Eloquent\Collection|LdSoporte[] $soportes
 */
class LdMovimiento extends Model
{
    use HasFactory, HasLdRegistroFilterScopes, HasLdRegistroStatus, HasSortingScopes;

    public const STATUS_REGISTRADO = 1;

    public const STATUS_ANULADO = 2;

    /** Columnas en que busca el scope search. */
    public const CAMPOS_BUSQUEDA = ['tercero_nombre', 'tercero_identificacion', 'documento_numero'];

    /** Tipos de identificación del tercero. */
    public const TIPOS_IDENTIFICACION = [
        'CC' => 'Cédula de ciudadanía',
        'NIT' => 'NIT',
        'CE' => 'Cédula de extranjería',
        'PA' => 'Pasaporte',
        'TI' => 'Tarjeta de identidad',
    ];

    /** Tipos de documento soporte del movimiento. */
    public const TIPOS_DOCUMENTO = [
        'factura' => 'Factura',
        'cuenta_cobro' => 'Cuenta de cobro',
        'recibo' => 'Recibo',
        'documento_soporte' => 'Documento soporte',
        'otro' => 'Otro',
    ];

    protected $table = 'ld_movimientos';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha' => 'date',
        'clase' => 'integer',
        'subtotal' => 'decimal:2',
        'total_impuestos' => 'decimal:2',
        'total_retenciones' => 'decimal:2',
        'valor_total' => 'decimal:2',
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

    public function tipoMovimiento(): BelongsTo
    {
        return $this->belongsTo(LdTipoMovimiento::class, 'tipo_movimiento_id')->withTrashed();
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
     * Impuestos y retenciones aplicados.
     */
    public function impuestos(): HasMany
    {
        return $this->hasMany(LdMovimientoImpuesto::class, 'movimiento_id');
    }

    /**
     * Archivos que soportan el movimiento (facturas, recibos…).
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
        return ['fecha', 'valor_total', 'tercero_nombre', 'status', 'created_at'];
    }
}
