<?php

namespace App\Http\Requests\Api\Academico;

use App\Models\Academico\Asistencia;
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
            'estado' => ['sometimes', Rule::in(Asistencia::ESTADOS)],
            'justificada' => ['sometimes', 'boolean'],
            'justificacion' => ['nullable', 'required_if:justificada,true', 'string', 'max:500'],
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
            'estado.in' => 'El estado debe ser: presente, ausente o tardanza (la justificación se marca aparte).',
            'justificada.boolean' => 'El campo justificada debe ser verdadero o falso.',
            'justificacion.required_if' => 'Indique el motivo de la justificación.',
            'justificacion.max' => 'El motivo de la justificación no puede exceder los 500 caracteres.',
            'hora_registro.date_format' => 'El formato de hora debe ser H:i:s (ejemplo: 14:30:00).',
            'observaciones.string' => 'Las observaciones deben ser texto.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 5000 caracteres.',
        ];
    }

    /**
     * Solo se justifica una ausencia o una tardanza: se valida con el estado resultante
     * (el enviado o el que ya tiene la asistencia).
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $actual      = Asistencia::find($this->route('asistencia'));
            $estado      = $this->input('estado', $actual?->estado);
            $justificada = $this->has('justificada') ? $this->boolean('justificada') : (bool) $actual?->justificada;

            if ($estado === 'presente' && $justificada) {
                $validator->errors()->add('justificada', 'Solo se justifica una ausencia o una tardanza.');
            }
        });
    }

    /**
     * Datos a guardar: al quitar la justificación se borra también su motivo.
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $datos = $this->validated();

        if (array_key_exists('justificada', $datos) && ! $datos['justificada']) {
            $datos['justificacion'] = null;
        }

        return $datos;
    }
}
