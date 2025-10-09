<?php

namespace App\Traits;

trait HasTipo
{
    /**
     * Obtiene las opciones de tipo para Curso.
     *
     * @return array<string, string> Array con los tipos disponibles
     */
    public static function getTipoOptions(): array
    {
        return [
            0 => 'Curso Práctico',
            1 => 'Técnico Laboral',
        ];
    }

    /**
     * Obtiene el texto del tipo basado en el número de tipo.
     *
     * @param int|null $tipo Número del tipo
     * @return string Descripción del tipo
     */
    public static function getTipoText(?int $tipo): string
    {
        $tipoOptions = self::getTipoOptions();

        return $tipoOptions[$tipo] ?? 'Desconocido';
    }

    /**
     * Obtiene el texto del tipo para la instancia actual del modelo.
     *
     * @return string Descripción del tipo
     */
    public function getTipoTextAttribute(): string
    {
        return self::getTipoText($this->tipo);
    }

    /**
     * Obtiene las opciones de tipo en formato para validación.
     *
     * @return string String con los valores válidos para validación
     */
    public static function getTipoValidationRule(): string
    {
        $tipos = array_keys(self::getTipoOptions());
        return 'in:' . implode(',', $tipos);
    }
}
