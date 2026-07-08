<?php

namespace App\Models\Financiero\Descuento;

use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpProducto;
use App\Traits\Financiero\HasDescuentoStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Descuento
 *
 * Representa un ajuste de precio (descuento o sobrecargo) en el sistema financiero.
 * El campo tipo_movimiento distingue el sentido: 'descuento' reduce el precio,
 * 'sobrecargo' lo incrementa (ej. recargo por tarjeta, mora por vencimiento).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $codigo_descuento
 * @property string|null $descripcion
 * @property string $tipo_movimiento 'descuento' | 'sobrecargo'
 * @property string $tipo 'porcentual' | 'valor_fijo' (sobrecargos siempre porcentual)
 * @property float $valor Porcentaje (0-100) o monto fijo
 * @property string $aplicacion 'valor_total'|'matricula'|'cuota'|'valor_recibo'|'saldo_cartera'
 * @property string $tipo_activacion 'pago_anticipado'|'promocion_matricula'|'codigo_promocional'|'medio_pago'|'mora_automatica'
 * @property int|null $dias_anticipacion Solo para pago_anticipado
 * @property bool $permite_acumulacion Siempre false para sobrecargos
 * @property array|null $medios_pago Medios que activan el sobrecargo (solo medio_pago)
 * @property array|null $marca_tarjeta Marcas específicas; null = cualquier marca
 * @property \Carbon\Carbon $fecha_inicio
 * @property \Carbon\Carbon $fecha_fin
 * @property int $status 0=Inactivo, 1=En Proceso, 2=Aprobado, 3=Activo
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection $listasPrecios
 * @property-read \Illuminate\Database\Eloquent\Collection $productos
 * @property-read \Illuminate\Database\Eloquent\Collection $sedes
 * @property-read \Illuminate\Database\Eloquent\Collection $poblaciones
 * @property-read \Illuminate\Database\Eloquent\Collection $descuentosAplicados
 */
class Descuento extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes,
        HasSortingScopes, HasRelationScopes, HasDescuentoStatus;

    // ── tipo_movimiento ───────────────────────────────────────────────────────
    const MOVIMIENTO_DESCUENTO  = 'descuento';
    const MOVIMIENTO_SOBRECARGO = 'sobrecargo';

    // ── tipo ──────────────────────────────────────────────────────────────────
    const TIPO_PORCENTUAL  = 'porcentual';
    const TIPO_VALOR_FIJO  = 'valor_fijo';

    // ── aplicacion ────────────────────────────────────────────────────────────
    const APLICACION_VALOR_TOTAL  = 'valor_total';
    const APLICACION_MATRICULA    = 'matricula';
    const APLICACION_CUOTA        = 'cuota';
    const APLICACION_VALOR_RECIBO = 'valor_recibo';   // sobrecargo sobre el total del recibo
    const APLICACION_SALDO_CARTERA = 'saldo_cartera'; // mora sobre el saldo pendiente de cartera

    // ── tipo_activacion ───────────────────────────────────────────────────────
    const ACTIVACION_PAGO_ANTICIPADO     = 'pago_anticipado';
    const ACTIVACION_PROMOCION_MATRICULA = 'promocion_matricula';
    const ACTIVACION_CODIGO_PROMOCIONAL  = 'codigo_promocional';
    const ACTIVACION_MEDIO_PAGO          = 'medio_pago';      // sobrecargo disparado por el cajero al elegir medio
    const ACTIVACION_MORA_AUTOMATICA     = 'mora_automatica'; // mora calculada por cron diario

    // ── status ────────────────────────────────────────────────────────────────
    const STATUS_INACTIVO   = 0;
    const STATUS_EN_PROCESO = 1;
    const STATUS_APROBADO   = 2;
    const STATUS_ACTIVO     = 3;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'descuentos';

    /**
     * Los atributos que no son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valor'            => 'decimal:2',
        'dias_anticipacion' => 'integer',
        'fecha_inicio'     => 'date',
        'fecha_fin'        => 'date',
        'status'           => 'integer',
        'permite_acumulacion' => 'boolean',
        'medios_pago'      => 'array',
        'marca_tarjeta'    => 'array',
    ];

    /**
     * Relación con LpListaPrecio (muchos a muchos).
     * Un descuento puede aplicarse a múltiples listas de precios.
     *
     * @return BelongsToMany
     */
    public function listasPrecios(): BelongsToMany
    {
        return $this->belongsToMany(
            LpListaPrecio::class,
            'descuento_lista_precio',
            'descuento_id',
            'lista_precio_id'
        )->withTimestamps();
    }

    /**
     * Relación con LpProducto (muchos a muchos).
     * Un descuento puede aplicarse a productos específicos.
     * Si está vacío, aplica a todos los productos de las listas relacionadas.
     *
     * @return BelongsToMany
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            LpProducto::class,
            'descuento_producto',
            'descuento_id',
            'producto_id'
        )->withTimestamps();
    }

    /**
     * Relación con Sede (muchos a muchos).
     * Un descuento puede aplicarse a sedes específicas.
     * Si está vacío, aplica según ciudades o globalmente.
     *
     * @return BelongsToMany
     */
    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(
            Sede::class,
            'descuento_sede',
            'descuento_id',
            'sede_id'
        )->withTimestamps();
    }

    /**
     * Relación con Poblacion (muchos a muchos).
     * Un descuento puede aplicarse a ciudades específicas.
     * Si está vacío, aplica globalmente.
     *
     * @return BelongsToMany
     */
    public function poblaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            Poblacion::class,
            'descuento_poblacion',
            'descuento_id',
            'poblacion_id'
        )->withTimestamps();
    }

    /**
     * Relación con DescuentoAplicado (uno a muchos).
     * Un descuento puede tener múltiples registros de aplicación.
     *
     * @return HasMany
     */
    public function descuentosAplicados(): HasMany
    {
        return $this->hasMany(DescuentoAplicado::class, 'descuento_id');
    }

    /**
     * Relación con RecibosPago (muchos a muchos).
     * Un descuento puede estar aplicado en múltiples recibos de pago.
     * La relación se establece a través de la tabla pivot recibo_pago_descuento.
     *
     * @return BelongsToMany
     */
    public function recibosPago(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Financiero\ReciboPago\ReciboPago::class,
            'recibo_pago_descuento',
            'descuento_id',
            'recibo_pago_id'
        )->withPivot(['valor_descuento', 'valor_original', 'valor_final'])
         ->withTimestamps();
    }

    /**
     * Verifica si el descuento está vigente en una fecha específica.
     * Un descuento está vigente si está activo (status = 3) y la fecha está dentro del rango de vigencia.
     *
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
     * @return bool True si el descuento está vigente, false en caso contrario
     */
    public function estaVigente(?Carbon $fecha = null): bool
    {
        $fecha = $fecha ?? Carbon::now();
        return $fecha->between($this->fecha_inicio, $this->fecha_fin)
            && $this->status === self::STATUS_ACTIVO;
    }

    /**
     * Verifica si el descuento puede activarse según las condiciones proporcionadas.
     *
     * @param Carbon|null $fecha Fecha actual para verificar vigencia
     * @param string|null $codigoPromocional Código promocional (solo para tipo codigo_promocional)
     * @param Carbon|null $fechaPago Fecha de pago (solo para tipo pago_anticipado)
     * @param Carbon|null $fechaProgramada Fecha programada de pago (solo para tipo pago_anticipado)
     * @return bool True si el descuento puede activarse, false en caso contrario
     */
    public function puedeActivar(
        ?Carbon $fecha = null,
        ?string $codigoPromocional = null,
        ?Carbon $fechaPago = null,
        ?Carbon $fechaProgramada = null
    ): bool {
        $fecha = $fecha ?? Carbon::now();

        // Verificar que esté vigente
        if (!$this->estaVigente($fecha)) {
            return false;
        }

        // Verificar según el tipo de activación
        switch ($this->tipo_activacion) {
            case self::ACTIVACION_CODIGO_PROMOCIONAL:
                return $this->puedeActivarPorCodigo($codigoPromocional ?? '');

            case self::ACTIVACION_PAGO_ANTICIPADO:
                if (!$fechaPago || !$fechaProgramada) {
                    return false;
                }
                $diasAnticipacion = $fechaProgramada->diffInDays($fechaPago);
                return $diasAnticipacion >= ($this->dias_anticipacion ?? 0);

            case self::ACTIVACION_PROMOCION_MATRICULA:
                return true;

            // Sobrecargos: la activación la gestiona el cajero (medio_pago) o el cron (mora_automatica)
            case self::ACTIVACION_MEDIO_PAGO:
            case self::ACTIVACION_MORA_AUTOMATICA:
                return true;

            default:
                return false;
        }
    }

    /**
     * Verifica si el descuento puede activarse mediante código promocional.
     *
     * @param string $codigo Código promocional ingresado
     * @return bool True si el código es válido y el descuento puede activarse
     */
    public function puedeActivarPorCodigo(string $codigo): bool
    {
        if ($this->tipo_activacion !== self::ACTIVACION_CODIGO_PROMOCIONAL) {
            return false;
        }

        if (!$this->estaVigente()) {
            return false;
        }

        return strtoupper(trim($codigo)) === strtoupper(trim($this->codigo_descuento ?? ''));
    }

    /**
     * Verifica si el descuento aplica a un producto específico.
     * Si el descuento no tiene productos asociados, aplica a todos.
     *
     * @param int $productoId ID del producto
     * @return bool True si aplica al producto, false en caso contrario
     */
    public function aplicaAProducto(int $productoId): bool
    {
        // Si no tiene productos asociados, aplica a todos
        if ($this->productos()->count() === 0) {
            return true;
        }

        return $this->productos()->where('lp_productos.id', $productoId)->exists();
    }

    /**
     * Verifica si el descuento aplica a una sede específica.
     *
     * @param int $sedeId ID de la sede
     * @return bool True si aplica a la sede, false en caso contrario
     */
    public function aplicaASede(int $sedeId): bool
    {
        // Si tiene sedes específicas, solo aplica a esas sedes
        if ($this->sedes()->count() > 0) {
            return $this->sedes()->where('sedes.id', $sedeId)->exists();
        }

        // Si tiene ciudades asociadas, verificar si la sede pertenece a alguna
        if ($this->poblaciones()->count() > 0) {
            return $this->poblaciones()
                ->whereHas('sedes', function ($query) use ($sedeId) {
                    $query->where('sedes.id', $sedeId);
                })
                ->exists();
        }

        // Si no tiene sedes ni ciudades, aplica globalmente
        return true;
    }

    /**
     * Verifica si el descuento aplica a una población específica.
     *
     * @param int $poblacionId ID de la población
     * @return bool True si aplica a la población, false en caso contrario
     */
    public function aplicaAPoblacion(int $poblacionId): bool
    {
        // Si tiene sedes específicas, verificar si alguna sede pertenece a la población
        if ($this->sedes()->count() > 0) {
            return $this->sedes()
                ->where('poblacion_id', $poblacionId)
                ->exists();
        }

        // Si tiene ciudades asociadas, verificar si la población está incluida
        if ($this->poblaciones()->count() > 0) {
            return $this->poblaciones()->where('poblacions.id', $poblacionId)->exists();
        }

        // Si no tiene sedes ni ciudades, aplica globalmente
        return true;
    }

    /**
     * Calcula el monto de un descuento sobre una base.
     * Para descuentos de valor fijo el resultado no puede exceder el monto base.
     *
     * @param float $monto Monto base
     * @return float Valor absoluto del descuento (nunca negativo)
     */
    public function calcularDescuento(float $monto): float
    {
        if ($monto <= 0) {
            return 0;
        }

        if ($this->tipo === self::TIPO_PORCENTUAL) {
            return max(0, ($monto * $this->valor) / 100);
        }

        return max(0, min($this->valor, $monto));
    }

    /**
     * Calcula el monto de un sobrecargo porcentual sobre una base.
     * Solo aplica para registros con tipo_movimiento='sobrecargo'.
     *
     * @param float $monto Monto base (valor del medio de pago o saldo de cartera)
     * @return float Valor del sobrecargo (nunca negativo)
     */
    public function calcularSobrecargo(float $monto): float
    {
        if ($monto <= 0 || $this->tipo_movimiento !== self::MOVIMIENTO_SOBRECARGO) {
            return 0;
        }

        return max(0, ($monto * $this->valor) / 100);
    }

    /**
     * Indica si este registro es un sobrecargo.
     */
    public function esSobrecargo(): bool
    {
        return $this->tipo_movimiento === self::MOVIMIENTO_SOBRECARGO;
    }

    /**
     * Verifica si este sobrecargo aplica al medio de pago y marca dados.
     *
     * @param string $medioPago Medio de pago del recibo
     * @param string|null $marcaTarjeta Marca de tarjeta (solo para tarjeta_*)
     */
    public function aplicaAMedioPago(string $medioPago, ?string $marcaTarjeta = null): bool
    {
        if ($this->tipo_activacion !== self::ACTIVACION_MEDIO_PAGO) {
            return false;
        }

        $medios = $this->medios_pago ?? [];
        if (!in_array($medioPago, $medios, true)) {
            return false;
        }

        // Si el sobrecargo no restringe por marca, aplica a todas
        if (empty($this->marca_tarjeta)) {
            return true;
        }

        return in_array($marcaTarjeta, $this->marca_tarjeta, true);
    }

    /**
     * Scope: ajustes vigentes (activos y dentro del rango de fechas).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|null $fecha
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVigentes($query, ?Carbon $fecha = null)
    {
        $fecha = $fecha ?? Carbon::now();
        return $query->where('fecha_inicio', '<=', $fecha)
                    ->where('fecha_fin', '>=', $fecha)
                    ->where('status', self::STATUS_ACTIVO);
    }

    /**
     * Scope: solo registros de tipo 'descuento'.
     */
    public function scopeDescuentos($query)
    {
        return $query->where('tipo_movimiento', self::MOVIMIENTO_DESCUENTO);
    }

    /**
     * Scope: solo registros de tipo 'sobrecargo'.
     */
    public function scopeSobrecargos($query)
    {
        return $query->where('tipo_movimiento', self::MOVIMIENTO_SOBRECARGO);
    }

    /**
     * Scope: sobrecargos activos para un medio de pago y opcionalmente una marca de tarjeta.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $medioPago Medio de pago (tarjeta_credito, tarjeta_debito, etc.)
     * @param string|null $marcaTarjeta Marca de tarjeta (visa, mastercard, etc.)
     */
    public function scopePorMedioPago($query, string $medioPago, ?string $marcaTarjeta = null)
    {
        return $query->sobrecargos()
            ->vigentes()
            ->where('tipo_activacion', self::ACTIVACION_MEDIO_PAGO)
            ->whereJsonContains('medios_pago', $medioPago)
            ->when($marcaTarjeta, function ($q) use ($marcaTarjeta) {
                // Si el sobrecargo tiene marcas específicas, filtrar; si no tiene (null), aplica a todas
                $q->where(function ($inner) use ($marcaTarjeta) {
                    $inner->whereNull('marca_tarjeta')
                          ->orWhereJsonContains('marca_tarjeta', $marcaTarjeta);
                });
            });
    }

    /**
     * Scope: sobrecargos de mora automática vigentes.
     */
    public function scopeMoraAutomatica($query)
    {
        return $query->sobrecargos()
            ->vigentes()
            ->where('tipo_activacion', self::ACTIVACION_MORA_AUTOMATICA);
    }

    /**
     * Scope para filtrar por tipo de cálculo.
     */
    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope para filtrar por campo de aplicación.
     */
    public function scopePorAplicacion($query, string $aplicacion)
    {
        return $query->where('aplicacion', $aplicacion);
    }

    /**
     * Scope para filtrar por tipo de activación.
     */
    public function scopePorTipoActivacion($query, string $tipoActivacion)
    {
        return $query->where('tipo_activacion', $tipoActivacion);
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'nombre', 'codigo_descuento', 'tipo_movimiento', 'tipo', 'valor',
            'aplicacion', 'tipo_activacion', 'fecha_inicio', 'fecha_fin',
            'status', 'created_at', 'updated_at',
        ];
    }

    /**
     * Obtiene las relaciones permitidas para este modelo.
     *
     * @return array<string>
     */
    protected function getAllowedRelations(): array
    {
        return [
            'listasPrecios',
            'productos',
            'sedes',
            'poblaciones',
            'descuentosAplicados',
            'listasPrecios.poblaciones',
            'productos.tipoProducto',
            'recibosPago'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array<string>
     */
    protected function getDefaultRelations(): array
    {
        return [];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array<string>
     */
    protected function getCountableRelations(): array
    {
        return [
            'listasPrecios',
            'productos',
            'sedes',
            'poblaciones',
            'descuentosAplicados'
        ];
    }

    /**
     * Crea una nueva instancia de factory para el modelo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return \Database\Factories\Financiero\Descuento\DescuentoFactory::new();
    }
}

