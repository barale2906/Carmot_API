<?php

namespace App\Http\Requests\Api\Academico;

use App\Http\Requests\Api\Academico\Concerns\ValidaProgramacionDeMatricula;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos de la vista previa de descuentos al matricular.
 */
class PrecalcularDescuentoMatriculaRequest extends FormRequest
{
    use ValidaProgramacionDeMatricula;

    /**
     * La autorización se resuelve por middleware de permisos en la ruta.
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
            'sede_id' => 'required|integer|exists:sedes,id',
            'curso_id' => 'required|integer|exists:cursos,id',
            'ciclo_id' => 'required|integer|exists:ciclos,id',
            'programacion_id' => 'nullable|integer|exists:programacions,id',
            'lp_precio_producto_id' => 'nullable|integer|exists:lp_precios_producto,id',
            'fecha_matricula' => 'nullable|date',
        ];
    }

    /**
     * Valida que la programación sea de la sede y el curso indicados.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validarProgramacionDeMatricula(
                $validator,
                $this->input('programacion_id'),
                $this->input('sede_id'),
                $this->input('curso_id')
            );
        });
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sede_id.required' => 'La sede es obligatoria.',
            'curso_id.required' => 'El curso es obligatorio.',
            'ciclo_id.required' => 'El ciclo es obligatorio.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'curso_id.exists' => 'El curso seleccionado no existe.',
            'ciclo_id.exists' => 'El ciclo seleccionado no existe.',
            'programacion_id.exists' => 'La programación seleccionada no existe.',
            'lp_precio_producto_id.exists' => 'El precio de lista seleccionado no existe.',
        ];
    }

    /**
     * Nombres de atributos en español.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sede_id' => 'sede',
            'curso_id' => 'curso',
            'ciclo_id' => 'ciclo',
            'programacion_id' => 'programación',
            'lp_precio_producto_id' => 'precio de lista',
            'fecha_matricula' => 'fecha de matrícula',
        ];
    }
}
