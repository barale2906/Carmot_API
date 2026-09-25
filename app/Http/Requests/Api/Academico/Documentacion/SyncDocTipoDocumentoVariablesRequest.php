<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Services\Academico\Documentacion\DocVariableResolverService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la selección de variables habilitadas para un tipo de documento.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class SyncDocTipoDocumentoVariablesRequest extends FormRequest
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
            'variables'   => 'present|array',
            'variables.*' => 'string|max:150',
        ];
    }

    /**
     * Verifica que cada variable exista en el catálogo de la entidad del tipo.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var DocTipoDocumento|null $tipoDocumento */
            $tipoDocumento = $this->route('tipo_documento');
            $variables     = $this->input('variables', []);

            if (!$tipoDocumento || empty($variables)) {
                return;
            }

            $validas = app(DocVariableResolverService::class)->clavesValidas($tipoDocumento->entidad_type);

            foreach (array_diff($variables, $validas) as $invalida) {
                $validator->errors()->add(
                    'variables',
                    "La variable \"{$invalida}\" no existe en el catálogo de la entidad de este tipo de documento."
                );
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
            'variables.present' => 'Debe enviar la lista de variables habilitadas.',
            'variables.array'   => 'Las variables habilitadas deben enviarse como una lista.',
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
            'variables' => 'variables habilitadas',
        ];
    }
}
