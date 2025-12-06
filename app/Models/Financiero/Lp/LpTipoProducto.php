<?php

namespace App\Models\Financiero\Lp;

use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasGenericScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo LpTipoProducto
 *
 * Representa un tipo de producto en el sistema de listas de precios.
 * Los tipos de productos definen las categorías principales de productos que pueden
 * ser ofrecidos, como cursos, módulos específicos y productos complementarios.
 *
 * @property int $id Identificador único del tipo de producto
 * @property string $nombre Nombre del tipo de producto
 * @property string $codigo Código único del tipo (curso, modulo, complementario)
 * @property bool $es_financiable Indica si el producto puede ser financiado
 * @property string|null $descripcion Descripción del tipo de producto
 * @property int $status Estado del tipo de producto (0: inactivo, 1: activo)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financiero\Lp\LpProducto> $productos Productos asociados a este tipo
 */
class LpTipoProducto extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasGenericScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'lp_tipos_producto';

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
        'es_financiable' => 'boolean',
        'status' => 'integer',
    ];

    /**
     * Relación con LpProducto (uno a muchos).
     * Un tipo de producto puede tener muchos productos asociados.
     *
     * @return HasMany
     */
    public function productos(): HasMany
    {
        return $this->hasMany(LpProducto::class, 'tipo_producto_id');
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
            'es_financiable',
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
            'productos'
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
            'productos'
        ];
    }
}
