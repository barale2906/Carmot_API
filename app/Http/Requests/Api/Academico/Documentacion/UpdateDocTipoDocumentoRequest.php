<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Services\Academico\Documentacion\DocVariableResolverService;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualización de un tipo de documento.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class UpdateDocTipoDocumentoRequest extends FormRequest
{
    use HasActiveStatus;
    use HasActiveStatusValidation;

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
        $tipoDocumento = $this->route('tipo_documento');

        return [
            'codigo'                 => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('doc_tipos_documento', 'codigo')->ignore($tipoDocumento?->id),
            ],
            'nombre'                 => 'sometimes|string|max:255',
            'descripcion'            => 'nullable|string',
            'entidad_type'           => ['nullable', 'string', Rule::in(array_keys(config('documentacion.entidades', [])))],
            'se_ata_fecha'           => 'sometimes|boolean',
            'campo_fecha_referencia' => 'nullable|string|max:100',
            'prefijo_numero'         => [
                'sometimes',
                'string',
                'max:10',
                'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('doc_tipos_documento', 'prefijo_numero')->ignore($tipoDocumento?->id),
            ],
            'status'                 => self::getStatusValidationRule(),
        ];
    }

    /**
     * Valida el campo de fecha contra la entidad efectiva del tipo de documento.
     *
     * La entidad efectiva es la enviada en la petición o, si no se envía, la que
     * ya tiene registrada el tipo de documento. Si la entidad cambia, las
     * variables habilitadas que dejen de existir en el nuevo catálogo se
     * reportan como error para evitar plantillas con variables huérfanas.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var DocTipoDocumento|null $tipoDocumento */
            $tipoDocumento = $this->route('tipo_documento');

            $entidadType = $this->has('entidad_type')
                ? $this->input('entidad_type')
                : $tipoDocumento?->entidad_type;

            if ($this->filled('campo_fecha_referencia')) {
                $camposFecha = config("documentacion.entidades.{$entidadType}.campos_fecha", []);

                if (!in_array($this->input('campo_fecha_referencia'), $camposFecha, true)) {
                    $validator->errors()->add(
                        'campo_fecha_referencia',
                        'El campo de fecha de referencia no es válido para la entidad seleccionada.'
                    );
                }
            }

            if ($this->has('entidad_type') && $tipoDocumento && $entidadType !== $tipoDocumento->entidad_type) {
                $validas     = app(DocVariableResolverService::class)->clavesValidas($entidadType);
                $habilitadas = $tipoDocumento->clavesHabilitadas();

                if (array_diff($habilitadas, $validas)) {
                    $validator->errors()->add(
                        'entidad_type',
                        'No se puede cambiar la entidad: hay variables habilitadas que no existen en el catálogo de la nueva entidad.'
                    );
                }
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
        return array_merge([
            'codigo.unique'         => 'Ya existe un tipo de documento con este código.',
            'prefijo_numero.unique' => 'Ya existe un tipo de documento con este prefijo de numeración.',
            'entidad_type.in'      => 'La entidad seleccionada no está disponible para documentos.',
            'prefijo_numero.regex' => 'El prefijo de numeración solo admite letras, números y guiones.',
        ], self::getStatusValidationMessages());
    }

    /**
     * Nombres legibles de los campos para los mensajes de error.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo'                 => 'código',
            'nombre'                 => 'nombre',
            'descripcion'            => 'descripción',
            'entidad_type'           => 'entidad asociada',
            'se_ata_fecha'           => 'atado a fecha',
            'campo_fecha_referencia' => 'campo de fecha de referencia',
            'prefijo_numero'         => 'prefijo de numeración',
        ];
    }
}
