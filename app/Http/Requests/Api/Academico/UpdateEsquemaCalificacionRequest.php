<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEsquemaCalificacionRequest extends FormRequest
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
        $esquemaId = $this->route('esquema_calificacion') ?? $this->route('esquema');

        return [
            'modulo_id' => 'sometimes|integer|exists:modulos,id',
            'grupo_id' => 'nullable|integer|exists:grupos,id',
            'nombre_esquema' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('esquema_calificacions', 'nombre_esquema')
                    ->where('modulo_id', $this->modulo_id ?? $this->route('esquema_calificacion')?->modulo_id)
                    ->where('grupo_id', $this->grupo_id ?? $this->route('esquema_calificacion')?->grupo_id ?? null)
                    ->ignore($esquemaId)
            ],
            'descripcion' => 'nullable|string',
            'condicion_aplicacion' => 'nullable|string',
            'status' => self::getStatusValidationRule(),
            'tipos_nota' => 'sometimes|array|min:1',
            'tipos_nota.*.id' => 'sometimes|integer|exists:tipo_nota_esquemas,id',
            'tipos_nota.*.nombre_tipo' => 'required_with:tipos_nota|string|max:255',
            'tipos_nota.*.peso' => 'required_with:tipos_nota|numeric|min:0|max:100',
            'tipos_nota.*.orden' => 'required_with:tipos_nota|integer|min:1',
            'tipos_nota.*.nota_minima' => 'nullable|numeric|min:0',
            'tipos_nota.*.nota_maxima' => 'nullable|numeric|min:0',
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
            'modulo_id.exists' => 'El módulo seleccionado no existe.',
            'grupo_id.exists' => 'El grupo seleccionado no existe.',
            'nombre_esquema.unique' => 'Ya existe un esquema con este nombre para este módulo y grupo.',
            'tipos_nota.min' => 'Debe definir al menos un tipo de nota.',
            'tipos_nota.*.nombre_tipo.required_with' => 'El nombre del tipo de nota es obligatorio.',
            'tipos_nota.*.peso.required_with' => 'El peso es obligatorio.',
            'tipos_nota.*.peso.numeric' => 'El peso debe ser un número.',
            'tipos_nota.*.peso.min' => 'El peso no puede ser menor a 0.',
            'tipos_nota.*.peso.max' => 'El peso no puede ser mayor a 100.',
        ], self::getStatusValidationMessages());
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
