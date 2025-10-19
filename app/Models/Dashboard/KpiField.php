<?php

namespace App\Models\Dashboard;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo KpiField
 *
 * Representa un campo de configuración para un KPI específico.
 * Define cómo se debe calcular y filtrar el KPI usando campos del modelo base.
 *
 * @property int $id Identificador único del campo
 * @property int $kpi_id ID del KPI al que pertenece este campo
 * @property string $field_name Nombre del campo en la base de datos (ej. "total_amount", "status")
 * @property string $display_name Nombre amigable para mostrar al usuario (ej. "Monto Total", "Estado")
 * @property string $field_type Tipo de dato del campo (numeric, string, date, boolean)
 * @property string|null $operation Operación a realizar (sum, count, avg, min, max, where, group_by)
 * @property string|null $operator Operador para condiciones where (=, >, <, LIKE, IN)
 * @property string|null $value Valor a comparar si operation es where
 * @property bool $is_required Indica si este campo es obligatorio para el cálculo
 * @property int $order Orden de presentación de los campos
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Dashboard\Kpi $kpi KPI al que pertenece este campo
 */
class KpiField extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Los atributos que pueden ser asignados masivamente.
     *
     * @var array<string>
     */
    protected $fillable = [
        'kpi_id', 'field_name', 'display_name', 'field_type',
        'operation', 'operator', 'value', 'is_required', 'order'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_required' => 'boolean',
    ];

    /**
     * Relación con Kpi (muchos a uno).
     * Un campo pertenece a un KPI.
     *
     * @return BelongsTo
     */
    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }

    /**
     * Relación con KpiFieldRelation como campo A (uno a muchos).
     * Un campo puede ser el primer campo en múltiples relaciones.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function relationsAsFieldA(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KpiFieldRelation::class, 'field_a_id');
    }

    /**
     * Relación con KpiFieldRelation como campo B (uno a muchos).
     * Un campo puede ser el segundo campo en múltiples relaciones.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function relationsAsFieldB(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KpiFieldRelation::class, 'field_b_id');
    }

    /**
     * Obtiene todas las relaciones donde este campo participa.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function allRelations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KpiFieldRelation::class, 'field_a_id')
            ->orWhere('field_b_id', $this->id);
    }
}
