<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la previsualización de una versión de plantilla.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class PrevisualizarDocPlantillaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado (los permisos se validan por middleware).
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación de la petición.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entidad_id' => 'nullable|integer',
        ];
    }

    /**
     * Exige un registro real cuando el tipo de documento se asocia a una entidad.
     *
     * Sin registro no hay datos con los que resolver variables ni bloques, y la
     * previsualización no mostraría nada útil.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var DocPlantilla|null $plantilla */
            $plantilla = $this->route('plantilla');

            if (!$plantilla) {
                return;
            }

            $entidadType = $plantilla->tipoDocumento->entidad_type;

            if (!$entidadType) {
                return;
            }

            if (!$this->filled('entidad_id')) {
                $validator->errors()->add(
                    'entidad_id',
                    'Indique el registro con el que desea previsualizar el documento.'
                );

                return;
            }

            if (!$entidadType::find($this->integer('entidad_id'))) {
                $validator->errors()->add('entidad_id', 'El registro indicado no existe.');
            }
        });
    }

    /**
     * Mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entidad_id.integer' => 'El registro asociado debe ser un identificador válido.',
        ];
    }

    /**
     * Nombres legibles de los campos para los mensajes de error.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'entidad_id' => 'registro asociado',
        ];
    }
}
