<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use App\Services\Academico\Documentacion\DocBloqueRenderService;
use App\Services\Academico\Documentacion\DocVariableResolverService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la clonación de una versión de plantilla en una nueva versión borrador.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class CloneDocPlantillaRequest extends FormRequest
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
            'nombre'         => 'required|string|max:255',
            'contenido_html' => 'nullable|string',
        ];
    }

    /**
     * Verifica que el contenido enviado solo use variables y bloques habilitados.
     *
     * Si no se envía contenido se copia el de la versión origen, que ya fue
     * validado al crearse.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var DocPlantilla|null $plantilla */
            $plantilla = $this->route('plantilla');

            if (!$plantilla || !$this->filled('contenido_html')) {
                return;
            }

            $noHabilitadas = app(DocVariableResolverService::class)
                ->variablesNoHabilitadas($this->input('contenido_html'), $plantilla->tipoDocumento);

            foreach ($noHabilitadas as $variable) {
                $validator->errors()->add(
                    'contenido_html',
                    "La variable \"{$variable}\" no está habilitada para este tipo de documento."
                );
            }

            $bloques = app(DocBloqueRenderService::class);
            $validos = $bloques->clavesValidas($plantilla->tipoDocumento->entidad_type);

            foreach (array_diff($bloques->clavesUsadas($this->input('contenido_html')), $validos) as $bloque) {
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
            'nombre.required' => 'El nombre de la nueva versión es obligatorio.',
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
