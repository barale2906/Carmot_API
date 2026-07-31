<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBancoRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para crear un banco.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200', Rule::unique('bancos', 'nombre')->whereNull('deleted_at')],
            'codigo' => ['nullable', 'string', 'max:20', Rule::unique('bancos', 'codigo')->whereNull('deleted_at')],
            'status' => self::getStatusValidationRule(),
        ];
    }

    public function messages(): array
    {
        return array_merge([
            'nombre.required' => 'El nombre del banco es obligatorio.',
            'nombre.max'      => 'El nombre no puede exceder los 200 caracteres.',
            'nombre.unique'   => 'Ya existe un banco con este nombre.',
            'codigo.max'      => 'El código no puede exceder los 20 caracteres.',
            'codigo.unique'   => 'Ya existe un banco con este código.',
        ], self::getStatusValidationMessages());
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del banco',
            'codigo' => 'código',
            'status' => 'estado',
        ];
    }
}
