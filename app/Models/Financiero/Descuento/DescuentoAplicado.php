<?php

namespace App\Models\Financiero\Descuento;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpProducto;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo DescuentoAplicado
 *
 * Representa el historial de descuentos aplicados a diferentes conceptos de pago.
 * Este modelo permite auditar y rastrear todos los descuentos que se han aplicado
 * en el sistema, incluyendo información sobre valores originales, descuentos aplicados
 * y valores finales.
 *
 * @property int $id Identificador único del registro de descuento aplicado
 * @property int $descuento_id ID del descuento aplicado
 * @property string $concepto_tipo Tipo de concepto: matricula, cuota, pago_contado, etc.
 * @property int $concepto_id ID del concepto de pago
 * @property float $valor_original Valor original antes del descuento
 * @property float $valor_descuento Valor del descuento aplicado
 * @property float $valor_final Valor final después del descuento
 * @property int|null $producto_id ID del producto relacionado
 * @property int|null $lista_precio_id ID de la lista de precios relacionada
 * @property int|null $sede_id ID de la sede donde se aplicó
 * @property string|null $observaciones Observaciones adicionales
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 *
 * @property-read \App\Models\Financiero\Descuento\Descuento $descuento Descuento que se aplicó
 * @property-read \App\Models\Financiero\Lp\LpProducto|null $producto Producto relacionado
 * @property-read \App\Models\Financiero\Lp\LpListaPrecio|null $listaPrecio Lista de precios relacionada
 * @property-read \App\Models\Configuracion\Sede|null $sede Sede donde se aplicó
 */
class DescuentoAplicado extends Model
{
    use HasFactory, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'descuento_aplicado';

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
        'valor_original' => 'decimal:2',
        'valor_descuento' => 'decimal:2',
        'valor_final' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con Descuento (muchos a uno).
     * Un registro de descuento aplicado pertenece a un descuento.
     *
     * @return BelongsTo
     */
    public function descuento(): BelongsTo
    {
        return $this->belongsTo(Descuento::class, 'descuento_id');
    }

    /**
     * Relación con LpProducto (muchos a uno).
     * Un registro de descuento aplicado puede estar relacionado con un producto.
     *
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(LpProducto::class, 'producto_id');
    }

    /**
     * Relación con LpListaPrecio (muchos a uno).
     * Un registro de descuento aplicado puede estar relacionado con una lista de precios.
     *
     * @return BelongsTo
     */
    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(LpListaPrecio::class, 'lista_precio_id');
    }

    /**
     * Relación con Sede (muchos a uno).
     * Un registro de descuento aplicado puede estar relacionado con una sede.
     *
     * @return BelongsTo
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'descuento_id',
            'concepto_tipo',
            'concepto_id',
            'valor_original',
            'valor_descuento',
            'valor_final',
            'producto_id',
            'lista_precio_id',
            'sede_id',
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
            'descuento',
            'producto',
            'listaPrecio',
            'sede',
            'producto.tipoProducto',
            'descuento.listasPrecios'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array<string>
     */
    protected function getDefaultRelations(): array
    {
        return ['descuento'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array<string>
     */
    protected function getCountableRelations(): array
    {
        return [];
    }

    /**
     * Crea una nueva instancia de factory para el modelo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return \Database\Factories\Financiero\Descuento\DescuentoAplicadoFactory::new();
    }
}

