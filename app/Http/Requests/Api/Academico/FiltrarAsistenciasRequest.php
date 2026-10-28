<?php

namespace App\Http\Requests\Api\Academico;

use App\Models\Academico\Asistencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarAsistenciasRequest extends FormRequest
{
    /**
     * Filtros del listado de asistencias (y de su descarga en Excel).
     */
    private const FILTROS = [
        'estudiante_id', 'estudiante', 'grupo_id', 'sede_id', 'ciclo_id', 'curso_id', 'modulo_id',
        'clase_programada_id', 'estado', 'justificada', 'fecha', 'fecha_desde', 'fecha_hasta',
        'include_trashed', 'only_trashed',
    ];

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
     * Obtiene las reglas de validación. `estudiante` busca por nombre, correo o documento.
     * Las fechas son las de la clase: `fecha` para un día y `fecha_desde`/`fecha_hasta` para un rango.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estudiante_id' => ['nullable', 'integer'],
            'estudiante' => ['nullable', 'string', 'max:100'],
            'grupo_id' => ['nullable', 'integer'],
            'sede_id' => ['nullable', 'integer'],
            'ciclo_id' => ['nullable', 'integer'],
            'curso_id' => ['nullable', 'integer'],
            'modulo_id' => ['nullable', 'integer'],
            'clase_programada_id' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::in(Asistencia::ESTADOS)],
            'justificada' => ['nullable', 'boolean'],
            'fecha' => ['nullable', 'date'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
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
            'estado.in' => 'El estado debe ser: ' . implode(', ', Asistencia::ESTADOS) . '.',
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
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
            'estudiante_id' => 'estudiante',
            'estudiante' => 'búsqueda de estudiante',
            'grupo_id' => 'grupo',
            'sede_id' => 'sede',
            'ciclo_id' => 'ciclo',
            'curso_id' => 'curso',
            'modulo_id' => 'módulo',
            'clase_programada_id' => 'clase',
            'estado' => 'estado',
            'justificada' => 'justificada',
            'fecha' => 'fecha',
            'fecha_desde' => 'fecha inicial',
            'fecha_hasta' => 'fecha final',
            'per_page' => 'registros por página',
        ];
    }

    /**
     * Filtros a aplicar. A un alumno siempre se le limitan a sus propias asistencias.
     *
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        $filtros = $this->only(self::FILTROS);

        if ($this->user()->esSoloEstudiante()) {
            $filtros['estudiante_id'] = $this->user()->id;
        }

        return $filtros;
    }
}
