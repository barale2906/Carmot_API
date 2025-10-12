<?php

namespace App\Http\Requests\Api\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class StoreSedeRequest extends FormRequest
{
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
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['required', 'string', 'max:500'],
            'telefono' => ['required', 'string', 'max:20'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:sedes,email'],
            'hora_inicio' => ['required', 'date_format:H:i:s'],
            'hora_fin' => ['required', 'date_format:H:i:s', 'after:hora_inicio'],
            'poblacion_id' => ['required', 'integer', 'exists:poblacions,id'],
            'areas' => ['sometimes', 'array'],
            'areas.*' => ['integer', 'exists:areas,id'],
            'horarios' => ['required', 'array', 'min:1'],
            'horarios.*.area_id' => ['required', 'integer', 'exists:areas,id'],
            'horarios.*.dia' => ['required', 'string', 'in:lunes,martes,miércoles,jueves,viernes,sábado,domingo'],
            'horarios.*.hora' => ['required', 'date_format:H:i:s'],
            'horarios.*.tipo' => ['sometimes', 'boolean'],
            'horarios.*.periodo' => ['sometimes', 'boolean'],
            'horarios.*.grupo_id' => ['sometimes', 'integer', 'nullable'],
            'horarios.*.grupo_nombre' => ['sometimes', 'string', 'max:255', 'nullable'],
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
            'nombre.required' => 'El nombre de la sede es obligatorio.',
            'nombre.max' => 'El nombre de la sede no puede exceder los 255 caracteres.',
            'direccion.required' => 'La dirección es obligatoria.',
            'direccion.max' => 'La dirección no puede exceder los 500 caracteres.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.max' => 'El teléfono no puede exceder los 20 caracteres.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado.',
            'email.max' => 'El email no puede exceder los 255 caracteres.',
            'hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM:SS.',
            'hora_fin.required' => 'La hora de fin es obligatoria.',
            'hora_fin.date_format' => 'La hora de fin debe tener el formato HH:MM:SS.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'poblacion_id.required' => 'La población es obligatoria.',
            'poblacion_id.exists' => 'La población seleccionada no existe.',
            'areas.array' => 'Las áreas deben ser un array.',
            'areas.*.integer' => 'Cada área debe ser un número entero.',
            'areas.*.exists' => 'Una o más áreas seleccionadas no existen.',
            'horarios.required' => 'Los horarios de atención son obligatorios.',
            'horarios.array' => 'Los horarios deben ser un array.',
            'horarios.min' => 'Debe proporcionar al menos un horario de atención.',
            'horarios.*.area_id.required' => 'El área es obligatoria para cada horario.',
            'horarios.*.area_id.exists' => 'Una o más áreas seleccionadas no existen.',
            'horarios.*.dia.required' => 'El día es obligatorio para cada horario.',
            'horarios.*.dia.in' => 'El día debe ser uno de: lunes, martes, miércoles, jueves, viernes, sábado, domingo.',
            'horarios.*.hora.required' => 'La hora es obligatoria para cada horario.',
            'horarios.*.hora.date_format' => 'La hora debe tener el formato HH:MM:SS.',
            'horarios.*.tipo.boolean' => 'El tipo debe ser verdadero o falso.',
            'horarios.*.periodo.boolean' => 'El período debe ser verdadero o falso.',
            'horarios.*.grupo_id.integer' => 'El ID del grupo debe ser un número entero.',
            'horarios.*.grupo_nombre.max' => 'El nombre del grupo no puede exceder los 255 caracteres.',
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
            'nombre' => 'nombre de la sede',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
            'email' => 'email',
            'hora_inicio' => 'hora de inicio',
            'hora_fin' => 'hora de fin',
            'poblacion_id' => 'población',
            'areas' => 'áreas',
            'horarios' => 'horarios de atención',
            'horarios.*.area_id' => 'área del horario',
            'horarios.*.dia' => 'día del horario',
            'horarios.*.hora' => 'hora del horario',
            'horarios.*.tipo' => 'tipo del horario',
            'horarios.*.periodo' => 'período del horario',
            'horarios.*.grupo_id' => 'ID del grupo',
            'horarios.*.grupo_nombre' => 'nombre del grupo',
        ];
    }
}
