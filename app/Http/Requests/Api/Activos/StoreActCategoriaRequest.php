<?php

namespace App\Http\Requests\Api\Activos;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActCategoriaRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre'      => ['required', 'string', 'max:120', Rule::unique('act_categorias', 'nombre')->whereNull('deleted_at')],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'status'      => self::getStatusValidationRule(),
        ];
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.unique'   => 'Ya existe una categoría con este nombre.',
        ] + self::getStatusValidationMessages();
    }
}
