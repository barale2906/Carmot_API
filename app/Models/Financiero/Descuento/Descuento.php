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
 * Representa un descuento en el sistema financiero.
 * Los descuentos pueden aplicarse a productos dentro de listas de precios,
 * con capacidad de variación por ubicación y condiciones de activación.
 *
 * @property int $id Identificador único del descuento
 * @property string $nombre Nombre descriptivo del descuento
 * @property string|null $codigo_descuento Código promocional alfanumérico único
 * @property string|null $descripcion Descripción del descuento
 * @property string $tipo Tipo de descuento: 'porcentual' o 'valor_fijo'
 * @property float $valor Valor del descuento (porcentaje 0-100 o monto fijo)
 * @property string $aplicacion Aplicación: 'valor_total', 'matricula' o 'cuota'
 * @property string $tipo_activacion Tipo de activación: 'pago_anticipado', 'promocion_matricula' o 'codigo_promocional'
 * @property int|null $dias_anticipacion Días mínimos de anticipación (solo para pago anticipado)
 * @property bool $permite_acumulacion Indica si el descuento puede acumularse con otros
 * @property \Carbon\Carbon $fecha_inicio Fecha de inicio de vigencia
 * @property \Carbon\Carbon $fecha_fin Fecha de fin de vigencia
 * @property int $status Estado del descuento (0: inactivo, 1: en proceso, 2: aprobado, 3: activo)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpListaPrecio> $listasPrecios Listas de precios donde aplica
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpProducto> $productos Productos específicos donde aplica (si está vacío, aplica a todos)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Configuracion\Sede> $sedes Sedes específicas donde aplica (si está vacío, aplica según ciudades o globalmente)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Configuracion\Poblacion> $poblaciones Ciudades donde aplica (si está vacío, aplica globalmente)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Descuento\DescuentoAplicado> $descuentosAplicados Historial de descuentos aplicados
 */
class Descuento extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes,
        HasSortingScopes, HasRelationScopes, HasDescuentoStatus;

    /**
     * Constante para tipo de descuento porcentual.
     */
    const TIPO_PORCENTUAL = 'porcentual';

    /**
     * Constante para tipo de descuento valor fijo.
     */
    const TIPO_VALOR_FIJO = 'valor_fijo';

    /**
     * Constante para aplicación al valor total.
     */
    const APLICACION_VALOR_TOTAL = 'valor_total';

    /**
     * Constante para aplicación a la matrícula.
     */
    const APLICACION_MATRICULA = 'matricula';

    /**
     * Constante para aplicación a la cuota.
     */
    const APLICACION_CUOTA = 'cuota';

    /**
     * Constante para activación por pago anticipado.
     */
    const ACTIVACION_PAGO_ANTICIPADO = 'pago_anticipado';

    /**
     * Constante para activación por promoción de matrícula.
     */
    const ACTIVACION_PROMOCION_MATRICULA = 'promocion_matricula';

    /**
     * Constante para activación por código promocional.
     */
    const ACTIVACION_CODIGO_PROMOCIONAL = 'codigo_promocional';

    /**
     * Constante para el estado Inactivo.
     */
    const STATUS_INACTIVO = 0;

    /**
     * Constante para el estado En Proceso.
     */
    const STATUS_EN_PROCESO = 1;

    /**
     * Constante para el estado Aprobado.
     */
    const STATUS_APROBADO = 2;

    /**
     * Constante para el estado Activo.
     */
    const STATUS_ACTIVO = 3;

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
        'valor' => 'decimal:2',
        'dias_anticipacion' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'status' => 'integer',
        'permite_acumulacion' => 'boolean',
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
                // Para promoción de matrícula, solo se requiere que esté vigente
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
     * Calcula el valor del descuento aplicado a un monto.
     * Los descuentos se calculan sobre el valor a pagar, no sobre el valor pagado.
     * El resultado nunca puede ser negativo.
     *
     * @param float $monto Monto base sobre el cual aplicar el descuento (valor a pagar)
     * @return float Valor del descuento calculado (nunca negativo)
     */
    public function calcularDescuento(float $monto): float
    {
        if ($monto <= 0) {
            return 0;
        }

        $descuento = 0;

        if ($this->tipo === self::TIPO_PORCENTUAL) {
            $descuento = ($monto * $this->valor) / 100;
        } else {
            // Tipo valor fijo
            $descuento = min($this->valor, $monto); // No puede exceder el monto
        }

        // Asegurar que el descuento nunca sea negativo
        return max(0, $descuento);
    }

    /**
     * Scope para filtrar descuentos vigentes.
     * Solo retorna descuentos que están activos y cuya fecha está dentro del rango de vigencia.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|null $fecha Fecha a verificar. Si es null, usa la fecha actual
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
     * Scope para filtrar por tipo de descuento.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tipo Tipo de descuento (porcentual o valor_fijo)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope para filtrar por aplicación del descuento.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $aplicacion Aplicación del descuento (valor_total, matricula, cuota)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePorAplicacion($query, string $aplicacion)
    {
        return $query->where('aplicacion', $aplicacion);
    }

    /**
     * Scope para filtrar por tipo de activación.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tipoActivacion Tipo de activación
     * @return \Illuminate\Database\Eloquent\Builder
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
            'nombre',
            'codigo_descuento',
            'tipo',
            'valor',
            'aplicacion',
            'tipo_activacion',
            'fecha_inicio',
            'fecha_fin',
            'status',
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
            'listasPrecios',
            'productos',
            'sedes',
            'poblaciones',
            'descuentosAplicados',
            'listasPrecios.poblaciones',
            'productos.tipoProducto'
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

