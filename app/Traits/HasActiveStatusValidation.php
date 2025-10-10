<?php

namespace App\Traits;

trait HasActiveStatusValidation
{
    /**
     * Obtiene la regla de validación para el campo status.
     *
     * @return string
     */
    public static function getStatusValidationRule(): string
    {
        $statusOptions = self::getActiveStatusOptions();
        $statusKeys = array_keys($statusOptions);

        return 'sometimes|integer|in:' . implode(',', $statusKeys);
    }

    /**
     * Obtiene los mensajes de error para el campo status.
     *
     * @return array<string, string>
     */
    public static function getStatusValidationMessages(): array
    {
        $statusOptions = self::getActiveStatusOptions();
        $statusList = [];

        foreach ($statusOptions as $key => $value) {
            $statusList[] = "$key ($value)";
        }

        return [
            'status.integer' => 'El estado debe ser un número entero.',
            'status.in' => 'El estado debe ser uno de los valores válidos: ' . implode(', ', $statusList) . '.',
        ];
    }
}
