<?php

namespace App\Models\Financiero\Lp;

use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo LpPrecioProducto
 *
 * Representa el precio de un producto dentro de una lista de precios específica.
 * Este modelo almacena tanto el precio de contado como los detalles de financiación
 * (precio total, matrícula, número de cuotas y valor de cuota calculado).
 * El valor de la cuota se calcula automáticamente al crear o actualizar el registro,
 * redondeando al 100 más cercano.
 *
 * @property int $id Identificador único del precio de producto
 * @property int $lista_precio_id ID de la lista de precios
 * @property int $producto_id ID del producto
 * @property float $precio_contado Precio para pago de contado
 * @property float|null $precio_total Precio total del producto (para financiación)
 * @property float $matricula Valor de la matrícula (obligatorio para cursos y módulos, puede ser 0)
 * @property int|null $numero_cuotas Número de cuotas
 * @property float|null $valor_cuota Valor calculado de cada cuota (redondeado al 100)
 * @property string|null $observaciones Observaciones adicionales
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Financiero\Lp\LpListaPrecio $listaPrecio Lista de precios a la que pertenece
 * @property-read \App\Models\Financiero\Lp\LpProducto $producto Producto al que pertenece
 */
class LpPrecioProducto extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'lp_precios_producto';

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
        'precio_contado' => 'decimal:2',
        'precio_total' => 'decimal:2',
        'matricula' => 'decimal:2',
        'numero_cuotas' => 'integer',
        'valor_cuota' => 'decimal:2',
    ];

    /**
     * Relación con LpListaPrecio (muchos a uno).
     * Un precio de producto pertenece a una lista de precios.
     *
     * @return BelongsTo
     */
    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(LpListaPrecio::class, 'lista_precio_id');
    }

    /**
     * Relación con LpProducto (muchos a uno).
     * Un precio de producto pertenece a un producto.
     *
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(LpProducto::class, 'producto_id');
    }

    /**
     * Calcula el valor de la cuota redondeado al 100 más cercano.
     * Este método solo se usa al crear/actualizar, NO al consultar.
     * La fórmula es: (precio_total - matricula) / numero_cuotas, redondeado al 100.
     *
     * @return float Valor de la cuota calculado y redondeado al 100 más cercano, o 0 si no aplica
     */
    public function calcularValorCuota(): float
    {
        if (!$this->producto || !$this->producto->esFinanciable() ||
            !$this->precio_total ||
            $this->matricula === null ||
            !$this->numero_cuotas || $this->numero_cuotas <= 0) {
            return 0;
        }

        $valorRestante = $this->precio_total - $this->matricula;
        $valorCuota = $valorRestante / $this->numero_cuotas;

        // Redondear al 100 más cercano
        return round($valorCuota / 100) * 100;
    }

    /**
     * Boot del modelo - calcula automáticamente el valor de la cuota al guardar.
     * Solo se ejecuta al crear o actualizar, no al consultar.
     * Este evento asegura que el valor_cuota siempre esté calculado correctamente
     * cuando se guarda un precio de producto financiable.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($precioProducto) {
            // Cargar la relación si no está cargada
            if (!$precioProducto->relationLoaded('producto')) {
                $precioProducto->load('producto');
            }

            if ($precioProducto->producto && $precioProducto->producto->esFinanciable()) {
                // Calcular y almacenar el valor de la cuota
                $precioProducto->valor_cuota = $precioProducto->calcularValorCuota();
            } else {
                // Para productos no financiables, limpiar valores de financiación
                $precioProducto->valor_cuota = null;
            }
        });
    }

    /**
     * Obtiene los campos permitidos para ordenamiento.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'precio_contado',
            'precio_total',
            'matricula',
            'numero_cuotas',
            'valor_cuota',
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
            'listaPrecio',
            'producto',
            'producto.tipoProducto'
        ];
    }

    /**
     * Obtiene las relaciones por defecto a cargar.
     *
     * @return array<string>
     */
    protected function getDefaultRelations(): array
    {
        return ['producto', 'listaPrecio'];
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
}
