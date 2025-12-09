<?php

namespace App\Models\Financiero\ReciboPago;

use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\User;
use App\Traits\Financiero\HasReciboPagoStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Services\Financiero\ReciboPagoNumeracionService;

/**
 * Modelo ReciboPago
 *
 * Representa un recibo de pago en el sistema financiero.
 * Un recibo de pago registra todos los pagos que ingresan al instituto por diferentes conceptos
 * que se muestran en las listas de precios. Incluye información sobre productos, descuentos,
 * medios de pago y relaciones con matrículas.
 *
 * @property int $id Identificador único del recibo de pago
 * @property string $numero_recibo Número completo del recibo (prefijo + consecutivo)
 * @property int $consecutivo Consecutivo por sede y origen
 * @property string $prefijo Prefijo de la sede según origen
 * @property int $origen Tipo de origen (0=Inventarios, 1=Académico)
 * @property \Carbon\Carbon $fecha_recibo Fecha del recibo
 * @property \Carbon\Carbon $fecha_transaccion Momento en que ingresó el dinero
 * @property float $valor_total Valor total del recibo
 * @property float $descuento_total Descuento total aplicado
 * @property string|null $banco Banco donde ingresó el dinero
 * @property int $status Estado del recibo (0=En proceso, 1=Creado, 2=Cerrado, 3=Anulado)
 * @property int|null $cierre Número de cierre de caja
 * @property int $sede_id ID de la sede que genera el recibo
 * @property int|null $estudiante_id ID del estudiante (User)
 * @property int $cajero_id ID del cajero (User) que genera el recibo
 * @property int|null $matricula_id ID de la matrícula asociada
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Configuracion\Sede $sede Sede que genera el recibo
 * @property-read \App\Models\User|null $estudiante Estudiante asociado al recibo
 * @property-read \App\Models\User $cajero Cajero que genera el recibo
 * @property-read \App\Models\Academico\Matricula|null $matricula Matrícula asociada al recibo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\ConceptoPago\ConceptoPago> $conceptosPago Conceptos de pago del recibo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpListaPrecio> $listasPrecio Listas de precios utilizadas
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpProducto> $productos Productos incluidos en el recibo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Descuento\Descuento> $descuentos Descuentos aplicados
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\ReciboPago\ReciboPagoMedioPago> $mediosPago Medios de pago utilizados
 */
class ReciboPago extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes, HasReciboPagoStatus;

    /**
     * Constante para el estado En Proceso.
     */
    const STATUS_EN_PROCESO = 0;

    /**
     * Constante para el estado Creado.
     */
    const STATUS_CREADO = 1;

    /**
     * Constante para el estado Cerrado.
     */
    const STATUS_CERRADO = 2;

    /**
     * Constante para el estado Anulado.
     */
    const STATUS_ANULADO = 3;

    /**
     * Constante para el origen Inventarios.
     */
    const ORIGEN_INVENTARIOS = 0;

    /**
     * Constante para el origen Académico.
     */
    const ORIGEN_ACADEMICO = 1;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'recibos_pago';

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
        'fecha_recibo' => 'date',
        'fecha_transaccion' => 'datetime',
        'valor_total' => 'decimal:2',
        'descuento_total' => 'decimal:2',
        'status' => 'integer',
        'origen' => 'integer',
        'consecutivo' => 'integer',
        'cierre' => 'integer',
    ];

    /**
     * Boot del modelo para eventos automáticos.
     * Genera automáticamente el número de recibo si no existe antes de crear el registro.
     * Esto asegura que la generación del número ocurra dentro de la misma transacción de inserción,
     * evitando condiciones de carrera cuando se crean múltiples recibos simultáneamente.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reciboPago) {
            // Si no tiene número de recibo, generarlo automáticamente
            if (empty($reciboPago->numero_recibo) && $reciboPago->sede_id && isset($reciboPago->origen)) {
                try {
                    $numeracionService = app(ReciboPagoNumeracionService::class);
                    
                    // El servicio maneja transacciones y locks para evitar duplicados
                    $numeroRecibo = $numeracionService->generarNumeroRecibo($reciboPago->sede_id, $reciboPago->origen);
                    
                    // Extraer el consecutivo del número generado (formato: PREFIJO-0001)
                    preg_match('/-(\d+)$/', $numeroRecibo, $matches);
                    $consecutivo = isset($matches[1]) ? (int)$matches[1] : 1;
                    
                    // Obtener el prefijo de la sede
                    $sede = Sede::find($reciboPago->sede_id);
                    if ($sede) {
                        $prefijo = $reciboPago->origen === self::ORIGEN_ACADEMICO
                            ? $sede->codigo_academico
                            : $sede->codigo_inventario;
                        
                        $reciboPago->numero_recibo = $numeroRecibo;
                        $reciboPago->consecutivo = $consecutivo;
                        $reciboPago->prefijo = $prefijo;
                    }
                } catch (\Exception $e) {
                    // Si hay un error, dejar que el modelo falle la creación
                    throw $e;
                }
            }
        });
    }

    /**
     * Relación con Sede (muchos a uno).
     * Un recibo de pago pertenece a una sede.
     *
     * @return BelongsTo
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Relación con User - Estudiante (muchos a uno).
     * Un recibo de pago puede pertenecer a un estudiante.
     *
     * @return BelongsTo
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /**
     * Relación con User - Cajero (muchos a uno).
     * Un recibo de pago es generado por un cajero.
     *
     * @return BelongsTo
     */
    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cajero_id');
    }

    /**
     * Relación con Matricula (muchos a uno).
     * Un recibo de pago puede estar asociado a una matrícula.
     *
     * @return BelongsTo
     */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    /**
     * Relación con ConceptoPago (muchos a muchos).
     * Un recibo de pago puede tener múltiples conceptos de pago.
     * La relación se establece a través de la tabla pivot recibo_pago_concepto_pago.
     *
     * @return BelongsToMany
     */
    public function conceptosPago(): BelongsToMany
    {
        return $this->belongsToMany(ConceptoPago::class, 'recibo_pago_concepto_pago', 'recibo_pago_id', 'concepto_pago_id')
                    ->withPivot(['valor', 'tipo', 'producto', 'cantidad', 'unitario', 'subtotal', 'id_relacional', 'observaciones'])
                    ->withTimestamps();
    }

    /**
     * Relación con LpListaPrecio (muchos a muchos).
     * Un recibo de pago puede utilizar múltiples listas de precios.
     * La relación se establece a través de la tabla pivot recibo_pago_lista_precio.
     *
     * @return BelongsToMany
     */
    public function listasPrecio(): BelongsToMany
    {
        return $this->belongsToMany(LpListaPrecio::class, 'recibo_pago_lista_precio', 'recibo_pago_id', 'lista_precio_id')
                    ->withTimestamps();
    }

    /**
     * Relación con LpProducto (muchos a muchos).
     * Un recibo de pago puede incluir múltiples productos.
     * La relación se establece a través de la tabla pivot recibo_pago_producto.
     *
     * @return BelongsToMany
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(LpProducto::class, 'recibo_pago_producto', 'recibo_pago_id', 'producto_id')
                    ->withPivot(['cantidad', 'precio_unitario', 'subtotal'])
                    ->withTimestamps();
    }

    /**
     * Relación con Descuento (muchos a muchos).
     * Un recibo de pago puede tener múltiples descuentos aplicados.
     * La relación se establece a través de la tabla pivot recibo_pago_descuento.
     *
     * @return BelongsToMany
     */
    public function descuentos(): BelongsToMany
    {
        return $this->belongsToMany(Descuento::class, 'recibo_pago_descuento', 'recibo_pago_id', 'descuento_id')
                    ->withPivot(['valor_descuento', 'valor_original', 'valor_final'])
                    ->withTimestamps();
    }

    /**
     * Relación con ReciboPagoMedioPago (uno a muchos).
     * Un recibo de pago puede tener múltiples medios de pago.
     *
     * @return HasMany
     */
    public function mediosPago(): HasMany
    {
        return $this->hasMany(\App\Models\Financiero\ReciboPago\ReciboPagoMedioPago::class, 'recibo_pago_id');
    }

    /**
     * Scope para filtrar por sede.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $sedeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySede($query, $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    /**
     * Scope para filtrar por estudiante.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $estudianteId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByEstudiante($query, $estudianteId)
    {
        return $query->where('estudiante_id', $estudianteId);
    }

    /**
     * Scope para filtrar por cajero.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $cajeroId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCajero($query, $cajeroId)
    {
        return $query->where('cajero_id', $cajeroId);
    }

    /**
     * Scope para filtrar por origen.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $origen
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByOrigen($query, $origen)
    {
        return $query->where('origen', $origen);
    }

    /**
     * Scope para filtrar por estado.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para filtrar por rango de fechas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFechaRange($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_recibo', [$fechaInicio, $fechaFin]);
    }

    /**
     * Scope para filtrar por número de cierre.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $cierre
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCierre($query, $cierre)
    {
        return $query->where('cierre', $cierre);
    }

    /**
     * Scope para filtrar por matrícula.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $matriculaId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByMatricula($query, $matriculaId)
    {
        return $query->where('matricula_id', $matriculaId);
    }

    /**
     * Scope para filtrar por producto vendido.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $productoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByProducto($query, $productoId)
    {
        return $query->whereHas('productos', function ($q) use ($productoId) {
            $q->where('producto_id', $productoId);
        });
    }

    /**
     * Scope para filtrar por población (a través de sede).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $poblacionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPoblacion($query, $poblacionId)
    {
        return $query->whereHas('sede', function ($q) use ($poblacionId) {
            $q->where('poblacion_id', $poblacionId);
        });
    }

    /**
     * Scope para filtrar recibos vigentes (no anulados).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVigentes($query)
    {
        return $query->where('status', '!=', self::STATUS_ANULADO);
    }

    /**
     * Obtiene el siguiente consecutivo para una sede y origen específicos.
     *
     * @param int $sedeId ID de la sede
     * @param int $origen Origen del recibo (0=Inventarios, 1=Académico)
     * @return int Siguiente consecutivo
     */
    public static function obtenerConsecutivo(int $sedeId, int $origen): int
    {
        return DB::transaction(function () use ($sedeId, $origen) {
            $ultimoRecibo = static::where('sede_id', $sedeId)
                ->where('origen', $origen)
                ->lockForUpdate()
                ->orderBy('consecutivo', 'desc')
                ->first();

            return ($ultimoRecibo ? $ultimoRecibo->consecutivo : 0) + 1;
        });
    }

    /**
     * Genera el número de recibo completo (prefijo + consecutivo).
     *
     * @param int $sedeId ID de la sede
     * @param int $origen Origen del recibo
     * @return string Número de recibo completo
     */
    public static function generarNumeroRecibo(int $sedeId, int $origen): string
    {
        $sede = Sede::findOrFail($sedeId);

        $prefijo = $origen === self::ORIGEN_ACADEMICO
            ? $sede->codigo_academico
            : $sede->codigo_inventario;

        if (!$prefijo) {
            throw new \Exception("La sede no tiene código configurado para el origen especificado.");
        }

        $consecutivo = self::obtenerConsecutivo($sedeId, $origen);
        $consecutivoFormateado = str_pad($consecutivo, 4, '0', STR_PAD_LEFT);

        return "{$prefijo}-{$consecutivoFormateado}";
    }

    /**
     * Calcula los totales del recibo basándose en los productos y descuentos.
     *
     * @return array Array con valor_total y descuento_total
     */
    public function calcularTotales(): array
    {
        $valorTotal = 0;
        $descuentoTotal = 0;

        // Sumar subtotales de conceptos de pago
        $valorTotal += $this->conceptosPago()->sum('subtotal');

        // Sumar subtotales de productos
        $valorTotal += $this->productos()->sum('subtotal');

        // Sumar descuentos aplicados
        $descuentoTotal = $this->descuentos()->sum('valor_descuento');

        return [
            'valor_total' => $valorTotal,
            'descuento_total' => $descuentoTotal,
        ];
    }

    /**
     * Valida que la suma de medios de pago sea igual al valor total.
     *
     * @return bool True si la suma es correcta, false en caso contrario
     */
    public function validarMediosPago(): bool
    {
        $sumaMediosPago = $this->mediosPago()->sum('valor');
        return abs($sumaMediosPago - $this->valor_total) < 0.01; // Tolerancia para decimales
    }

    /**
     * Anula el recibo cambiando su estado a ANULADO.
     *
     * @return bool True si se anuló correctamente
     */
    public function anular(): bool
    {
        if ($this->status === self::STATUS_CERRADO) {
            throw new \Exception("No se puede anular un recibo cerrado.");
        }

        return $this->update(['status' => self::STATUS_ANULADO]);
    }

    /**
     * Cierra el recibo cambiando su estado a CERRADO.
     *
     * @param int|null $numeroCierre Número de cierre de caja
     * @return bool True si se cerró correctamente
     */
    public function cerrar(?int $numeroCierre = null): bool
    {
        if ($this->status === self::STATUS_ANULADO) {
            throw new \Exception("No se puede cerrar un recibo anulado.");
        }

        $data = ['status' => self::STATUS_CERRADO];
        if ($numeroCierre !== null) {
            $data['cierre'] = $numeroCierre;
        }

        return $this->update($data);
    }

    /**
     * Verifica si el recibo está anulado.
     *
     * @return bool True si está anulado
     */
    public function estaAnulado(): bool
    {
        return $this->status === self::STATUS_ANULADO;
    }

    /**
     * Verifica si el recibo está cerrado.
     *
     * @return bool True si está cerrado
     */
    public function estaCerrado(): bool
    {
        return $this->status === self::STATUS_CERRADO;
    }

    /**
     * Verifica si el recibo está en proceso.
     *
     * @return bool True si está en proceso
     */
    public function estaEnProceso(): bool
    {
        return $this->status === self::STATUS_EN_PROCESO;
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'numero_recibo',
            'consecutivo',
            'fecha_recibo',
            'fecha_transaccion',
            'valor_total',
            'descuento_total',
            'status',
            'origen',
            'cierre',
            'created_at',
            'updated_at'
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
            'sede',
            'estudiante',
            'cajero',
            'matricula',
            'conceptosPago',
            'listasPrecio',
            'productos',
            'descuentos',
            'mediosPago',
            'sede.poblacion',
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array<string>
     */
    protected function getDefaultRelations(): array
    {
        return ['sede', 'cajero'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array<string>
     */
    protected function getCountableRelations(): array
    {
        return [
            'conceptosPago',
            'listasPrecio',
            'productos',
            'descuentos',
            'mediosPago'
        ];
    }
}

