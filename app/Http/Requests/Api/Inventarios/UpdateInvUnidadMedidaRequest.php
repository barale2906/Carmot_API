<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para actualizar una unidad de medida.
 */
class UpdateInvUnidadMedidaRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unidadId = $this->route('unidad_medida');

        return [
            'nombre'      => [
                'required',
                'string',
                'max:100',
                Rule::unique('inv_unidades_medida', 'nombre')
                    ->ignore($unidadId)
                    ->whereNull('deleted_at'),
            ],
            'abreviatura' => ['nullable', 'string', 'max:20'],
            'status'      => self::getStatusValidationRule(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'nombre.required' => 'El nombre de la unidad de medida es obligatorio.',
            'nombre.max'      => 'El nombre no puede superar 100 caracteres.',
            'nombre.unique'   => 'Ya existe otra unidad de medida con ese nombre.',
        ], self::getStatusValidationMessages());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre'      => 'nombre',
            'abreviatura' => 'abreviatura',
            'status'      => 'estado',
        ];
    }
}
