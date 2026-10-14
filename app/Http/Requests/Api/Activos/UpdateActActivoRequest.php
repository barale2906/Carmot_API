<?php

namespace App\Http\Requests\Api\Activos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición de los datos descriptivos del activo. La sede, el área y el estado no se
 * editan aquí: cambian con los endpoints de traslado y de cambio de estado, que dejan historial.
 */
class UpdateActActivoRequest extends FormRequest
{
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
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo'         => ['sometimes', 'string', 'max:40', Rule::unique('act_activos', 'codigo')->ignore($this->route('activo'))],
            'nombre'         => ['sometimes', 'string', 'max:200'],
            'descripcion'    => ['sometimes', 'nullable', 'string', 'max:2000'],
            'categoria_id'   => ['sometimes', 'integer', Rule::exists('act_categorias', 'id')->where('status', 1)->whereNull('deleted_at')],
            'responsable_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'marca'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'modelo'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'serial'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'fecha_ingreso'  => ['sometimes', 'date', 'before_or_equal:today'],
            'valor'          => ['sometimes', 'numeric', 'min:0', 'max:9999999999999'],
            'observaciones'  => ['sometimes', 'nullable', 'string', 'max:2000'],
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
            'codigo.unique'                => 'Ya existe un activo con este código.',
            'categoria_id.exists'          => 'La categoría no existe o está inactiva.',
            'fecha_ingreso.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
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
            'codigo'         => 'código',
            'categoria_id'   => 'categoría',
            'responsable_id' => 'responsable',
            'fecha_ingreso'  => 'fecha de ingreso',
        ];
    }
}
