<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para actualizar un almacén.
 */
class UpdateInvAlmacenRequest extends FormRequest
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
        $almacenId = $this->route('almacen');

        return [
            'nombre'      => [
                'required',
                'string',
                'max:150',
                Rule::unique('inv_almacenes', 'nombre')
                    ->ignore($almacenId)
                    ->whereNull('deleted_at'),
            ],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'sede_id'     => ['required', 'integer', 'exists:sedes,id'],
            'status'      => self::getStatusValidationRule(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'nombre.required'  => 'El nombre del almacén es obligatorio.',
            'nombre.max'       => 'El nombre no puede superar 150 caracteres.',
            'nombre.unique'    => 'Ya existe otro almacén con ese nombre.',
            'sede_id.required' => 'La sede es obligatoria.',
            'sede_id.exists'   => 'La sede seleccionada no existe.',
        ], self::getStatusValidationMessages());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre'      => 'nombre',
            'descripcion' => 'descripción',
            'sede_id'     => 'sede',
            'status'      => 'estado',
        ];
    }
}
