<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use App\Services\ArchivoService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la carga del escaneado firmado de un documento de una matrícula.
 *
 * El documento se identifica por su tipo (contrato, pagaré) o, si no tiene tipo
 * —la hoja de matrícula u otro soporte—, por una descripción.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class SubirEscaneadoMatriculaRequest extends FormRequest
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
            'archivo'           => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:' . ArchivoService::MAX_KB],
            'tipo_documento_id' => ['nullable', 'integer', 'exists:doc_tipos_documento,id'],
            'descripcion'       => ['required_without:tipo_documento_id', 'nullable', 'string', 'max:150'],
        ];
    }

    /**
     * Verifica que el tipo indicado esté activo y corresponda a matrículas.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || !$this->filled('tipo_documento_id')) {
                return;
            }

            $tipoDocumento = DocTipoDocumento::find($this->integer('tipo_documento_id'));

            if ($tipoDocumento->status !== 1) {
                $validator->errors()->add('tipo_documento_id', 'El tipo de documento está inactivo.');

                return;
            }

            if ($tipoDocumento->entidad_type !== Matricula::class) {
                $validator->errors()->add('tipo_documento_id', 'El tipo de documento no corresponde a matrículas.');
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
            'archivo.required'                 => 'Selecciona el archivo escaneado.',
            'archivo.file'                     => 'El archivo no se cargó correctamente.',
            'archivo.mimes'                    => 'El archivo debe ser PDF, JPG o PNG.',
            'archivo.max'                      => 'El archivo no puede superar los 10 MB.',
            'archivo.uploaded'                 => 'No se recibió el archivo: puede superar el tamaño que permite el servidor.',
            'tipo_documento_id.exists'         => 'El tipo de documento seleccionado no existe.',
            'descripcion.required_without'     => 'Indica qué documento es el archivo.',
            'descripcion.max'                  => 'La descripción no puede tener más de 150 caracteres.',
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
            'archivo'           => 'archivo',
            'tipo_documento_id' => 'tipo de documento',
            'descripcion'       => 'descripción',
        ];
    }
}
