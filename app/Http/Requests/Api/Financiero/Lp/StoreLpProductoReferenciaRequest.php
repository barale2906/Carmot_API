<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Models\Financiero\Lp\LpProductoReferencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Request StoreLpProductoReferenciaRequest
 *
 * Valida los datos para vincular una entidad académica (curso o módulo)
 * a un producto LP. Garantiza que la combinación producto-referencia
 * no exista previamente y que la entidad académica exista.
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class StoreLpProductoReferenciaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     * La autorización se maneja mediante middleware y permisos.
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
            'referencia_id' => [
                'required',
                'integer',
            ],
            'referencia_tipo' => [
                'required',
                'string',
                Rule::in(LpProductoReferencia::tiposValidos()),
            ],
        ];
    }

    /**
     * Validaciones adicionales tras las reglas básicas.
     * Verifica que:
     * 1. La entidad académica exista (sin soft-deleted).
     * 2. El vínculo no sea duplicado.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $referenciaId   = $this->integer('referencia_id');
            $referenciaTipo = $this->string('referencia_tipo')->toString();
            $productoId     = $this->integer('lp_producto_id');

            // Verificar que la entidad académica existe
            $tabla  = $referenciaTipo === LpProductoReferencia::TIPO_CURSO ? 'cursos' : 'modulos';
            $existe = DB::table($tabla)
                ->whereNull('deleted_at')
                ->where('id', $referenciaId)
                ->exists();

            if (!$existe) {
                $validator->errors()->add(
                    'referencia_id',
                    "El {$referenciaTipo} con ID {$referenciaId} no existe o está eliminado."
                );
                return;
            }

            // Verificar que el vínculo no existe ya
            $duplicado = DB::table('lp_producto_referencias')
                ->where('lp_producto_id',  $productoId)
                ->where('referencia_id',   $referenciaId)
                ->where('referencia_tipo', $referenciaTipo)
                ->exists();

            if ($duplicado) {
                $validator->errors()->add(
                    'referencia_id',
                    "Este {$referenciaTipo} ya está vinculado al producto seleccionado."
                );
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
            'lp_producto_id.required' => 'El producto LP es obligatorio.',
            'lp_producto_id.integer'  => 'El ID del producto debe ser un número entero.',
            'lp_producto_id.exists'   => 'El producto LP seleccionado no existe.',
            'referencia_id.required'  => 'El ID de la referencia académica es obligatorio.',
            'referencia_id.integer'   => 'El ID de la referencia debe ser un número entero.',
            'referencia_tipo.required' => 'El tipo de referencia es obligatorio.',
            'referencia_tipo.in'       => 'El tipo de referencia debe ser "curso" o "modulo".',
        ];
    }
}
