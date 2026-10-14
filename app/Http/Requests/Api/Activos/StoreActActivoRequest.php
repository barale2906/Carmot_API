<?php

namespace App\Http\Requests\Api\Activos;

use App\Http\Requests\Api\Activos\Concerns\ValidaUbicacionActivo;
use App\Models\Activos\ActActivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActActivoRequest extends FormRequest
{
    use ValidaUbicacionActivo;

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
            'codigo'         => ['required', 'string', 'max:40', Rule::unique('act_activos', 'codigo')],
            'nombre'         => ['required', 'string', 'max:200'],
            'descripcion'    => ['nullable', 'string', 'max:2000'],
            'categoria_id'   => ['required', 'integer', Rule::exists('act_categorias', 'id')->where('status', 1)->whereNull('deleted_at')],
            'sede_id'        => ['required', 'integer', 'exists:sedes,id'],
            'area_id'        => ['nullable', 'integer', 'exists:areas,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'marca'          => ['nullable', 'string', 'max:100'],
            'modelo'         => ['nullable', 'string', 'max:100'],
            'serial'         => ['nullable', 'string', 'max:100'],
            'fecha_ingreso'  => ['required', 'date', 'before_or_equal:today'],
            'valor'          => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            // Un activo nace en uso; la baja solo se registra como cambio de estado.
            'estado'         => ['sometimes', Rule::in(array_diff(array_keys(ActActivo::ESTADOS), [ActActivo::ESTADO_BAJA]))],
            'observaciones'  => ['nullable', 'string', 'max:2000'],
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
            'estado.in'                    => 'Estado inválido: un activo nuevo no puede registrarse de baja.',
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
            'sede_id'        => 'sede',
            'area_id'        => 'área',
            'responsable_id' => 'responsable',
            'fecha_ingreso'  => 'fecha de ingreso',
        ];
    }

    /**
     * La sede debe estar asignada al usuario y el área pertenecer a la sede.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isEmpty()) {
                $this->validarUbicacion($validator, (int) $this->sede_id, $this->area_id ? (int) $this->area_id : null);
            }
        });
    }
}
