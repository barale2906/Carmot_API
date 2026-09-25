<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use App\Services\Academico\Documentacion\DocBloqueRenderService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la configuración de impresión de los bloques de una versión de plantilla.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class SyncDocPlantillaBloquesRequest extends FormRequest
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
            'bloques'                   => 'present|array',
            'bloques.*.bloque'          => 'required|string|max:100',
            'bloques.*.columnas'        => 'required|array|min:1',
            'bloques.*.columnas.*'      => 'required|string|max:100',
            'bloques.*.titulos'         => 'sometimes|array',
            'bloques.*.titulos.*'       => 'nullable|string|max:150',
            'bloques.*.mostrar_resumen' => 'sometimes|boolean',
        ];
    }

    /**
     * Verifica que cada bloque y cada columna existan en el catálogo.
     *
     * El catálogo depende de la entidad del tipo de documento: un bloque de
     * cartera no aplica a un documento que no se asocia a una matrícula.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var DocPlantilla|null $plantilla */
            $plantilla = $this->route('plantilla');

            if (!$plantilla || $validator->errors()->isNotEmpty()) {
                return;
            }

            $render      = app(DocBloqueRenderService::class);
            $entidadType = $plantilla->tipoDocumento->entidad_type;
            $validos     = $render->clavesValidas($entidadType);

            foreach ($this->input('bloques', []) as $indice => $config) {
                $clave = $config['bloque'] ?? null;

                if (!in_array($clave, $validos, true)) {
                    $validator->errors()->add(
                        "bloques.{$indice}.bloque",
                        "El bloque \"{$clave}\" no está disponible para la entidad de este tipo de documento."
                    );

                    continue;
                }

                $declaradas = array_column($render->columnasDe($clave, $entidadType), 'clave');

                foreach (array_diff($config['columnas'] ?? [], $declaradas) as $columna) {
                    $validator->errors()->add(
                        "bloques.{$indice}.columnas",
                        "La columna \"{$columna}\" no existe en el bloque \"{$clave}\"."
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
        return [
            'bloques.present'             => 'Debe enviar la lista de bloques.',
            'bloques.*.bloque.required'   => 'Cada configuración debe indicar el bloque.',
            'bloques.*.columnas.required' => 'Debe seleccionar al menos una columna del bloque.',
            'bloques.*.columnas.min'      => 'Debe seleccionar al menos una columna del bloque.',
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
            'bloques'              => 'bloques',
            'bloques.*.bloque'     => 'bloque',
            'bloques.*.columnas'   => 'columnas',
        ];
    }
}
