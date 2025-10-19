<?php

namespace App\Http\Requests\Api\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\KpiMetadataService;

/**
 * Request UpdateKpiRequest
 *
 * Valida los datos para actualizar un KPI existente.
 * Incluye validación de modelo base y campos de configuración.
 */
class UpdateKpiRequest extends FormRequest
{
    protected $kpiMetadataService;

    public function __construct(KpiMetadataService $kpiMetadataService)
    {
        parent::__construct();
        $this->kpiMetadataService = $kpiMetadataService;
    }

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
        $kpiId = $this->route('kpi') ?? $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:255|unique:kpis,code,' . $kpiId,
            'description' => 'nullable|string|max:1000',
            'unit' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'calculation_type' => 'sometimes|in:predefined,custom_fields,sql_query',
            'base_model' => 'nullable|string',
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
            'name.string' => 'El nombre del KPI debe ser una cadena válida.',
            'name.max' => 'El nombre del KPI no puede exceder 255 caracteres.',
            'code.string' => 'El código del KPI debe ser una cadena válida.',
            'code.unique' => 'El código del KPI ya existe.',
            'code.max' => 'El código del KPI no puede exceder 255 caracteres.',
            'description.max' => 'La descripción no puede exceder 1000 caracteres.',
            'unit.max' => 'La unidad no puede exceder 50 caracteres.',
            'calculation_type.in' => 'El tipo de cálculo debe ser: predefined, custom_fields o sql_query.',
            'base_model.string' => 'El modelo base debe ser una cadena válida.',
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
            // Validar que el modelo base existe si se especifica
            if ($this->base_model && !$this->kpiMetadataService->isModelAllowed($this->base_model)) {
                $validator->errors()->add('base_model', 'El modelo especificado no está permitido para KPIs.');
            }
        });
    }
}
