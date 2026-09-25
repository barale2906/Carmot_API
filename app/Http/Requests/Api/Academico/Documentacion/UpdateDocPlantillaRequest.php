<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use App\Services\Academico\Documentacion\DocBloqueRenderService;
use App\Services\Academico\Documentacion\DocVariableResolverService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la actualización de una versión de plantilla de documento.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class UpdateDocPlantillaRequest extends FormRequest
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
            'nombre'         => 'sometimes|string|max:255',
            'contenido_html' => 'sometimes|string',
        ];
    }

    /**
     * Verifica que el contenido solo use variables y bloques habilitados para el tipo.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var DocPlantilla|null $plantilla */
            $plantilla = $this->route('plantilla');

            if (!$plantilla || !$this->has('contenido_html') || $validator->errors()->isNotEmpty()) {
                return;
            }

            $noHabilitadas = app(DocVariableResolverService::class)
                ->variablesNoHabilitadas($this->input('contenido_html', ''), $plantilla->tipoDocumento);

            foreach ($noHabilitadas as $variable) {
                $validator->errors()->add(
                    'contenido_html',
                    "La variable \"{$variable}\" no está habilitada para este tipo de documento."
                );
            }

            $bloques = app(DocBloqueRenderService::class);
            $validos = $bloques->clavesValidas($plantilla->tipoDocumento->entidad_type);

            foreach (array_diff($bloques->clavesUsadas($this->input('contenido_html', '')), $validos) as $bloque) {
                $validator->errors()->add(
                    'contenido_html',
                    "El bloque \"{$bloque}\" no está disponible para la entidad de este tipo de documento."
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
            'nombre.string'         => 'El nombre de la versión debe ser texto.',
            'contenido_html.string' => 'El contenido de la plantilla debe ser texto.',
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
            'nombre'         => 'nombre',
            'contenido_html' => 'contenido',
        ];
    }
}
