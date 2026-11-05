<?php

namespace App\Models\Configuracion;

use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Traits\HasActiveStatus;
use App\Traits\HasCuentaBancariaFilterScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Modelo CuentaBancaria
 *
 * Cuenta del instituto donde se recibe el dinero de los pagos por transferencia.
 * Una cuenta aplica a las sedes asociadas explícitamente (pivote cuenta_bancaria_sede)
 * y/o a todas las sedes de una ciudad (poblacion_id). Al registrar una transferencia
 * solo se ofrecen las cuentas activas que aplican a la sede donde se recibe el pago
 * (la del turno de caja con que se factura).
 *
 * @property int $id Identificador único
 * @property int|null $poblacion_id Ciudad cuyas sedes reciben pagos en la cuenta
 * @property int $banco_id Entidad bancaria de la cuenta
 * @property string $tipo_cuenta ahorros, corriente o billetera (ver TIPOS)
 * @property string $numero_cuenta Número de la cuenta o celular de la billetera
 * @property string|null $titular Titular de la cuenta
 * @property string|null $descripcion Alias o nota para identificarla
 * @property int $status 0=Inactivo, 1=Activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read string $etiqueta Texto para selectores: "Banco · Tipo · Número"
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Sede> $sedes Sedes asociadas explícitamente
 * @property-read \App\Models\Configuracion\Poblacion|null $poblacion Ciudad a cuyas sedes aplica
 * @property-read \App\Models\Configuracion\Banco $banco
 * @property-read \Illuminate\Database\Eloquent\Collection|ReciboPagoMedioPago[] $mediosPago
 */
class CuentaBancaria extends Model
{
    use HasActiveStatus, HasCuentaBancariaFilterScopes, HasFactory, HasSortingScopes, SoftDeletes;

    /**
     * Tipos de cuenta admitidos y su etiqueta para mostrar.
     */
    public const TIPOS = [
        'ahorros' => 'Ahorros',
        'corriente' => 'Corriente',
        'billetera' => 'Billetera digital',
    ];

    protected $table = 'cuentas_bancarias';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'poblacion_id' => 'integer',
        'banco_id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Sedes asociadas explícitamente a la cuenta.
     */
    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'cuenta_bancaria_sede')->withTimestamps();
    }

    /**
     * Ciudad (población) a cuyas sedes aplica la cuenta.
     */
    public function poblacion(): BelongsTo
    {
        return $this->belongsTo(Poblacion::class);
    }

    /**
     * Entidad bancaria de la cuenta.
     */
    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class)->withTrashed();
    }

    /**
     * Pagos por transferencia recibidos en esta cuenta.
     */
    public function mediosPago(): HasMany
    {
        return $this->hasMany(ReciboPagoMedioPago::class);
    }

    /**
     * Scope de las cuentas que aplican a una sede: asociadas a ella o a su ciudad.
     */
    public function scopeAplicaASede($query, int $sedeId)
    {
        $poblacionId = Sede::whereKey($sedeId)->value('poblacion_id');

        return $query->where(function ($q) use ($sedeId, $poblacionId) {
            $q->whereHas('sedes', fn ($s) => $s->whereKey($sedeId))
                ->when($poblacionId, fn ($w) => $w->orWhere('poblacion_id', $poblacionId));
        });
    }

    /**
     * Scope de las cuentas activas que aplican a una sede (las que se ofrecen al registrar un pago).
     */
    public function scopeActivasDeSede($query, int $sedeId)
    {
        return $query->aplicaASede($sedeId)->where('status', 1);
    }

    /**
     * Texto para selectores y comprobantes: "Bancolombia · Ahorros · 123-456".
     */
    public function getEtiquetaAttribute(): string
    {
        return collect([
            $this->banco?->nombre,
            self::TIPOS[$this->tipo_cuenta] ?? $this->tipo_cuenta,
            $this->numero_cuenta,
        ])->filter()->implode(' · ');
    }

    /**
     * Campos del medio de pago que se derivan de la cuenta destino de una transferencia:
     * la cuenta, su banco y el nombre del banco (campo de texto legado del recibo).
     *
     * @return array{cuenta_bancaria_id: int|null, banco_id: int|null, banco: string|null}
     */
    public static function datosParaMedioPago(?int $cuentaId): array
    {
        $cuenta = $cuentaId ? self::with('banco')->find($cuentaId) : null;

        return [
            'cuenta_bancaria_id' => $cuenta?->id,
            'banco_id' => $cuenta?->banco_id,
            'banco' => $cuenta?->banco?->nombre,
        ];
    }

    /**
     * Regla de validación: la cuenta está activa y aplica a la sede indicada (la sede
     * donde se recibe el pago). Sin sede no admite ninguna cuenta.
     */
    public static function reglaActivaDeSede(?int $sedeId): In
    {
        return Rule::in($sedeId ? self::activasDeSede($sedeId)->pluck('id')->all() : []);
    }

    /**
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['numero_cuenta', 'tipo_cuenta', 'titular', 'poblacion_id', 'banco_id', 'status', 'created_at', 'updated_at'];
    }
}
