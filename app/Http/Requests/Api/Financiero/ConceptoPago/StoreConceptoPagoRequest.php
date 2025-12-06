<?php

namespace App\Http\Requests\Api\Financiero\ConceptoPago;

use App\Models\Financiero\ConceptoPago\ConceptoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Request StoreConceptoPagoRequest
 *
 * Valida los datos para crear un nuevo concepto de pago en el sistema financiero.
 * Incluye validación de campos requeridos y tipos de concepto.
 *
 * @package App\Http\Requests\Api\Financiero\ConceptoPago
 */
class StoreConceptoPagoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     * La autorización se maneja mediante middleware y permisos.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'tipo' => [
                'required',
                function ($attribute, $value, $fail) {
                    // Permitir tanto integer (índice) como string (nombre del tipo)
                    if (is_numeric($value)) {
                        $indice = (int) $value;
                        if (!ConceptoPago::esIndiceValido($indice)) {
                            $fail('El índice de tipo no es válido.');
                        }
                    } elseif (is_string($value)) {
                        $indice = ConceptoPago::getIndicePorNombre($value);
                        if ($indice === null) {
                            $fail('El nombre de tipo no es válido. Tipos permitidos: ' . implode(', ', ConceptoPago::getTiposDisponibles()));
                        }
                    } else {
                        $fail('El tipo debe ser un número (índice) o un string (nombre del tipo).');
                    }
                },
            ],
            'valor' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
        ];
    }

    /**
     * Prepara los datos para la validación.
     * Convierte el nombre del tipo a índice si es necesario.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tipo') && is_string($this->tipo) && !is_numeric($this->tipo)) {
            $indice = ConceptoPago::getIndicePorNombre($this->tipo);
            if ($indice !== null) {
                $this->merge(['tipo' => $indice]);
            }
        } elseif ($this->has('tipo') && is_numeric($this->tipo)) {
            $this->merge(['tipo' => (int) $this->tipo]);
        }
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del concepto de pago es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'tipo.required' => 'El tipo del concepto de pago es obligatorio.',
            'valor.required' => 'El valor del concepto de pago es obligatorio.',
            'valor.numeric' => 'El valor debe ser un número.',
            'valor.min' => 'El valor no puede ser negativo.',
            'valor.regex' => 'El valor debe tener máximo 2 decimales.',
        ];
    }
}

