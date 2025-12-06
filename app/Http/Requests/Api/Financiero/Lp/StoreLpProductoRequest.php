<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Request StoreLpProductoRequest
 *
 * Valida los datos para crear un nuevo producto en el catálogo de productos.
 * Incluye validación de relaciones con tipo de producto y referencias polimórficas.
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class StoreLpProductoRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

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
            'tipo_producto_id' => 'required|integer|exists:lp_tipos_producto,id',
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:100|unique:lp_productos,codigo',
            'descripcion' => 'nullable|string',
            'referencia_id' => [
                'nullable',
                'integer',
                Rule::requiredIf($this->filled('referencia_tipo')),
                function ($attribute, $value, $fail) {
                    if ($this->filled('referencia_tipo') && !$value) {
                        $fail('El campo referencia_id es obligatorio cuando se especifica referencia_tipo.');
                    }
                },
            ],
            'referencia_tipo' => [
                'nullable',
                'string',
                Rule::in(['curso', 'modulo']),
                Rule::requiredIf($this->filled('referencia_id')),
                function ($attribute, $value, $fail) {
                    if ($this->filled('referencia_id') && !$value) {
                        $fail('El campo referencia_tipo es obligatorio cuando se especifica referencia_id.');
                    }
                },
            ],
            'status' => self::getStatusValidationRule(),
        ];
    }

    /**
     * Configurar validaciones adicionales después de las reglas básicas.
     * Valida que la referencia exista en la tabla correspondiente.
     *
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('referencia_id') && $this->filled('referencia_tipo')) {
                $tabla = $this->referencia_tipo === 'curso' ? 'cursos' : 'modulos';
                $existe = DB::table($tabla)->where('id', $this->referencia_id)->exists();

                if (!$existe) {
                    $validator->errors()->add(
                        'referencia_id',
                        "La referencia especificada no existe en la tabla {$tabla}."
                    );
                }
            }
        });
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $statusMessages = self::getStatusValidationMessages();

        return array_merge([
            'tipo_producto_id.required' => 'El tipo de producto es obligatorio.',
            'tipo_producto_id.integer' => 'El tipo de producto debe ser un número entero.',
            'tipo_producto_id.exists' => 'El tipo de producto seleccionado no existe.',
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede exceder 100 caracteres.',
            'codigo.unique' => 'El código del producto ya existe.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'referencia_id.integer' => 'El ID de referencia debe ser un número entero.',
            'referencia_tipo.in' => 'El tipo de referencia debe ser "curso" o "modulo".',
        ], $statusMessages);
    }
}
