<?php

namespace App\Http\Requests\Api\Academico;

use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Http\Requests\Api\Academico\Concerns\ValidaEstudiantesDelGrupo;
use Illuminate\Foundation\Http\FormRequest;

class StoreNotaMasivaRequest extends FormRequest
{
    use ValidaEstudiantesDelGrupo;

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
            'grupo_id' => 'required|integer|exists:grupos,id',
            'modulo_id' => 'required|integer|exists:modulos,id',
            'esquema_calificacion_id' => 'required|integer|exists:esquema_calificacions,id',
            'tipo_nota_esquema_id' => 'required|integer|exists:tipo_nota_esquemas,id',
            'notas' => 'required|array|min:1',
            'notas.*.estudiante_id' => 'required|integer|distinct|exists:users,id',
            'notas.*.nota' => 'required|numeric|min:0',
            'notas.*.observaciones' => 'nullable|string',
            'fecha_registro' => 'nullable|date',
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
            'grupo_id.required' => 'El grupo es obligatorio.',
            'grupo_id.exists' => 'El grupo seleccionado no existe.',
            'modulo_id.required' => 'El módulo es obligatorio.',
            'modulo_id.exists' => 'El módulo seleccionado no existe.',
            'esquema_calificacion_id.required' => 'El esquema de calificación es obligatorio.',
            'esquema_calificacion_id.exists' => 'El esquema seleccionado no existe.',
            'tipo_nota_esquema_id.required' => 'El tipo de nota es obligatorio.',
            'tipo_nota_esquema_id.exists' => 'El tipo de nota seleccionado no existe.',
            'notas.required' => 'Debe proporcionar al menos una nota.',
            'notas.min' => 'Debe proporcionar al menos una nota.',
            'notas.*.estudiante_id.required' => 'El estudiante es obligatorio.',
            'notas.*.estudiante_id.exists' => 'El estudiante seleccionado no existe.',
            'notas.*.estudiante_id.distinct' => 'Un estudiante aparece repetido en la lista.',
            'notas.*.nota.required' => 'La nota es obligatoria.',
            'notas.*.nota.numeric' => 'La nota debe ser un número.',
            'notas.*.nota.min' => 'La nota no puede ser menor a 0.',
            'fecha_registro.date' => 'La fecha de registro debe ser una fecha válida.',
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
            'grupo_id' => 'grupo',
            'modulo_id' => 'módulo',
            'esquema_calificacion_id' => 'esquema de calificación',
            'tipo_nota_esquema_id' => 'tipo de nota',
            'notas' => 'notas',
            'notas.*.estudiante_id' => 'estudiante',
            'notas.*.nota' => 'nota',
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
    }

    /**
     * El tipo de nota debe pertenecer al esquema, y el esquema aplicar al grupo y a su módulo.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $grupo   = Grupo::find($this->grupo_id);
            $esquema = EsquemaCalificacion::find($this->esquema_calificacion_id);

            if ((int) $grupo->modulo_id !== (int) $this->modulo_id) {
                $validator->errors()->add('modulo_id', 'El módulo no pertenece al grupo especificado.');
            }

            $aplicaAlGrupo = $esquema->grupo_id === null || (int) $esquema->grupo_id === (int) $this->grupo_id;
            if ((int) $esquema->modulo_id !== (int) $this->modulo_id || ! $aplicaAlGrupo) {
                $validator->errors()->add('esquema_calificacion_id', 'El esquema de calificación no aplica a este grupo y módulo.');
            }

            if (! $esquema->tiposNota()->whereKey($this->tipo_nota_esquema_id)->exists()) {
                $validator->errors()->add('tipo_nota_esquema_id', 'El tipo de nota no pertenece al esquema de calificación.');
            }

            $this->validarEstudiantesDelGrupo($validator, $grupo, array_column((array) $this->input('notas', []), 'estudiante_id'), 'notas');
        });
    }
}
