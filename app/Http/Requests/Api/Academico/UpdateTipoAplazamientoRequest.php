<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTipoAplazamientoRequest extends FormRequest
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
        $tipoId = $this->route('tipo_aplazamiento')?->id;

        return [
            'nombre'      => ['sometimes', 'string', 'max:255', Rule::unique('tipo_aplazamientos', 'nombre')->ignore($tipoId)],
            'descripcion' => 'sometimes|nullable|string|max:1000',
            'status'      => self::getStatusValidationRule(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(self::getStatusValidationMessages(), [
            'nombre.unique' => 'Ya existe un tipo de aplazamiento con ese nombre.',
            'nombre.max'    => 'El nombre no puede superar los 255 caracteres.',
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
