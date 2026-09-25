<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use App\Services\Academico\Documentacion\DocVariableResolverService;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la creación de un tipo de documento.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class StoreDocTipoDocumentoRequest extends FormRequest
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
        return [
            'codigo'                 => 'required|string|max:50|unique:doc_tipos_documento,codigo',
            'nombre'                 => 'required|string|max:255',
            'descripcion'            => 'nullable|string',
            'entidad_type'           => ['nullable', 'string', Rule::in(array_keys(config('documentacion.entidades', [])))],
            'se_ata_fecha'           => 'sometimes|boolean',
            'campo_fecha_referencia' => 'nullable|string|max:100',
            'prefijo_numero'         => 'required|string|max:10|regex:/^[A-Za-z0-9\-]+$/|unique:doc_tipos_documento,prefijo_numero',
            'status'                 => self::getStatusValidationRule(),
            'variables'              => 'sometimes|array',
            'variables.*'            => 'string|max:150',
        ];
    }

    /**
     * Valida el campo de fecha y las variables contra el catálogo de la entidad.
     *
     * El campo de fecha de referencia debe ser un atributo declarado en
     * `config/documentacion.php` para la entidad elegida, y cada variable
     * habilitada debe existir en el catálogo de esa misma entidad.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $entidadType = $this->input('entidad_type');

            if ($this->filled('campo_fecha_referencia')) {
                $camposFecha = config("documentacion.entidades.{$entidadType}.campos_fecha", []);

                if (!in_array($this->input('campo_fecha_referencia'), $camposFecha, true)) {
                    $validator->errors()->add(
                        'campo_fecha_referencia',
                        'El campo de fecha de referencia no es válido para la entidad seleccionada.'
                    );
                }
            }

            $variables = $this->input('variables', []);

            if (!empty($variables)) {
                $validas = app(DocVariableResolverService::class)->clavesValidas($entidadType);

                foreach (array_diff($variables, $validas) as $invalida) {
                    $validator->errors()->add(
                        'variables',
                        "La variable \"{$invalida}\" no existe en el catálogo de la entidad seleccionada."
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
            'codigo.required'         => 'El código del tipo de documento es obligatorio.',
            'codigo.unique'           => 'Ya existe un tipo de documento con este código.',
            'nombre.required'         => 'El nombre del tipo de documento es obligatorio.',
            'entidad_type.in'         => 'La entidad seleccionada no está disponible para documentos.',
            'prefijo_numero.required' => 'El prefijo de numeración es obligatorio.',
            'prefijo_numero.regex'    => 'El prefijo de numeración solo admite letras, números y guiones.',
            'prefijo_numero.unique'   => 'Ya existe un tipo de documento con este prefijo de numeración.',
            'variables.array'         => 'Las variables habilitadas deben enviarse como una lista.',
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
            'variables'              => 'variables habilitadas',
        ];
    }
}
