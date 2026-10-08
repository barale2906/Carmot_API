<?php

namespace App\Http\Requests\Api\Academico;

use App\Services\Academico\ClonarCicloService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ClonarCicloRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza las fechas a YYYY-MM-DD, evitando desfases de zona horaria.
     */
    protected function prepareForValidation(): void
    {
        $campos = [];
        foreach (['fecha_inicio', 'fecha_fin'] as $campo) {
            if ($this->filled($campo)) {
                $campos[$campo] = substr((string) $this->$campo, 0, 10);
            }
        }
        if ($campos) {
            $this->merge($campos);
        }
    }

    /**
     * Reglas de validación: solo la fecha de inicio es obligatoria
     * (ver withValidator para la fecha mínima).
     * La fecha_fin aplica únicamente si el ciclo origen es de fecha fin fija.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_inicio' => 'required|date|after_or_equal:today',
            'nombre'       => 'nullable|string|max:255|unique:ciclos,nombre',
            'fecha_fin'    => 'nullable|date|after:fecha_inicio',
        ];
    }

    /**
     * La fecha de inicio debe ser posterior a la terminación del ciclo origen,
     * para que el clon nunca se traslape con él, y corresponder a un día de clase
     * laborable (ver ClonarCicloService::esDiaDeClase). Si el origen no tiene
     * fechas calculadas no se puede clonar.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('fecha_inicio')) {
                return;
            }

            $clonar      = app(ClonarCicloService::class);
            $origen      = $this->route('ciclo');
            $terminacion = $clonar->fechaTerminacion($origen);
            $fecha       = $this->date('fecha_inicio');

            if (! $terminacion) {
                $validator->errors()->add('fecha_inicio', 'El ciclo a clonar no tiene fechas calculadas; calcule su fecha de fin antes de clonarlo.');
            } elseif ($fecha->lte($terminacion)) {
                $validator->errors()->add('fecha_inicio', sprintf(
                    'La fecha de inicio debe ser posterior a la finalización del ciclo a clonar (%s).',
                    $terminacion->toDateString()
                ));
            } elseif (! $clonar->esDiaDeClase($origen, $fecha)) {
                $validator->errors()->add('fecha_inicio', sprintf(
                    'La fecha de inicio debe ser un día de clase laborable del ciclo (%s).',
                    implode(', ', $clonar->diasClase($origen))
                ));
            }
        });
    }

    /**
     * Mensajes de error personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.required'       => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'           => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser igual o posterior a hoy.',
            'nombre.string'               => 'El nombre del ciclo debe ser una cadena de texto.',
            'nombre.max'                  => 'El nombre del ciclo no puede tener más de 255 caracteres.',
            'nombre.unique'               => 'Ya existe un ciclo con este nombre.',
            'fecha_fin.date'              => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after'             => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ];
    }

    /**
     * Atributos personalizados.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin'    => 'fecha de fin',
        ];
    }
}
