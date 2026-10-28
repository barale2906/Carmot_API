<?php

namespace App\Http\Requests\Api\Academico;

use App\Services\Academico\SabanaNotasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarConsolidadoNotasRequest extends FormRequest
{
    /**
     * Filtros del consolidado de notas.
     */
    private const FILTROS = ['ciclo_id', 'grupo_id', 'profesor_id', 'estudiante_id', 'buscar', 'estado', 'por_recuperar'];

    /**
     * Estados de resultado por los que se puede filtrar.
     */
    private const ESTADOS = [
        SabanaNotasService::ESTADO_PENDIENTE,
        SabanaNotasService::ESTADO_APROBADO,
        SabanaNotasService::ESTADO_REPROBADO,
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
     * Obtiene las reglas de validación. Se exige al menos uno de ciclo, grupo, profesor
     * o estudiante para no calcular la institución completa. `buscar` filtra por nombre,
     * correo o documento del estudiante.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $alcance = 'required_without_all:ciclo_id,grupo_id,profesor_id,estudiante_id,buscar';

        return [
            'ciclo_id'      => ['nullable', $alcance, 'integer', 'exists:ciclos,id'],
            'grupo_id'      => ['nullable', 'integer', 'exists:grupos,id'],
            'profesor_id'   => ['nullable', 'integer', 'exists:users,id'],
            'estudiante_id' => ['nullable', 'integer', 'exists:users,id'],
            'buscar'        => ['nullable', 'string', 'min:3', 'max:100'],
            'estado'        => ['nullable', Rule::in(self::ESTADOS)],
            'por_recuperar' => ['nullable', 'boolean'],
            'page'          => ['nullable', 'integer', 'min:1'],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:500'],
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
            'ciclo_id.required_without_all' => 'Indique al menos un ciclo, grupo, profesor o estudiante.',
            'buscar.min'                    => 'La búsqueda de estudiante debe tener al menos 3 caracteres.',
            'estado.in'                     => 'El estado debe ser: ' . implode(', ', self::ESTADOS) . '.',
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
            'ciclo_id'      => 'ciclo',
            'grupo_id'      => 'grupo',
            'profesor_id'   => 'profesor',
            'estudiante_id' => 'estudiante',
            'buscar'        => 'búsqueda de estudiante',
            'estado'        => 'estado',
            'por_recuperar' => 'por recuperar',
            'per_page'      => 'registros por página',
        ];
    }

    /**
     * Filtros a aplicar según quién consulta: el alumno solo ve lo suyo, el profesor solo
     * sus grupos y el resto del personal, los grupos de sus sedes.
     *
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        $usuario = $this->user();
        $filtros = $this->only(self::FILTROS);
        $filtros['por_recuperar'] = $this->boolean('por_recuperar');

        if ($usuario->esSoloEstudiante()) {
            $filtros['estudiante_id'] = $usuario->id;
        } elseif ($usuario->esSoloProfesor()) {
            $filtros['profesor_id'] = $usuario->id;
        } else {
            $filtros['sede_ids'] = $usuario->idsSedesAccesibles();
        }

        return $filtros;
    }
}
