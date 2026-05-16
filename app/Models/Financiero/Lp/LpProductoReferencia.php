<?php

namespace App\Models\Financiero\Lp;

use App\Models\Academico\Curso;
use App\Models\Academico\Modulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo LpProductoReferencia
 *
 * Representa el vínculo entre un producto LP y su referencia académica
 * (curso o módulo). Actúa como la tabla pivot de la relación polimórfica
 * muchos a muchos entre LpProducto y Curso/Modulo.
 *
 * Un producto puede tener cero referencias (productos como diplomas, certificados,
 * registros de notas, etc.) o una o más referencias académicas.
 *
 * @property int $id Identificador único del vínculo
 * @property int $lp_producto_id ID del producto LP
 * @property int $referencia_id ID del curso o módulo
 * @property string $referencia_tipo Tipo de referencia: 'curso' o 'modulo'
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \App\Models\Financiero\Lp\LpProducto $producto Producto LP asociado
 * @property-read \App\Models\Academico\Curso|\App\Models\Academico\Modulo|null $referenciaModel Entidad académica asociada
 */
class LpProductoReferencia extends Model
{
    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'lp_producto_referencias';

    /**
     * Atributos no asignables en masa.
     * Se excluyen id y timestamps para que no puedan sobreescribirse accidentalmente.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Atributos que se castean a tipos nativos.
     * Los IDs polimórficos se castean explícitamente a integer para evitar
     * comparaciones incorrectas de tipo al filtrar colecciones.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'lp_producto_id' => 'integer',
        'referencia_id'  => 'integer',
    ];

    /**
     * Valor del campo referencia_tipo para cursos académicos.
     *
     * @var string
     */
    const TIPO_CURSO  = 'curso';

    /**
     * Valor del campo referencia_tipo para módulos académicos.
     *
     * @var string
     */
    const TIPO_MODULO = 'modulo';

    /**
     * Retorna todos los valores válidos para el campo referencia_tipo.
     * Se usa en las reglas de validación de los FormRequest.
     *
     * @return array<int, string>
     */
    public static function tiposValidos(): array
    {
        return [self::TIPO_CURSO, self::TIPO_MODULO];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación con LpProducto (muchos a uno).
     * Una referencia pertenece a un producto LP.
     *
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(LpProducto::class, 'lp_producto_id');
    }

    /**
     * Resuelve y retorna la entidad académica (Curso o Modulo) referenciada.
     * Se accede como $referencia->referenciaModel.
     *
     * Para eager loading eficiente, utiliza la relación específica
     * 'curso' o 'modulo' cargada previamente con loadReferenciaModel().
     *
     * @return \App\Models\Academico\Curso|\App\Models\Academico\Modulo|null
     */
    public function getReferenciaModelAttribute(): ?Model
    {
        // Si ya fue cargada vía eager loading manual, retornarla directamente
        if ($this->relationLoaded('curso')) {
            return $this->getRelation('curso');
        }

        if ($this->relationLoaded('modulo')) {
            return $this->getRelation('modulo');
        }

        // Fallback con consulta individual (evitar en loops; usar loadReferenciaModels())
        return match ($this->referencia_tipo) {
            self::TIPO_CURSO  => Curso::find($this->referencia_id),
            self::TIPO_MODULO => Modulo::find($this->referencia_id),
            default           => null,
        };
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope para filtrar solo referencias de tipo curso.
     * Uso: LpProductoReferencia::cursos()->get()
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCursos(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('referencia_tipo', self::TIPO_CURSO);
    }

    /**
     * Scope para filtrar solo referencias de tipo módulo.
     * Uso: LpProductoReferencia::modulos()->get()
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeModulos(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('referencia_tipo', self::TIPO_MODULO);
    }

    /**
     * Scope para filtrar referencias que pertenecen a un producto LP específico.
     * Uso: LpProductoReferencia::delProducto(5)->get()
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  int  $productoId  ID del producto LP
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeDelProducto(\Illuminate\Database\Eloquent\Builder $query, int $productoId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('lp_producto_id', $productoId);
    }
}
