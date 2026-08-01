<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para actualizar una categoría de inventario.
 */
class UpdateInvCategoriaRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * La autorización se controla mediante middleware de permisos.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la actualización.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoriaId = $this->route('categoria');

        return [
            'nombre'      => [
                'required',
                'string',
                'max:150',
                Rule::unique('inv_categorias', 'nombre')
                    ->ignore($categoriaId)
                    ->whereNull('deleted_at'),
            ],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'status'      => self::getStatusValidationRule(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.max'      => 'El nombre no puede superar 150 caracteres.',
            'nombre.unique'   => 'Ya existe otra categoría con ese nombre.',
        ], self::getStatusValidationMessages());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre'      => 'nombre',
            'descripcion' => 'descripción',
            'status'      => 'estado',
        ];
    }
}
