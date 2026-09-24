<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Models\Inventarios\InvEntregaKit;
use App\Models\Inventarios\InvKitComponente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación para la entrega parcial dirigida de componentes de un kit.
 *
 * El cajero elige qué componentes entrega ahora y, opcionalmente, en qué cantidad.
 * Los componentes no listados quedan intactos, pendientes para una entrega posterior.
 */
class EntregarComponentesKitRequest extends FormRequest
{
    /**
     * La autorización se resuelve con el middleware de permisos de la ruta.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'componentes'                         => ['required', 'array', 'min:1'],
            'componentes.*.kit_componente_id'     => ['required', 'integer', 'exists:inv_kit_componentes,id'],
            'componentes.*.producto_entregado_id' => ['nullable', 'integer', 'exists:inv_productos,id'],
            'componentes.*.cantidad'              => ['nullable', 'integer', 'min:1'],
            'forzar_parcial'                      => ['nullable', 'boolean'],
        ];
    }

    /**
     * Verifica que cada componente indicado pertenezca realmente al kit de esta entrega.
     *
     * Sin esta comprobación un componente de otro kit se ignoraría en silencio y el
     * cajero creería haber entregado algo que nunca se descargó de inventario.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $entregaKit = InvEntregaKit::with('pedidoItem')->find($this->route('entregaKitId'));

            if (! $entregaKit) {
                return;
            }

            $componentesDelKit = InvKitComponente::where('kit_id', $entregaKit->kit_producto_id)
                ->pluck('id')
                ->all();

            foreach ((array) $this->componentes as $indice => $componente) {
                $id = $componente['kit_componente_id'] ?? null;

                if ($id && ! in_array((int) $id, $componentesDelKit, true)) {
                    $v->errors()->add(
                        "componentes.{$indice}.kit_componente_id",
                        'El componente seleccionado no pertenece a este kit.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'componentes.required'                       => 'Debe indicar al menos un componente a entregar.',
            'componentes.min'                            => 'Debe indicar al menos un componente a entregar.',
            'componentes.*.kit_componente_id.required'   => 'El ID del componente es obligatorio.',
            'componentes.*.kit_componente_id.exists'     => 'El componente seleccionado no existe.',
            'componentes.*.producto_entregado_id.exists' => 'La variante seleccionada no existe.',
            'componentes.*.cantidad.min'                 => 'La cantidad a entregar debe ser al menos 1.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'componentes' => 'componentes del kit',
        ];
    }
}
