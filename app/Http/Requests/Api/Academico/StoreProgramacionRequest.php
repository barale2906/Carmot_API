<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use App\Traits\HasJornadaStatus;
use App\Traits\HasJornadaStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreProgramacionRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation, HasJornadaStatus, HasJornadaStatusValidation;

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
     *
     * @var array|null grupos Array de objetos con información de grupos a asignar a la programación.
     * Cada objeto debe contener:
     * - grupo_id (integer, required): ID del grupo existente
     * - fecha_inicio_grupo (date, nullable): Fecha de inicio del grupo dentro de la programación
     * - fecha_fin_grupo (date, nullable): Fecha de fin del grupo dentro de la programación
     */
    public function rules(): array
    {
        return [
            'curso_id' => 'required|integer|exists:cursos,id',
            'sede_id' => 'required|integer|exists:sedes,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'fecha_inicio' => 'required|date|after_or_equal:today',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'registrados' => 'nullable|integer|min:0',
            'jornada' => self::getJornadaValidationRule(),
            // Array de objetos con información de grupos: [{"grupo_id": int, "fecha_inicio_grupo": "date|null", "fecha_fin_grupo": "date|null"}]
            'grupos' => 'nullable|array',
            'grupos.*.grupo_id' => 'required|integer|exists:grupos,id',
            'grupos.*.fecha_inicio_grupo' => 'nullable|date|after_or_equal:fecha_inicio|before_or_equal:fecha_fin',
            'grupos.*.fecha_fin_grupo' => 'nullable|date|after:grupos.*.fecha_inicio_grupo|before_or_equal:fecha_fin',
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
            'curso_id.required' => 'El curso es obligatorio.',
            'curso_id.integer' => 'El curso debe ser un número entero.',
            'curso_id.exists' => 'El curso seleccionado no existe.',
            'sede_id.required' => 'La sede es obligatoria.',
            'sede_id.integer' => 'La sede debe ser un número entero.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'nombre.required' => 'El nombre de la programación es obligatorio.',
            'nombre.string' => 'El nombre de la programación debe ser una cadena de texto.',
            'nombre.max' => 'El nombre de la programación no puede tener más de 255 caracteres.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede tener más de 1000 caracteres.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser igual o posterior a hoy.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'registrados.integer' => 'El número de registrados debe ser un número entero.',
            'registrados.min' => 'El número de registrados no puede ser negativo.',
            'grupos.array' => 'Los grupos deben ser un array.',
            'grupos.*.grupo_id.required' => 'El ID del grupo es obligatorio.',
            'grupos.*.grupo_id.integer' => 'El ID del grupo debe ser un número entero.',
            'grupos.*.grupo_id.exists' => 'Uno o más grupos seleccionados no existen.',
            'grupos.*.fecha_inicio_grupo.date' => 'La fecha de inicio del grupo debe ser una fecha válida.',
            'grupos.*.fecha_inicio_grupo.after_or_equal' => 'La fecha de inicio del grupo debe ser igual o posterior a la fecha de inicio de la programación.',
            'grupos.*.fecha_inicio_grupo.before_or_equal' => 'La fecha de inicio del grupo debe ser igual o anterior a la fecha de fin de la programación.',
            'grupos.*.fecha_fin_grupo.date' => 'La fecha de fin del grupo debe ser una fecha válida.',
            'grupos.*.fecha_fin_grupo.after' => 'La fecha de fin del grupo debe ser posterior a su fecha de inicio.',
            'grupos.*.fecha_fin_grupo.before_or_equal' => 'La fecha de fin del grupo debe ser igual o anterior a la fecha de fin de la programación.',
        ], array_merge(self::getStatusValidationMessages(), self::getJornadaValidationMessages()));
    }

    /**
     * Obtiene los atributos personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'curso_id' => 'curso',
            'sede_id' => 'sede',
            'descripcion' => 'descripción',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'fecha_inicio_grupo' => 'fecha de inicio del grupo',
            'fecha_fin_grupo' => 'fecha de fin del grupo',
        ];
    }
}
