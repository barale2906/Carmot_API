<?php

namespace App\Http\Requests\Api\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Dashboard\KpiField;

/**
 * Request UpdateKpiFieldRelationRequest
 *
 * Valida los datos para actualizar una relación entre campos de KPI existente.
 * Incluye validación de campos, operaciones y condiciones.
 */
class UpdateKpiFieldRelationRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta petición.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en middleware
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'field_a_id' => 'sometimes|exists:kpi_fields,id',
            'field_b_id' => 'sometimes|exists:kpi_fields,id|different:field_a_id',
            'operation' => 'sometimes|in:divide,multiply,add,subtract,percentage',
            'field_a_model' => 'nullable|string',
            'field_b_model' => 'nullable|string',
            'field_a_conditions' => 'nullable|array',
            'field_a_conditions.*.field' => 'required_with:field_a_conditions|string|max:255',
            'field_a_conditions.*.operator' => 'required_with:field_a_conditions|in:=,!=,<,>,<=,>=,LIKE,NOT LIKE,IN,NOT IN',
            'field_a_conditions.*.value' => 'required_with:field_a_conditions|string|max:500',
            'field_b_conditions' => 'nullable|array',
            'field_b_conditions.*.field' => 'required_with:field_b_conditions|string|max:255',
            'field_b_conditions.*.operator' => 'required_with:field_b_conditions|in:=,!=,<,>,<=,>=,LIKE,NOT LIKE,IN,NOT IN',
            'field_b_conditions.*.value' => 'required_with:field_b_conditions|string|max:500',
            'multiplier' => 'nullable|numeric|min:0|max:999999.9999',
            'is_active' => 'boolean',
            'order' => 'integer|min:0',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'field_a_id.exists' => 'El primer campo debe existir.',
            'field_b_id.exists' => 'El segundo campo debe existir.',
            'field_b_id.different' => 'El segundo campo debe ser diferente al primero.',
            'operation.in' => 'La operación debe ser: divide, multiply, add, subtract o percentage.',
            'field_a_model.string' => 'El modelo del primer campo debe ser una cadena válida.',
            'field_b_model.string' => 'El modelo del segundo campo debe ser una cadena válida.',
            'field_a_conditions.array' => 'Las condiciones del primer campo deben ser un array.',
            'field_a_conditions.*.field.required_with' => 'El campo es obligatorio cuando se especifican condiciones.',
            'field_a_conditions.*.operator.required_with' => 'El operador es obligatorio cuando se especifican condiciones.',
            'field_a_conditions.*.operator.in' => 'El operador debe ser: =, !=, <, >, <=, >=, LIKE, NOT LIKE, IN, NOT IN.',
            'field_a_conditions.*.value.required_with' => 'El valor es obligatorio cuando se especifican condiciones.',
            'field_b_conditions.array' => 'Las condiciones del segundo campo deben ser un array.',
            'field_b_conditions.*.field.required_with' => 'El campo es obligatorio cuando se especifican condiciones.',
            'field_b_conditions.*.operator.required_with' => 'El operador es obligatorio cuando se especifican condiciones.',
            'field_b_conditions.*.operator.in' => 'El operador debe ser: =, !=, <, >, <=, >=, LIKE, NOT LIKE, IN, NOT IN.',
            'field_b_conditions.*.value.required_with' => 'El valor es obligatorio cuando se especifican condiciones.',
            'multiplier.numeric' => 'El multiplicador debe ser un número.',
            'multiplier.min' => 'El multiplicador debe ser mayor o igual a 0.',
            'multiplier.max' => 'El multiplicador no puede exceder 999999.9999.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
            'order.integer' => 'El orden debe ser un número entero.',
            'order.min' => 'El orden debe ser mayor o igual a 0.',
        ];
    }

    /**
     * Configura el validador después de que las reglas se hayan aplicado.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $kpiId = $this->route('kpi');
            $fieldAId = $this->field_a_id;
            $fieldBId = $this->field_b_id;

            // Validar que ambos campos pertenecen al mismo KPI (solo si se están actualizando)
            if ($fieldAId && $kpiId) {
                $fieldA = KpiField::where('id', $fieldAId)->where('kpi_id', $kpiId)->first();
                if (!$fieldA) {
                    $validator->errors()->add('field_a_id', 'El primer campo debe pertenecer al KPI especificado.');
                }
            }

            if ($fieldBId && $kpiId) {
                $fieldB = KpiField::where('id', $fieldBId)->where('kpi_id', $kpiId)->first();
                if (!$fieldB) {
                    $validator->errors()->add('field_b_id', 'El segundo campo debe pertenecer al KPI especificado.');
                }
            }

            // Validar que no existe una relación duplicada (excluyendo la actual)
            if ($fieldAId && $fieldBId && $kpiId) {
                $relationId = $this->route('relation');
                $existingRelation = \App\Models\Dashboard\KpiFieldRelation::where('kpi_id', $kpiId)
                    ->where('id', '!=', $relationId)
                    ->where(function ($query) use ($fieldAId, $fieldBId) {
                        $query->where(function ($q) use ($fieldAId, $fieldBId) {
                            $q->where('field_a_id', $fieldAId)->where('field_b_id', $fieldBId);
                        })->orWhere(function ($q) use ($fieldAId, $fieldBId) {
                            $q->where('field_a_id', $fieldBId)->where('field_b_id', $fieldAId);
                        });
                    })
                    ->exists();

                if ($existingRelation) {
                    $validator->errors()->add('field_b_id', 'Ya existe una relación entre estos dos campos.');
                }
            }
        });
    }
}
