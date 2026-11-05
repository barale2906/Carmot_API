<?php

namespace App\Models\Financiero\LibroDiario;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * Modelo LdTurnoCaja
 *
 * Turno de caja de un cajero en una sede. Se abre registrando la base inicial
 * de efectivo, antes de cualquier recibo o registro del libro diario, y se
 * cierra con un arqueo ciego: el cajero registra el efectivo contado sin ver el
 * esperado. El cierre queda Por aprobar hasta que alguien con `fin_ldCierreAprobar`
 * lo aprueba (Cerrado) o lo rechaza (vuelve a Abierto).
 *
 * Un cajero tiene a lo sumo un turno abierto por sede, y puede tener abiertos
 * a la vez turnos en varias de sus sedes (factura para otras sedes). Al facturar
 * con varios turnos abiertos elige desde cuál lo hace (`turno_caja_id`); con uno
 * solo se usa ese. Tras cerrar un turno no puede abrir otro en la misma sede ese
 * día sin una autorización (LdAutorizacionTurno).
 *
 * @property int $id
 * @property int $sede_id
 * @property int $cajero_id
 * @property \Carbon\Carbon $fecha
 * @property float $base_inicial
 * @property \Carbon\Carbon $abierto_at
 * @property int $status 0=Abierto, 1=Cerrado (aprobado), 2=Por aprobar
 * @property \Carbon\Carbon|null $cerrado_at
 * @property int|null $cerrado_por_id
 * @property float|null $efectivo_esperado
 * @property float|null $efectivo_contado
 * @property float|null $diferencia
 * @property array|null $resumen Totales congelados al cierre
 * @property string|null $observaciones_cierre
 * @property bool $ajustado Se anuló un registro después del cierre
 * @property int|null $autorizacion_id
 * @property int|null $aprobado_por_id
 * @property \Carbon\Carbon|null $aprobado_at
 * @property string|null $observaciones_aprobacion
 * @property-read \Illuminate\Database\Eloquent\Collection|ReciboPago[] $recibos
 * @property-read \Illuminate\Database\Eloquent\Collection|LdMovimiento[] $movimientos
 * @property-read \Illuminate\Database\Eloquent\Collection|LdConsignacion[] $consignaciones
 * @property-read \Illuminate\Database\Eloquent\Collection|LdTurnoEvento[] $eventos
 */
class LdTurnoCaja extends Model
{
    use HasFactory, HasSortingScopes;

    public const STATUS_ABIERTO = 0;

    public const STATUS_CERRADO = 1;

    public const STATUS_POR_APROBAR = 2;

    /** Mensaje de validación cuando se intenta registrar un pago sin turno abierto. */
    public const MENSAJE_SIN_TURNO = 'No tiene un turno de caja abierto. Abra el turno registrando la base inicial antes de generar pagos.';

    /** Mensaje cuando el cajero tiene caja abierta en varias sedes y no indicó desde cuál factura. */
    public const MENSAJE_ELEGIR_TURNO = 'Tiene caja abierta en varias sedes. Elija desde cuál sede está facturando.';

    /** Mensaje cuando el turno indicado no es un turno abierto del cajero. */
    public const MENSAJE_TURNO_INVALIDO = 'El turno de caja elegido no está abierto o no le pertenece.';

    protected $table = 'ld_turnos_caja';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha' => 'date',
        'base_inicial' => 'decimal:2',
        'abierto_at' => 'datetime',
        'status' => 'integer',
        'cerrado_at' => 'datetime',
        'efectivo_esperado' => 'decimal:2',
        'efectivo_contado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'resumen' => 'array',
        'ajustado' => 'boolean',
        'aprobado_at' => 'datetime',
    ];

    /**
     * Opciones de estado del turno.
     *
     * @return array<int, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_ABIERTO => 'Abierto',
            self::STATUS_CERRADO => 'Cerrado',
            self::STATUS_POR_APROBAR => 'Por aprobar',
        ];
    }

    /**
     * Indica si el usuario supervisa cajas (aprueba cierres o supervisa turnos): ve los
     * turnos de sus sedes y el arqueo (efectivo esperado y diferencia). El cajero hace
     * un conteo ciego y no ve esos valores.
     */
    public static function supervisaCaja(?User $usuario): bool
    {
        return $usuario !== null && ($usuario->can('fin_ldCierreAprobar') || $usuario->can('fin_ldTurnos'));
    }

    /**
     * Indica si el usuario puede ver el arqueo de este turno (efectivo esperado y
     * diferencia) y todo lo que mueve efectivo: quien supervisa cajas y no es el cajero
     * del turno, o cualquiera con acceso al turno una vez aprobado el cierre (Cerrado).
     *
     * Mientras el turno está abierto o por aprobar, su cajero solo ve los pagos por
     * medios distintos al efectivo (N26): con la base, los ingresos y egresos en efectivo
     * y las consignaciones calcularía el esperado. No se libera en "Por aprobar" porque un
     * cierre rechazado vuelve a Abierto para recontar.
     */
    public function arqueoVisiblePara(?User $usuario): bool
    {
        if ($this->status === self::STATUS_CERRADO) {
            return true;
        }

        return self::supervisaCaja($usuario) && $usuario->id !== $this->cajero_id;
    }

    /**
     * Resumen que ve el cajero antes de que se apruebe su cierre: solo los medios de pago
     * distintos al efectivo y las transferencias por aprobar (N26). `restringido` le indica
     * a la interfaz que no hay totales que mostrar.
     *
     * @param  array<string, mixed>  $resumen
     * @return array<string, mixed>
     */
    public static function resumenSinEfectivo(array $resumen): array
    {
        return [
            'restringido' => true,
            'por_medio_pago' => Arr::except($resumen['por_medio_pago'] ?? [], 'efectivo'),
            'transferencias_pendientes' => $resumen['transferencias_pendientes'] ?? null,
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cajero_id');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    public function autorizacion(): BelongsTo
    {
        return $this->belongsTo(LdAutorizacionTurno::class, 'autorizacion_id');
    }

    /**
     * Recibos de pago generados en el turno.
     */
    public function recibos(): HasMany
    {
        return $this->hasMany(ReciboPago::class, 'turno_caja_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(LdMovimiento::class, 'turno_caja_id');
    }

    public function consignaciones(): HasMany
    {
        return $this->hasMany(LdConsignacion::class, 'turno_caja_id');
    }

    /**
     * Bitácora del turno, de la más reciente a la más antigua.
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(LdTurnoEvento::class, 'turno_caja_id')->latest('id');
    }

    /**
     * Scope de los turnos abiertos de un cajero (uno por sede como máximo): los pagos
     * de cualquier sede se pueden recibir desde cualquier caja abierta.
     */
    public function scopeAbiertoDe($query, int $cajeroId)
    {
        return $query->where('cajero_id', $cajeroId)
            ->where('status', self::STATUS_ABIERTO);
    }

    /**
     * Turno abierto con el que el cajero factura: el indicado en `$turnoId` si es un
     * turno abierto suyo o, sin indicarlo, el único que tenga abierto. Devuelve null si
     * no tiene ninguno, si el indicado no es válido o si tiene varios y no eligió.
     */
    public static function paraFacturar(int $cajeroId, ?int $turnoId = null): ?self
    {
        $abiertos = self::abiertoDe($cajeroId)->get();

        if ($turnoId) {
            return $abiertos->firstWhere('id', $turnoId);
        }

        return $abiertos->count() === 1 ? $abiertos->first() : null;
    }

    /**
     * Sede donde se recibe el pago al facturar: la del turno con que se factura (ver
     * paraFacturar). El recibo siempre queda en esa sede, aunque la matrícula o el
     * pedido sean de otra; null si no hay turno resoluble.
     */
    public static function sedeParaFacturar(int $cajeroId, ?int $turnoId = null): ?int
    {
        return self::paraFacturar($cajeroId, $turnoId)?->sede_id;
    }

    /**
     * Error de validación al facturar sin un turno resoluble (ver paraFacturar), o null si
     * hay turno: sin caja abierta, turno indicado inválido o varias cajas sin elegir.
     */
    public static function errorParaFacturar(int $cajeroId, ?int $turnoId = null): ?string
    {
        $idsAbiertos = self::abiertoDe($cajeroId)->pluck('id');

        return match (true) {
            $idsAbiertos->isEmpty() => self::MENSAJE_SIN_TURNO,
            (bool) $turnoId => $idsAbiertos->contains($turnoId) ? null : self::MENSAJE_TURNO_INVALIDO,
            $idsAbiertos->count() > 1 => self::MENSAJE_ELEGIR_TURNO,
            default => null,
        };
    }

    /**
     * Scope para limitar a las sedes indicadas (las accesibles por el usuario).
     *
     * @param  array<int>  $sedeIds
     */
    public function scopeDeSedes($query, array $sedeIds)
    {
        return $query->whereIn('sede_id', $sedeIds);
    }

    /**
     * Scope de los turnos que el usuario puede consultar: los de sus sedes y, si no
     * supervisa cajas, solo los propios.
     */
    public function scopeVisiblesPara($query, User $usuario)
    {
        return $query->deSedes($usuario->idsSedesAccesibles())
            ->when(! self::supervisaCaja($usuario), fn ($q) => $q->where('cajero_id', $usuario->id));
    }

    /**
     * Scope compuesto: sede_id, cajero_id, status, fecha_desde, fecha_hasta.
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(! empty($filters['sede_id']), fn ($q) => $q->where('sede_id', (int) $filters['sede_id']))
            ->when(! empty($filters['cajero_id']), fn ($q) => $q->where('cajero_id', (int) $filters['cajero_id']))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['fecha_desde']), fn ($q) => $q->whereDate('fecha', '>=', $filters['fecha_desde']))
            ->when(! empty($filters['fecha_hasta']), fn ($q) => $q->whereDate('fecha', '<=', $filters['fecha_hasta']));
    }

    /**
     * Indica si el turno está abierto.
     */
    public function estaAbierto(): bool
    {
        return $this->status === self::STATUS_ABIERTO;
    }

    /**
     * Indica si el cierre del turno espera aprobación.
     */
    public function estaPorAprobar(): bool
    {
        return $this->status === self::STATUS_POR_APROBAR;
    }

    /**
     * Nombre del PDF del cierre: fecha en que se abrió el turno + id, p. ej. cierre-caja-2026-11-03-7.pdf.
     */
    public function nombreArchivoPdf(): string
    {
        return "cierre-caja-{$this->fecha->format('Y-m-d')}-{$this->id}.pdf";
    }

    /**
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['fecha', 'abierto_at', 'cerrado_at', 'status', 'created_at'];
    }
}
