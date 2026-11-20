<?php

namespace App\Http\Requests\Api\Academico;

/**
 * Validación previa (sin guardar nada) de todo lo que el asistente de matrícula enviará.
 *
 * Aplica las mismas reglas de StoreMatriculaRequest. Si la matrícula es para un estudiante
 * nuevo no hay `estudiante_id` todavía: se exige el bloque `estudiante` con las reglas de
 * StoreEstudianteRequest, para detectar cualquier error antes de crear el usuario.
 */
class ValidarMatriculaRequest extends StoreMatriculaRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->filled('estudiante_id')) {
            return $rules;
        }

        $rules['estudiante_id'] = 'nullable|integer';
        $rules['estudiante']    = 'required|array';

        foreach ((new StoreEstudianteRequest())->rules() as $campo => $regla) {
            // `sedes` las deriva el asistente del ciclo elegido; no se validan aquí.
            if (str_starts_with($campo, 'sedes')) {
                continue;
            }
            // Con el campo anidado, `unique:users` inferiría la columna "estudiante.email".
            $rules["estudiante.$campo"] = array_map(
                fn ($r) => $r === 'unique:users' ? "unique:users,$campo" : $r,
                $regla
            );
        }

        return $rules;
    }

    public function messages(): array
    {
        $mensajes = parent::messages();

        foreach ((new StoreEstudianteRequest())->messages() as $campo => $mensaje) {
            $mensajes["estudiante.$campo"] = $mensaje;
        }

        return $mensajes + ['estudiante.required' => 'Los datos del estudiante son obligatorios.'];
    }
}
