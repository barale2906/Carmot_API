<?php

namespace App\Http\Requests\Api\Academico;

use App\Http\Requests\Api\Academico\Concerns\ValidaGruposDelCiclo;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCicloRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation, ValidaGruposDelCiclo;

    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza los campos de fecha extrayendo solo la parte YYYY-MM-DD,
     * evitando desfases de zona horaria cuando el frontend envía ISO con sufijo UTC.
     */
    protected function prepareForValidation(): void
    {
        $campos = [];
        foreach (['fecha_inicio', 'fecha_fin'] as $campo) {
            if ($this->has($campo) && $this->$campo) {
                $campos[$campo] = substr((string) $this->$campo, 0, 10);
            }
        }
        if ($campos) {
            $this->merge($campos);
        }
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     * Con fecha_fin_automatica = false (fecha fin fija) la fecha_fin es obligatoria:
     * el contenido de los módulos se ajusta para terminar en esa fecha.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sede_id' => 'required|integer|exists:sedes,id',
            'curso_id' => 'required|integer|exists:cursos,id',
            'nombre' => 'required|string|max:255|unique:ciclos,nombre',
            'descripcion' => 'nullable|string|max:1000',
            'fecha_inicio' => 'required|date|after_or_equal:today',
            'fecha_fin' => [
                'nullable', 'date', 'after:fecha_inicio',
                Rule::requiredIf(fn () => $this->has('fecha_fin_automatica') && ! $this->boolean('fecha_fin_automatica')),
            ],
            'fecha_fin_automatica' => 'nullable|boolean',
            'grupos' => 'nullable|array',
            'grupos.*' => 'integer|exists:grupos,id',
            'status' => self::getStatusValidationRule(),
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'sede_id.required' => 'La sede es obligatoria.',
            'sede_id.integer' => 'La sede debe ser un número entero.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'curso_id.required' => 'El curso es obligatorio.',
            'curso_id.integer' => 'El curso debe ser un número entero.',
            'curso_id.exists' => 'El curso seleccionado no existe.',
            'nombre.required' => 'El nombre del ciclo es obligatorio.',
            'nombre.string' => 'El nombre del ciclo debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del ciclo no puede tener más de 255 caracteres.',
            'nombre.unique' => 'Ya existe un ciclo con este nombre.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede tener más de 1000 caracteres.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser igual o posterior a hoy.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria cuando la fecha fin es fija (no automática).',
            'fecha_fin_automatica.boolean' => 'El cálculo automático de fecha de fin debe ser verdadero o falso.',
            'grupos.array' => 'Los grupos deben ser un array.',
            'grupos.*.integer' => 'Cada grupo debe ser un número entero.',
            'grupos.*.exists' => 'Uno o más grupos seleccionados no existen.',
        ], self::getStatusValidationMessages());
    }

    /**
     * Obtiene los atributos personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sede_id' => 'sede',
            'curso_id' => 'curso',
            'descripcion' => 'descripción',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'fecha_fin_automatica' => 'cálculo automático de fecha de fin',
        ];
    }

    /**
     * Validaciones posteriores: los grupos deben ser de la sede y del curso del ciclo y tener horarios.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validarGruposDelCiclo($validator, $this->input('sede_id'), $this->input('curso_id')));
    }
}
