<?php

namespace App\Http\Requests\Api\Activos;

use App\Http\Requests\Api\Activos\Concerns\ValidaUbicacionActivo;
use App\Models\Activos\ActActivo;
use Illuminate\Foundation\Http\FormRequest;

class TrasladarActActivoRequest extends FormRequest
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
            'sede_id'        => ['required', 'integer', 'exists:sedes,id'],
            'area_id'        => ['nullable', 'integer', 'exists:areas,id'],
            'responsable_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id', $this->reglaResponsable()],
            'fecha'          => ['required', 'date', 'before_or_equal:today'],
            'motivo'         => ['required', 'string', 'min:5', 'max:1000'],
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
            'fecha.before_or_equal' => 'La fecha del traslado no puede ser futura.',
            'motivo.required'       => 'Indica el motivo del traslado.',
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
            'sede_id'        => 'sede de destino',
            'area_id'        => 'área de destino',
            'responsable_id' => 'responsable',
        ];
    }

    /**
     * No se traslada un activo de baja ni a la misma ubicación; el destino debe ser una
     * sede asignada al usuario y el área debe pertenecer a ella.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var ActActivo $activo */
            $activo = $this->route('activo');

            if ($activo->estaDeBaja()) {
                $validator->errors()->add('sede_id', 'El activo está dado de baja: no se puede trasladar.');
                return;
            }

            $mismaUbicacion = (int) $this->sede_id === $activo->sede_id
                && ($this->area_id ? (int) $this->area_id : null) === $activo->area_id;
            if ($mismaUbicacion) {
                $validator->errors()->add('area_id', 'El activo ya está en esa sede y área.');
                return;
            }

            $this->validarUbicacion($validator, (int) $this->sede_id, $this->area_id ? (int) $this->area_id : null);
        });
    }
}
