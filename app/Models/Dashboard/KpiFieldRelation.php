<?php

namespace App\Models\Dashboard;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo KpiFieldRelation
 *
 * Representa una relación matemática entre dos campos de un KPI.
 * Permite realizar operaciones como porcentajes, sumas, restas, etc.
 *
 * @property int $id Identificador único de la relación
 * @property int $kpi_id ID del KPI al que pertenece esta relación
 * @property int $field_a_id ID del primer campo
 * @property int $field_b_id ID del segundo campo
 * @property string $operation Operación matemática (divide, multiply, add, subtract, percentage)
 * @property string|null $field_a_model Modelo del primer campo si es diferente al modelo base
 * @property string|null $field_b_model Modelo del segundo campo si es diferente al modelo base
 * @property array|null $field_a_conditions Condiciones adicionales para el primer campo
 * @property array|null $field_b_conditions Condiciones adicionales para el segundo campo
 * @property float $multiplier Multiplicador para el resultado final
 * @property bool $is_active Indica si la relación está activa
 * @property int $order Orden de procesamiento
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\Dashboard\Kpi $kpi KPI al que pertenece esta relación
 * @property-read \App\Models\Dashboard\KpiField $fieldA Primer campo de la relación
 * @property-read \App\Models\Dashboard\KpiField $fieldB Segundo campo de la relación
 */
class KpiFieldRelation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Los atributos que pueden ser asignados masivamente.
     *
     * @var array<string>
     */
    protected $fillable = [
        'kpi_id', 'field_a_id', 'field_b_id', 'operation',
        'field_a_model', 'field_b_model', 'field_a_conditions',
        'field_b_conditions', 'multiplier', 'is_active', 'order'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'field_a_conditions' => 'array',
        'field_b_conditions' => 'array',
        'multiplier' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con Kpi (muchos a uno).
     * Una relación pertenece a un KPI.
     *
     * @return BelongsTo
     */
    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }

    /**
     * Relación con KpiField - Campo A (muchos a uno).
     * El primer campo de la relación.
     *
     * @return BelongsTo
     */
    public function fieldA(): BelongsTo
    {
        return $this->belongsTo(KpiField::class, 'field_a_id');
    }

    /**
     * Relación con KpiField - Campo B (muchos a uno).
     * El segundo campo de la relación.
     *
     * @return BelongsTo
     */
    public function fieldB(): BelongsTo
    {
        return $this->belongsTo(KpiField::class, 'field_b_id');
    }

    /**
     * Obtiene el modelo del primer campo.
     *
     * @return string
     */
    public function getFieldAModel(): string
    {
        return $this->field_a_model ?? $this->kpi->base_model;
    }

    /**
     * Obtiene el modelo del segundo campo.
     *
     * @return string
     */
    public function getFieldBModel(): string
    {
        return $this->field_b_model ?? $this->kpi->base_model;
    }

    /**
     * Verifica si la relación es válida.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->is_active &&
               $this->fieldA &&
               $this->fieldB &&
               $this->fieldA->kpi_id === $this->kpi_id &&
               $this->fieldB->kpi_id === $this->kpi_id;
    }

    /**
     * Obtiene las operaciones disponibles.
     *
     * @return array<string>
     */
    public static function getAvailableOperations(): array
    {
        return [
            'divide' => 'División (A / B)',
            'multiply' => 'Multiplicación (A * B)',
            'add' => 'Suma (A + B)',
            'subtract' => 'Resta (A - B)',
            'percentage' => 'Porcentaje ((A / B) * 100)',
        ];
    }
}
