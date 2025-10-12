<?php

namespace App\Http\Requests\Api\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSedeRequest extends FormRequest
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
        $sedeId = $this->route('sede')->id;

        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'direccion' => ['sometimes', 'string', 'max:500'],
            'telefono' => ['sometimes', 'string', 'max:20'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('sedes', 'email')->ignore($sedeId)
            ],
            'hora_inicio' => ['sometimes', 'date_format:H:i:s'],
            'hora_fin' => ['sometimes', 'date_format:H:i:s', 'after:hora_inicio'],
            'poblacion_id' => ['sometimes', 'integer', 'exists:poblacions,id'],
            'areas' => ['sometimes', 'array'],
            'areas.*' => ['integer', 'exists:areas,id'],
            'horarios' => ['sometimes', 'array'],
            'horarios.*.area_id' => ['required_with:horarios', 'integer', 'exists:areas,id'],
            'horarios.*.dia' => ['required_with:horarios', 'string', 'in:lunes,martes,miércoles,jueves,viernes,sábado,domingo'],
            'horarios.*.hora' => ['required_with:horarios', 'date_format:H:i:s'],
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
            'nombre.max' => 'El nombre de la sede no puede exceder los 255 caracteres.',
            'direccion.max' => 'La dirección no puede exceder los 500 caracteres.',
            'telefono.max' => 'El teléfono no puede exceder los 20 caracteres.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado.',
            'email.max' => 'El email no puede exceder los 255 caracteres.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM:SS.',
            'hora_fin.date_format' => 'La hora de fin debe tener el formato HH:MM:SS.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'poblacion_id.exists' => 'La población seleccionada no existe.',
            'areas.array' => 'Las áreas deben ser un array.',
            'areas.*.integer' => 'Cada área debe ser un número entero.',
            'areas.*.exists' => 'Una o más áreas seleccionadas no existen.',
            'horarios.array' => 'Los horarios deben ser un array.',
            'horarios.*.area_id.required_with' => 'El área es obligatoria cuando se proporcionan horarios.',
            'horarios.*.area_id.exists' => 'Una o más áreas seleccionadas no existen.',
            'horarios.*.dia.required_with' => 'El día es obligatorio cuando se proporcionan horarios.',
            'horarios.*.dia.in' => 'El día debe ser uno de: lunes, martes, miércoles, jueves, viernes, sábado, domingo.',
            'horarios.*.hora.required_with' => 'La hora es obligatoria cuando se proporcionan horarios.',
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
