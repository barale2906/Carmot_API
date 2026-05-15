<?php

namespace App\Http\Requests\Api\Academico;

use App\Models\Academico\Matricula;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMatriculaRequest extends FormRequest
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
        return [
            // ----------------------------------------------------------------
            // Datos académicos / administrativos
            // ----------------------------------------------------------------
            'curso_id'             => 'required|integer|exists:cursos,id',
            'ciclo_id'             => 'required|integer|exists:ciclos,id',
            'estudiante_id'        => 'required|integer|exists:users,id',
            'matriculado_por_id'   => 'required|integer|exists:users,id',
            'comercial_id'         => 'required|integer|exists:users,id',
            'fecha_matricula'      => 'required|date',
            'fecha_inicio'         => 'required|date|after_or_equal:fecha_matricula',
            'monto'                => 'required|numeric|min:0',
            'valor_cuota'          => 'nullable|numeric|min:0',
            'observaciones'        => 'nullable|string|max:5000',
            'status'               => self::getStatusValidationRule(),

            // ----------------------------------------------------------------
            // Datos de identificación
            // ----------------------------------------------------------------
            'tipo_identificacion'     => ['nullable', 'string', Rule::in(array_keys(Matricula::TIPOS_IDENTIFICACION))],
            'departamento_expedicion' => 'nullable|string|max:100',
            'ciudad_expedicion'       => 'nullable|string|max:100',

            // ----------------------------------------------------------------
            // Datos personales
            // ----------------------------------------------------------------
            'fecha_nacimiento' => 'nullable|date|before:today',
            'genero'           => ['nullable', 'string', Rule::in(array_keys(Matricula::GENEROS))],
            'estado_civil'     => ['nullable', 'string', Rule::in(array_keys(Matricula::ESTADOS_CIVILES))],
            'grupo_sanguineo'  => ['nullable', 'string', Rule::in(array_keys(Matricula::GRUPOS_SANGUINEOS))],
            'rh'               => ['nullable', 'string', Rule::in(array_keys(Matricula::RHS))],
            'direccion'        => 'nullable|string|max:255',
            'lugar_origen_id'  => 'nullable|integer|exists:poblacions,id',
            'celular'          => 'nullable|string|max:20',
            'telefono'         => 'nullable|string|max:20',

            // ----------------------------------------------------------------
            // Datos socioeconómicos
            // ----------------------------------------------------------------
            'nivel_educacion' => ['nullable', 'string', Rule::in(array_keys(Matricula::NIVELES_EDUCACION))],
            'ocupacion'       => 'nullable|string|max:100',
            'empresa'         => 'nullable|string|max:150',
            'estrato'         => 'nullable|integer|min:1|max:6',
            'regimen_salud'   => ['nullable', 'string', Rule::in(array_keys(Matricula::REGIMENES_SALUD))],

            // ----------------------------------------------------------------
            // Datos de salud y condición
            // ----------------------------------------------------------------
            'enfermedad_prioritaria' => 'nullable|boolean',
            'discapacidad'           => 'nullable|boolean',

            // ----------------------------------------------------------------
            // Proceso de venta / inscripción
            // ----------------------------------------------------------------
            'conocimiento_curso' => 'nullable|boolean',
            'como_entero_curso'  => 'nullable|string|max:200',

            // ----------------------------------------------------------------
            // Dotación
            // ----------------------------------------------------------------
            'talla_overol' => 'nullable|string|max:10',
            'talla_botas'  => 'nullable|string|max:10',

            // ----------------------------------------------------------------
            // Contacto de emergencia
            // ----------------------------------------------------------------
            'nombre_contacto'   => 'nullable|string|max:150',
            'telefono_contacto' => 'nullable|string|max:20',
            'correo_contacto'   => 'nullable|email|max:150',

            // ----------------------------------------------------------------
            // Consentimientos e identidad cultural
            // ----------------------------------------------------------------
            'aprueba_uso_imagen' => 'nullable|boolean',
            'multiculturalidad'  => 'nullable|string|max:100',
            'foto'               => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return array_merge([
            // Datos académicos
            'curso_id.required'           => 'El curso es obligatorio.',
            'curso_id.exists'             => 'El curso seleccionado no existe.',
            'ciclo_id.required'           => 'El ciclo es obligatorio.',
            'ciclo_id.exists'             => 'El ciclo seleccionado no existe.',
            'estudiante_id.required'      => 'El estudiante es obligatorio.',
            'estudiante_id.exists'        => 'El estudiante seleccionado no existe.',
            'matriculado_por_id.required' => 'El usuario que realiza la matrícula es obligatorio.',
            'matriculado_por_id.exists'   => 'El usuario que realiza la matrícula no existe.',
            'comercial_id.required'       => 'El usuario comercial es obligatorio.',
            'comercial_id.exists'         => 'El usuario comercial seleccionado no existe.',
            'fecha_matricula.required'    => 'La fecha de matrícula es obligatoria.',
            'fecha_matricula.date'        => 'La fecha de matrícula debe ser una fecha válida.',
            'fecha_inicio.required'       => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'           => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser igual o posterior a la fecha de matrícula.',
            'monto.required'              => 'El monto es obligatorio.',
            'monto.numeric'               => 'El monto debe ser un número.',
            'monto.min'                   => 'El monto debe ser mayor o igual a 0.',
            'valor_cuota.numeric'         => 'El valor de la cuota debe ser un número.',
            'valor_cuota.min'             => 'El valor de la cuota debe ser mayor o igual a 0.',
            'observaciones.max'           => 'Las observaciones no pueden tener más de 5000 caracteres.',

            // Identificación
            'tipo_identificacion.in'      => 'El tipo de identificación no es válido. Valores permitidos: ' . implode(', ', array_keys(Matricula::TIPOS_IDENTIFICACION)) . '.',
            'fecha_nacimiento.date'       => 'La fecha de nacimiento debe ser una fecha válida.',
            'fecha_nacimiento.before'     => 'La fecha de nacimiento debe ser anterior a hoy.',
            'genero.in'                   => 'El género no es válido. Valores permitidos: ' . implode(', ', array_keys(Matricula::GENEROS)) . '.',
            'estado_civil.in'             => 'El estado civil no es válido. Valores permitidos: ' . implode(', ', array_keys(Matricula::ESTADOS_CIVILES)) . '.',
            'grupo_sanguineo.in'          => 'El grupo sanguíneo no es válido. Valores permitidos: ' . implode(', ', array_keys(Matricula::GRUPOS_SANGUINEOS)) . '.',
            'rh.in'                       => 'El RH no es válido. Valores permitidos: ' . implode(', ', array_keys(Matricula::RHS)) . '.',
            'lugar_origen_id.exists'      => 'El lugar de origen seleccionado no existe.',
            'nivel_educacion.in'          => 'El nivel de educación no es válido. Valores permitidos: ' . implode(', ', array_keys(Matricula::NIVELES_EDUCACION)) . '.',
            'estrato.min'                 => 'El estrato debe ser entre 1 y 6.',
            'estrato.max'                 => 'El estrato debe ser entre 1 y 6.',
            'regimen_salud.in'            => 'El régimen de salud no es válido. Valores permitidos: ' . implode(', ', array_keys(Matricula::REGIMENES_SALUD)) . '.',
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
