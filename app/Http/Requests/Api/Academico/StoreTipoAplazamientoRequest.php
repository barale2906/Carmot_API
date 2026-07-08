<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreTipoAplazamientoRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:255|unique:tipo_aplazamientos,nombre',
            'descripcion' => 'nullable|string|max:1000',
            'status'      => self::getStatusValidationRule(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(self::getStatusValidationMessages(), [
            'nombre.required' => 'El nombre del tipo de aplazamiento es obligatorio.',
            'nombre.unique'   => 'Ya existe un tipo de aplazamiento con ese nombre.',
            'nombre.max'      => 'El nombre no puede superar los 255 caracteres.',
            'descripcion.max' => 'La descripción no puede superar los 1000 caracteres.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nombre'      => 'nombre',
            'descripcion' => 'descripción',
            'status'      => 'estado',
        ];
    }
}
