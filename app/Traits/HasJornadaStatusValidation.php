<?php

namespace App\Traits;

trait HasJornadaStatusValidation
{
    /**
     * Obtiene la regla de validación para el campo jornada.
     *
     * @return string
     */
    public static function getJornadaValidationRule(): string
    {
        $jornadaOptions = static::getJornadaOptions();
        $jornadaKeys = array_keys($jornadaOptions);

        return 'required|integer|in:' . implode(',', $jornadaKeys);
    }

    /**
     * Obtiene la regla de validación para el campo jornada (opcional).
     *
     * @return string
     */
    public static function getJornadaValidationRuleOptional(): string
    {
        $jornadaOptions = static::getJornadaOptions();
        $jornadaKeys = array_keys($jornadaOptions);

        return 'sometimes|required|integer|in:' . implode(',', $jornadaKeys);
    }

    /**
     * Obtiene los mensajes de error para el campo jornada.
     *
     * @return array<string, string>
     */
    public static function getJornadaValidationMessages(): array
    {
        $jornadaOptions = static::getJornadaOptions();
        $jornadaList = [];

        foreach ($jornadaOptions as $key => $value) {
            $jornadaList[] = "$key ($value)";
        }

        return [
            'jornada.required' => 'La jornada es obligatoria.',
            'jornada.integer' => 'La jornada debe ser un número entero.',
            'jornada.in' => 'La jornada debe ser uno de los valores válidos: ' . implode(', ', $jornadaList) . '.',
        ];
    }
}

