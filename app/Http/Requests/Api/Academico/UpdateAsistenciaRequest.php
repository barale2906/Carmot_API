<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAsistenciaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estado' => ['sometimes', Rule::in(['presente', 'ausente', 'justificado', 'tardanza'])],
            'hora_registro' => ['nullable', 'date_format:H:i:s'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.in' => 'El estado debe ser: presente, ausente, justificado o tardanza.',
            'hora_registro.date_format' => 'El formato de hora debe ser H:i:s (ejemplo: 14:30:00).',
            'observaciones.string' => 'Las observaciones deben ser texto.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 5000 caracteres.',
        ];
    }
}
