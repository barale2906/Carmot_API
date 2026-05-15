<?php

namespace App\Http\Requests\Api\Academico;

use App\Models\Academico\Matricula;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMatriculaRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Opciones de estado: extiende el trait para incluir "Anulado".
     */
    public static function getActiveStatusOptions(): array
    {
        return [
            0 => 'Inactivo',
            1 => 'Activo',
            2 => 'Anulado',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $matricula    = $this->route('matricula');
        $fechaMatricula = $this->input('fecha_matricula', $matricula->fecha_matricula ?? null);

        return [
            // ----------------------------------------------------------------
            // Datos académicos / administrativos
            // ----------------------------------------------------------------
            'curso_id'           => 'sometimes|integer|exists:cursos,id',
            'ciclo_id'           => 'sometimes|integer|exists:ciclos,id',
            'estudiante_id'      => 'sometimes|integer|exists:users,id',
            'matriculado_por_id' => 'sometimes|integer|exists:users,id',
            'comercial_id'       => 'sometimes|integer|exists:users,id',
            'fecha_matricula'    => 'sometimes|date',
            'fecha_inicio'       => 'sometimes|date|after_or_equal:' . ($fechaMatricula ?? 'today'),
            'monto'              => 'sometimes|numeric|min:0',
            'valor_cuota'        => 'sometimes|nullable|numeric|min:0',
            'observaciones'      => 'sometimes|nullable|string|max:5000',
            'status'             => self::getStatusValidationRule(),

            // ----------------------------------------------------------------
            // Datos de identificación
            // ----------------------------------------------------------------
            'tipo_identificacion'     => ['sometimes', 'nullable', 'string', Rule::in(array_keys(Matricula::TIPOS_IDENTIFICACION))],
            'departamento_expedicion' => 'sometimes|nullable|string|max:100',
            'ciudad_expedicion'       => 'sometimes|nullable|string|max:100',

            // ----------------------------------------------------------------
            // Datos personales
            // ----------------------------------------------------------------
            'fecha_nacimiento' => 'sometimes|nullable|date|before:today',
            'genero'           => ['sometimes', 'nullable', 'string', Rule::in(array_keys(Matricula::GENEROS))],
            'estado_civil'     => ['sometimes', 'nullable', 'string', Rule::in(array_keys(Matricula::ESTADOS_CIVILES))],
            'grupo_sanguineo'  => ['sometimes', 'nullable', 'string', Rule::in(array_keys(Matricula::GRUPOS_SANGUINEOS))],
            'rh'               => ['sometimes', 'nullable', 'string', Rule::in(array_keys(Matricula::RHS))],
            'direccion'        => 'sometimes|nullable|string|max:255',
            'lugar_origen_id'  => 'sometimes|nullable|integer|exists:poblacions,id',
            'celular'          => 'sometimes|nullable|string|max:20',
            'telefono'         => 'sometimes|nullable|string|max:20',

            // ----------------------------------------------------------------
            // Datos socioeconómicos
            // ----------------------------------------------------------------
            'nivel_educacion' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(Matricula::NIVELES_EDUCACION))],
            'ocupacion'       => 'sometimes|nullable|string|max:100',
            'empresa'         => 'sometimes|nullable|string|max:150',
            'estrato'         => 'sometimes|nullable|integer|min:1|max:6',
            'regimen_salud'   => ['sometimes', 'nullable', 'string', Rule::in(array_keys(Matricula::REGIMENES_SALUD))],

            // ----------------------------------------------------------------
            // Datos de salud y condición
            // ----------------------------------------------------------------
            'enfermedad_prioritaria' => 'sometimes|nullable|boolean',
            'discapacidad'           => 'sometimes|nullable|boolean',

            // ----------------------------------------------------------------
            // Proceso de venta / inscripción
            // ----------------------------------------------------------------
            'conocimiento_curso' => 'sometimes|nullable|boolean',
            'como_entero_curso'  => 'sometimes|nullable|string|max:200',

            // ----------------------------------------------------------------
            // Dotación
            // ----------------------------------------------------------------
            'talla_overol' => 'sometimes|nullable|string|max:10',
            'talla_botas'  => 'sometimes|nullable|string|max:10',

            // ----------------------------------------------------------------
            // Contacto de emergencia
            // ----------------------------------------------------------------
            'nombre_contacto'   => 'sometimes|nullable|string|max:150',
            'telefono_contacto' => 'sometimes|nullable|string|max:20',
            'correo_contacto'   => 'sometimes|nullable|email|max:150',

            // ----------------------------------------------------------------
            // Consentimientos e identidad cultural
            // ----------------------------------------------------------------
            'aprueba_uso_imagen' => 'sometimes|nullable|boolean',
            'multiculturalidad'  => 'sometimes|nullable|string|max:100',
            'foto'               => 'sometimes|nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return array_merge([
            'curso_id.exists'             => 'El curso seleccionado no existe.',
            'ciclo_id.exists'             => 'El ciclo seleccionado no existe.',
            'estudiante_id.exists'        => 'El estudiante seleccionado no existe.',
            'matriculado_por_id.exists'   => 'El usuario que realiza la matrícula no existe.',
            'comercial_id.exists'         => 'El usuario comercial seleccionado no existe.',
            'fecha_matricula.date'        => 'La fecha de matrícula debe ser una fecha válida.',
            'fecha_inicio.date'           => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser igual o posterior a la fecha de matrícula.',
            'monto.numeric'               => 'El monto debe ser un número.',
            'monto.min'                   => 'El monto debe ser mayor o igual a 0.',
            'valor_cuota.numeric'         => 'El valor de la cuota debe ser un número.',
            'tipo_identificacion.in'      => 'El tipo de identificación no es válido.',
            'fecha_nacimiento.date'       => 'La fecha de nacimiento debe ser una fecha válida.',
            'fecha_nacimiento.before'     => 'La fecha de nacimiento debe ser anterior a hoy.',
            'genero.in'                   => 'El género no es válido.',
            'estado_civil.in'             => 'El estado civil no es válido.',
            'grupo_sanguineo.in'          => 'El grupo sanguíneo no es válido.',
            'rh.in'                       => 'El RH no es válido.',
            'lugar_origen_id.exists'      => 'El lugar de origen seleccionado no existe.',
            'nivel_educacion.in'          => 'El nivel de educación no es válido.',
            'estrato.min'                 => 'El estrato debe ser entre 1 y 6.',
            'estrato.max'                 => 'El estrato debe ser entre 1 y 6.',
            'regimen_salud.in'            => 'El régimen de salud no es válido.',
            'correo_contacto.email'       => 'El correo del contacto debe ser una dirección de correo válida.',
        ], self::getStatusValidationMessages());
    }

    public function attributes(): array
    {
        return [
            'curso_id'                => 'curso',
            'ciclo_id'                => 'ciclo',
            'estudiante_id'           => 'estudiante',
            'matriculado_por_id'      => 'usuario que realiza la matrícula',
            'comercial_id'            => 'usuario comercial',
            'fecha_matricula'         => 'fecha de matrícula',
            'fecha_inicio'            => 'fecha de inicio',
            'tipo_identificacion'     => 'tipo de identificación',
            'departamento_expedicion' => 'departamento de expedición',
            'ciudad_expedicion'       => 'ciudad de expedición',
            'fecha_nacimiento'        => 'fecha de nacimiento',
            'estado_civil'            => 'estado civil',
            'grupo_sanguineo'         => 'grupo sanguíneo',
            'lugar_origen_id'         => 'lugar de origen',
            'nivel_educacion'         => 'nivel de educación',
            'regimen_salud'           => 'régimen de salud',
            'enfermedad_prioritaria'  => 'enfermedad de atención prioritaria',
            'conocimiento_curso'      => 'conocimiento del curso',
            'como_entero_curso'       => 'cómo se enteró del curso',
            'valor_cuota'             => 'valor de cuota',
            'talla_overol'            => 'talla de overol',
            'talla_botas'             => 'talla de botas',
            'nombre_contacto'         => 'nombre del contacto de emergencia',
            'telefono_contacto'       => 'teléfono del contacto de emergencia',
            'correo_contacto'         => 'correo del contacto de emergencia',
            'aprueba_uso_imagen'      => 'aprobación de uso de imagen',
            'multiculturalidad'       => 'identidad multicultural',
        ];
    }
}
