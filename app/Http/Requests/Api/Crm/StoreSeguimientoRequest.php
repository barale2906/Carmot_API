<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\Crm\Referido;
use App\Traits\HasProximaAccionValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeguimientoRequest extends FormRequest
{
    use HasProximaAccionValidation;

    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('crm_seguimientoCrear');
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'referido_id' => 'required|integer|exists:referidos,id',
            'seguidor_id' => 'required|integer|exists:users,id',
            'fecha' => 'required|date',
            'seguimiento' => 'required|string|max:65535',
            // Estado en que queda el lead tras la gestión; Matriculado solo lo asigna la conversión en matrícula
            'estado_referido' => ['nullable', 'integer', Rule::in(Referido::ESTADOS_ASIGNABLES)],
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
            'estado_referido.in' => 'El estado debe ser Creado, Interesado, Pendiente por matricular o Declinado. Matriculado se asigna al generar la matrícula.',
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
            'estado_referido' => 'estado del lead',
            ...$this->proximaAccionAttributes(),
        ];
    }
}
