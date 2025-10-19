<?php

namespace App\Http\Requests\Api\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request StoreKpiFieldRequest
 *
 * Valida los datos para crear un nuevo campo de KPI.
 * Incluye validación de operaciones y tipos de campo.
 */
class StoreKpiFieldRequest extends FormRequest
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
            'kpi_id' => 'required|exists:kpis,id',
            'field_name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'field_type' => 'required|in:numeric,string,date,boolean',
            'operation' => 'nullable|in:sum,count,avg,min,max,where,group_by',
            'operator' => 'nullable|string|max:10',
            'value' => 'nullable|string|max:500',
            'is_required' => 'boolean',
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
            'kpi_id.required' => 'El ID del KPI es obligatorio.',
            'kpi_id.exists' => 'El KPI especificado no existe.',
            'field_name.required' => 'El nombre del campo es obligatorio.',
            'field_name.max' => 'El nombre del campo no puede exceder 255 caracteres.',
            'display_name.required' => 'El nombre de visualización es obligatorio.',
            'display_name.max' => 'El nombre de visualización no puede exceder 255 caracteres.',
            'field_type.required' => 'El tipo de campo es obligatorio.',
            'field_type.in' => 'El tipo de campo debe ser: numeric, string, date o boolean.',
            'operation.in' => 'La operación debe ser: sum, count, avg, min, max, where o group_by.',
            'operator.max' => 'El operador no puede exceder 10 caracteres.',
            'value.max' => 'El valor no puede exceder 500 caracteres.',
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
            // Validar que si la operación es 'where', se especifique el operador
            if ($this->operation === 'where' && !$this->operator) {
                $validator->errors()->add('operator', 'El operador es obligatorio cuando la operación es "where".');
            }

            // Validar que si la operación es 'where', se especifique el valor
            if ($this->operation === 'where' && !$this->value) {
                $validator->errors()->add('value', 'El valor es obligatorio cuando la operación es "where".');
            }

            // Validar que el campo esté permitido para el modelo del KPI
            if ($this->kpi_id && $this->field_name) {
                $this->validateFieldIsAllowed($validator);
            }

            // Validar que la operación sea apropiada para el tipo de campo
            if ($this->field_type && $this->operation) {
                $this->validateOperationForFieldType($validator);
            }
        });
    }

    /**
     * Valida que el campo esté permitido para el modelo del KPI.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateFieldIsAllowed($validator): void
    {
        $kpi = \App\Models\Dashboard\Kpi::find($this->kpi_id);

        if (!$kpi || !$kpi->base_model) {
            return;
        }

        $kpiMetadataService = app(\App\Services\KpiMetadataService::class);
        $allowedFields = $kpiMetadataService->getModelFieldsByClass($kpi->base_model);

        if (!in_array($this->field_name, $allowedFields)) {
            $validator->errors()->add('field_name', "El campo '{$this->field_name}' no está permitido para este modelo.");
        }
    }

    /**
     * Valida que la operación sea apropiada para el tipo de campo.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateOperationForFieldType($validator): void
    {
        $allowedOperations = $this->getAllowedOperationsForFieldType($this->field_type);

        if (!in_array($this->operation, $allowedOperations)) {
            $validator->errors()->add('operation', "La operación '{$this->operation}' no es válida para campos de tipo '{$this->field_type}'.");
        }
    }

    /**
     * Obtiene las operaciones permitidas para un tipo de campo.
     *
     * @param string $fieldType
     * @return array<string>
     */
    private function getAllowedOperationsForFieldType(string $fieldType): array
    {
        return match ($fieldType) {
            'numeric' => ['sum', 'count', 'avg', 'min', 'max', 'where'],
            'string', 'text' => ['count', 'where'],
            'boolean' => ['count', 'where'],
            'date', 'datetime' => ['count', 'where'],
            default => ['count']
        };
    }
}
