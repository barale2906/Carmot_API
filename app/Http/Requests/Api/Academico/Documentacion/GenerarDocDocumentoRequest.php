<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocTipoDocumento;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la generación de un documento a partir de su tipo y entidad.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class GenerarDocDocumentoRequest extends FormRequest
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
            'tipo_documento_id' => 'required|integer|exists:doc_tipos_documento,id',
            'entidad_id'        => 'nullable|integer',
        ];
    }

    /**
     * Verifica que el tipo esté activo y que la entidad exigida exista.
     *
     * Los tipos asociados a una entidad (matrícula, cartera, estudiante) solo
     * pueden generarse indicando el registro concreto del que se toman los datos.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tipoDocumento = DocTipoDocumento::find($this->input('tipo_documento_id'));

            if (!$tipoDocumento) {
                return;
            }

            if ($tipoDocumento->status !== 1) {
                $validator->errors()->add('tipo_documento_id', 'El tipo de documento está inactivo.');

                return;
            }

            if (!$tipoDocumento->entidad_type) {
                return;
            }

            if (!$this->filled('entidad_id')) {
                $validator->errors()->add(
                    'entidad_id',
                    'Este tipo de documento requiere indicar el registro asociado.'
                );

                return;
            }

            if (!$tipoDocumento->entidad_type::find($this->integer('entidad_id'))) {
                $validator->errors()->add('entidad_id', 'El registro asociado no existe.');
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
            'tipo_documento_id.required' => 'El tipo de documento es obligatorio.',
            'tipo_documento_id.exists'   => 'El tipo de documento seleccionado no existe.',
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
            'tipo_documento_id' => 'tipo de documento',
            'entidad_id'        => 'registro asociado',
        ];
    }
}
