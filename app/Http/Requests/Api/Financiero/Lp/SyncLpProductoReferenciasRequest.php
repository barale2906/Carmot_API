<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Models\Financiero\Lp\LpProductoReferencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Request SyncLpProductoReferenciasRequest
 *
 * Valida los datos para la sincronización masiva (reemplazo completo) de
 * referencias académicas de un producto LP.
 *
 * El array "referencias" reemplazará TODAS las referencias existentes del
 * producto. Para desvincular todo, enviar "referencias": [].
 *
 * Payload esperado:
 * {
 *   "lp_producto_id": 1,
 *   "referencias": [
 *     { "referencia_id": 3, "referencia_tipo": "curso" },
 *     { "referencia_id": 7, "referencia_tipo": "modulo" }
 *   ]
 * }
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class SyncLpProductoReferenciasRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lp_producto_id' => [
                'required',
                'integer',
                'exists:lp_productos,id',
            ],
            'referencias' => [
                'present',
                'array',
            ],
            'referencias.*.referencia_id' => [
                'required',
                'integer',
            ],
            'referencias.*.referencia_tipo' => [
                'required',
                'string',
                Rule::in(LpProductoReferencia::tiposValidos()),
            ],
        ];
    }

    /**
     * Validaciones adicionales tras las reglas básicas.
     * Verifica que cada entidad académica del array exista y que no
     * haya duplicados dentro del propio array enviado.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any() || empty($this->referencias)) {
                return;
            }

            $vistas = [];

            foreach ($this->referencias as $index => $ref) {
                $referenciaId   = (int) ($ref['referencia_id']   ?? 0);
                $referenciaTipo = $ref['referencia_tipo'] ?? '';

                if (!$referenciaId || !$referenciaTipo) {
                    continue;
                }

                // Verificar existencia en la tabla correspondiente
                $tabla  = $referenciaTipo === LpProductoReferencia::TIPO_CURSO ? 'cursos' : 'modulos';
                $existe = DB::table($tabla)
                    ->whereNull('deleted_at')
                    ->where('id', $referenciaId)
                    ->exists();

                if (!$existe) {
                    $validator->errors()->add(
                        "referencias.{$index}.referencia_id",
                        "El {$referenciaTipo} con ID {$referenciaId} no existe o está eliminado."
                    );
                }

                // Detectar duplicados dentro del array enviado
                $clave = "{$referenciaTipo}:{$referenciaId}";

                if (in_array($clave, $vistas, true)) {
                    $validator->errors()->add(
                        "referencias.{$index}.referencia_id",
                        "El {$referenciaTipo} con ID {$referenciaId} está duplicado en el listado."
                    );
                }

                $vistas[] = $clave;
            }
        });
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lp_producto_id.required'             => 'El producto LP es obligatorio.',
            'lp_producto_id.integer'              => 'El ID del producto debe ser un número entero.',
            'lp_producto_id.exists'               => 'El producto LP seleccionado no existe.',
            'referencias.present'                 => 'El campo referencias es obligatorio (puede ser un array vacío).',
            'referencias.array'                   => 'El campo referencias debe ser un array.',
            'referencias.*.referencia_id.required' => 'Cada referencia debe incluir un referencia_id.',
            'referencias.*.referencia_id.integer'  => 'El referencia_id debe ser un número entero.',
            'referencias.*.referencia_tipo.required' => 'Cada referencia debe incluir un referencia_tipo.',
            'referencias.*.referencia_tipo.in'       => 'El referencia_tipo debe ser "curso" o "modulo".',
        ];
    }
}
