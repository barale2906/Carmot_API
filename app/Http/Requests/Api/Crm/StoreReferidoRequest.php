<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\Crm\Referido;
use App\Models\User;
use App\Traits\HasProximaAccionValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferidoRequest extends FormRequest
{
    use HasProximaAccionValidation;

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
        return [
            'curso_id' => ['required', 'integer', 'exists:cursos,id'],
            // Asesor que gestiona el lead hasta matricularlo o hasta que lo reasignen (N07)
            'gestor_id' => ['required', 'integer', 'exists:users,id', User::reglaPersonal('El asesor debe ser alguien del personal, no un estudiante.')],
            'nombre' => ['nullable', 'string', 'max:255'],
            'celular' => ['required', 'string', 'unique:referidos,celular', 'max:20'],
            'ciudad' => ['required', 'string', 'max:100'],
            // Un lead no nace matriculado: ese estado lo asigna la conversión en matrícula
            'status' => ['sometimes', 'integer', Rule::in(Referido::ESTADOS_ASIGNABLES)],
            // Primer seguimiento de la bitácora, registrado con el lead; exige el permiso de seguimientos
            'seguimiento' => [
                'nullable',
                'string',
                'max:65535',
                Rule::prohibitedIf(fn () => ! $this->user()->can('crm_seguimientoCrear')),
            ],
            // Acción que queda programada en la agenda para continuar con el lead
            ...$this->proximaAccionRules(),
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
            'curso_id.required' => 'El curso es obligatorio.',
            'curso_id.exists' => 'El curso seleccionado no existe.',
            'gestor_id.required' => 'El gestor es obligatorio.',
            'gestor_id.exists' => 'El gestor seleccionado no existe.',
            'celular.required' => 'El número de celular es obligatorio.',
            'celular.unique' => 'Este número de celular ya está registrado.',
            'celular.max' => 'El número de celular no puede exceder los 20 caracteres.',
            'ciudad.required' => 'La ciudad es obligatoria.',
            'ciudad.max' => 'El nombre de la ciudad no puede exceder los 100 caracteres.',
            'status.in' => 'El estado debe ser uno de: 0 (Creado), 1 (Interesado), 2 (Pendiente), 4 (Declinado). Matriculado se asigna al generar la matrícula.',
            'seguimiento.prohibited' => 'No tiene permiso para registrar seguimientos.',
            ...$this->proximaAccionMessages(),
        ];
    }

    /**
     * Obtiene los atributos personalizados para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'curso_id' => 'curso',
            'gestor_id' => 'gestor',
            'celular' => 'número de celular',
            'ciudad' => 'ciudad',
            'status' => 'estado',
            'seguimiento' => 'seguimiento',
            ...$this->proximaAccionAttributes(),
        ];
    }
}
