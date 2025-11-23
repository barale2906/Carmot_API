<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotaEstudianteRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estudiante_id' => 'required|integer|exists:users,id',
            'grupo_id' => 'required|integer|exists:grupos,id',
            'modulo_id' => 'required|integer|exists:modulos,id',
            'esquema_calificacion_id' => 'required|integer|exists:esquema_calificacions,id',
            'tipo_nota_esquema_id' => 'required|integer|exists:tipo_nota_esquemas,id',
            'nota' => 'required|numeric|min:0',
            'fecha_registro' => 'nullable|date',
            'observaciones' => 'nullable|string',
            'status' => 'sometimes|integer|in:0,1,2',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estudiante_id.required' => 'El estudiante es obligatorio.',
            'estudiante_id.exists' => 'El estudiante seleccionado no existe.',
            'grupo_id.required' => 'El grupo es obligatorio.',
            'grupo_id.exists' => 'El grupo seleccionado no existe.',
            'modulo_id.required' => 'El módulo es obligatorio.',
            'modulo_id.exists' => 'El módulo seleccionado no existe.',
            'esquema_calificacion_id.required' => 'El esquema de calificación es obligatorio.',
            'esquema_calificacion_id.exists' => 'El esquema seleccionado no existe.',
            'tipo_nota_esquema_id.required' => 'El tipo de nota es obligatorio.',
            'tipo_nota_esquema_id.exists' => 'El tipo de nota seleccionado no existe.',
            'nota.required' => 'La nota es obligatoria.',
            'nota.numeric' => 'La nota debe ser un número.',
            'nota.min' => 'La nota no puede ser menor a 0.',
            'fecha_registro.date' => 'La fecha de registro debe ser una fecha válida.',
            'status.in' => 'El estado debe ser 0 (pendiente), 1 (registrada) o 2 (cerrada).',
        ];
    }

    /**
     * Obtiene los atributos personalizados.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'estudiante_id' => 'estudiante',
            'grupo_id' => 'grupo',
            'modulo_id' => 'módulo',
            'esquema_calificacion_id' => 'esquema de calificación',
            'tipo_nota_esquema_id' => 'tipo de nota',
        ];
    }

    /**
     * Prepara los datos para la validación.
     */
    protected function prepareForValidation(): void
    {
        // Establecer fecha_registro por defecto si no se proporciona
        if (!$this->has('fecha_registro')) {
            $this->merge(['fecha_registro' => now()->toDateString()]);
        }

        // Establecer status por defecto
        if (!$this->has('status')) {
            $this->merge(['status' => 1]); // Registrada por defecto
        }
    }
}
