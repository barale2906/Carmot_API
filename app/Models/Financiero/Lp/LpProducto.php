<?php

namespace App\Models\Financiero\Lp;

use App\Models\Academico\Curso;
use App\Models\Academico\Modulo;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo LpProducto
 *
 * Representa un producto en el catálogo general de productos del sistema de listas de precios.
 * Un producto puede ser un curso completo, un módulo específico o un producto complementario.
 * Los productos pueden estar asociados a cursos o módulos existentes mediante relaciones polimórficas.
 *
 * @property int $id Identificador único del producto
 * @property int $tipo_producto_id ID del tipo de producto
 * @property string $nombre Nombre del producto
 * @property string|null $codigo Código único del producto
 * @property string|null $descripcion Descripción del producto
 * @property int|null $referencia_id ID del curso o módulo relacionado (si aplica)
 * @property string|null $referencia_tipo Tipo de referencia (curso, modulo)
 * @property int $status Estado del producto (0: inactivo, 1: activo)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Financiero\Lp\LpTipoProducto $tipoProducto Tipo de producto al que pertenece
 * @property-read \App\Models\Academico\Curso|\App\Models\Academico\Modulo|null $referencia Referencia polimórfica al curso o módulo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpPrecioProducto> $precios Precios del producto en diferentes listas
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpListaPrecio> $listasPrecios Listas de precios donde aparece el producto
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Descuento> $descuentos Descuentos asociados a este producto
 */
class LpProducto extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'lp_productos';

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
        'status' => 'integer',
    ];

    /**
     * Relación con LpTipoProducto (muchos a uno).
     * Un producto pertenece a un tipo de producto.
     *
     * @return BelongsTo
     */
    public function tipoProducto(): BelongsTo
    {
        return $this->belongsTo(LpTipoProducto::class, 'tipo_producto_id');
    }

    /**
     * Relación polimórfica con Curso o Modulo (muchos a uno).
     * Un producto puede referenciar a un curso o módulo existente.
     *
     * @return MorphTo
     */
    public function referencia(): MorphTo
    {
        return $this->morphTo('referencia', 'referencia_tipo', 'referencia_id');
    }

    /**
     * Relación con LpPrecioProducto (uno a muchos).
     * Un producto puede tener múltiples precios en diferentes listas de precios.
     *
     * @return HasMany
     */
    public function precios(): HasMany
    {
        return $this->hasMany(LpPrecioProducto::class, 'producto_id');
    }

    /**
     * Relación con LpListaPrecio (muchos a muchos).
     * Un producto puede estar en múltiples listas de precios.
     * La relación se establece a través de la tabla pivot lp_precios_producto.
     *
     * @return BelongsToMany
     */
    public function listasPrecios(): BelongsToMany
    {
        return $this->belongsToMany(LpListaPrecio::class, 'lp_precios_producto', 'producto_id', 'lista_precio_id')
                    ->withPivot(['precio_contado', 'precio_total', 'matricula', 'numero_cuotas', 'valor_cuota', 'observaciones'])
                    ->withTimestamps();
    }

    /**
     * Relación con Descuentos (muchos a muchos).
     * Un producto puede tener múltiples descuentos asociados.
     * La relación se establece a través de la tabla pivot descuento_producto.
     *
     * @return BelongsToMany
     */
    public function descuentos(): BelongsToMany
    {
        return $this->belongsToMany(
            Descuento::class,
            'descuento_producto',
            'producto_id',
            'descuento_id'
        )->withTimestamps();
    }

    /**
     * Relación con RecibosPago (muchos a muchos).
     * Un producto puede estar en múltiples recibos de pago.
     * La relación se establece a través de la tabla pivot recibo_pago_producto.
     *
     * @return BelongsToMany
     */
    public function recibosPago(): BelongsToMany
    {
        return $this->belongsToMany(
            ReciboPago::class,
            'recibo_pago_producto',
            'producto_id',
            'recibo_pago_id'
        )->withPivot(['cantidad', 'precio_unitario', 'subtotal'])
         ->withTimestamps();
    }

    /**
     * Verifica si el producto es financiable.
     * Un producto es financiable si su tipo de producto tiene la propiedad es_financiable en true.
     *
     * @return bool True si el producto es financiable, false en caso contrario
     */
    public function esFinanciable(): bool
    {
        return $this->tipoProducto->es_financiable ?? false;
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
            'codigo',
            'tipo_producto_id',
            'referencia_tipo',
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
            'tipoProducto',
            'referencia',
            'precios',
            'listasPrecios',
            'descuentos',
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
        return ['tipoProducto'];
    }

    /**
     * Obtiene las relaciones que pueden ser contadas.
     *
     * @return array<string>
     */
    protected function getCountableRelations(): array
    {
        return [
            'precios',
            'listasPrecios',
            'descuentos'
        ];
    }
}
