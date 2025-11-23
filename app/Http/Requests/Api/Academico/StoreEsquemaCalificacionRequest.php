<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEsquemaCalificacionRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
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
            'modulo_id' => 'required|integer|exists:modulos,id',
            'grupo_id' => 'nullable|integer|exists:grupos,id',
            'nombre_esquema' => [
                'required',
                'string',
                'max:255',
                Rule::unique('esquema_calificacions', 'nombre_esquema')
                    ->where('modulo_id', $this->modulo_id)
                    ->where('grupo_id', $this->grupo_id ?? null)
            ],
            'descripcion' => 'nullable|string',
            'condicion_aplicacion' => 'nullable|string',
            'status' => self::getStatusValidationRule(),
            'tipos_nota' => 'required|array|min:1',
            'tipos_nota.*.nombre_tipo' => 'required|string|max:255',
            'tipos_nota.*.peso' => 'required|numeric|min:0|max:100',
            'tipos_nota.*.orden' => 'required|integer|min:1',
            'tipos_nota.*.nota_minima' => 'nullable|numeric|min:0',
            'tipos_nota.*.nota_maxima' => 'nullable|numeric|min:0|gt:tipos_nota.*.nota_minima',
            'tipos_nota.*.descripcion' => 'nullable|string',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'modulo_id.required' => 'El módulo es obligatorio.',
            'modulo_id.exists' => 'El módulo seleccionado no existe.',
            'grupo_id.exists' => 'El grupo seleccionado no existe.',
            'nombre_esquema.required' => 'El nombre del esquema es obligatorio.',
            'nombre_esquema.unique' => 'Ya existe un esquema con este nombre para este módulo y grupo.',
            'tipos_nota.required' => 'Debe definir al menos un tipo de nota.',
            'tipos_nota.min' => 'Debe definir al menos un tipo de nota.',
            'tipos_nota.*.nombre_tipo.required' => 'El nombre del tipo de nota es obligatorio.',
            'tipos_nota.*.peso.required' => 'El peso es obligatorio.',
            'tipos_nota.*.peso.numeric' => 'El peso debe ser un número.',
            'tipos_nota.*.peso.min' => 'El peso no puede ser menor a 0.',
            'tipos_nota.*.peso.max' => 'El peso no puede ser mayor a 100.',
            'tipos_nota.*.orden.required' => 'El orden es obligatorio.',
            'tipos_nota.*.nota_maxima.gt' => 'La nota máxima debe ser mayor que la nota mínima.',
        ], self::getStatusValidationMessages());
    }

    /**
     * Obtiene los atributos personalizados.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'modulo_id' => 'módulo',
            'grupo_id' => 'grupo',
            'nombre_esquema' => 'nombre del esquema',
            'tipos_nota' => 'tipos de nota',
            'tipos_nota.*.nombre_tipo' => 'nombre del tipo de nota',
            'tipos_nota.*.peso' => 'peso',
            'tipos_nota.*.orden' => 'orden',
        ];
    }

    /**
     * Prepara los datos para la validación.
     */
    protected function prepareForValidation(): void
    {
        // Validar que la suma de pesos sea 100%
        if ($this->has('tipos_nota')) {
            $sumaPesos = collect($this->tipos_nota)->sum('peso');
            if (abs($sumaPesos - 100) > 0.01) {
                $this->merge(['_suma_pesos' => $sumaPesos]);
            }
        }
    }

    /**
     * Configurar reglas de validación personalizadas.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('tipos_nota')) {
                $sumaPesos = collect($this->tipos_nota)->sum('peso');
                if (abs($sumaPesos - 100) > 0.01) {
                    $validator->errors()->add(
                        'tipos_nota',
                        "La suma de los pesos debe ser exactamente 100%. Suma actual: {$sumaPesos}%"
                    );
                }
            }
        });
    }
}
