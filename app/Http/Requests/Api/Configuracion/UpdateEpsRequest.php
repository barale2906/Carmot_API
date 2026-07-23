<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEpsRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $epsId = $this->route('ep') instanceof \App\Models\Configuracion\Eps
            ? $this->route('ep')->id
            : (int) $this->route('ep');

        return [
            'nombre'    => ['sometimes', 'string', 'max:255', Rule::unique('eps', 'nombre')->ignore($epsId)->whereNull('deleted_at')],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status'    => self::getStatusValidationRule(),
        ];
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'nombre.max'    => 'El nombre de la EPS no puede exceder los 255 caracteres.',
            'nombre.unique' => 'Ya existe una EPS con este nombre.',
            'direccion.max' => 'La dirección no puede exceder los 500 caracteres.',
        ], self::getStatusValidationMessages());
    }

    /**
     * Obtiene los atributos personalizados para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre'    => 'nombre de la EPS',
            'direccion' => 'dirección',
            'status'    => 'estado',
        ];
    }
}
