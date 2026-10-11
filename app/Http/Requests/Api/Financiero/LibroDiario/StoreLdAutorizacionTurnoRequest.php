<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Valida la autorización para que un cajero abra un nuevo turno hoy después de
 * haber cerrado caja. El cajero debe compartir al menos una sede con quien autoriza.
 */
class StoreLdAutorizacionTurnoRequest extends FormRequest
{
    /**
     * La autorización se resuelve con el middleware de permisos.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cajero_id' => ['required', 'integer', 'exists:users,id'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * El cajero debe trabajar en alguna sede de quien autoriza.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->has('cajero_id')) {
                return;
            }

            $sedesCajero = User::find($this->cajero_id)->idsSedesAccesibles();
            if (! array_intersect($sedesCajero, $this->user()->idsSedesAccesibles())) {
                $v->errors()->add('cajero_id', 'El cajero no pertenece a ninguna de sus sedes.');
            }
        });
    }

    /**
     * Mensajes de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cajero_id.required' => 'El cajero es obligatorio.',
            'cajero_id.exists' => 'El cajero seleccionado no existe.',
            'motivo.required' => 'El motivo de la autorización es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
