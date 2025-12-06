<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Services\Financiero\LpPrecioProductoService;
use App\Traits\Financiero\HasListaPrecioStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request UpdateLpListaPrecioRequest
 *
 * Valida los datos para actualizar una lista de precios existente.
 * Todos los campos son opcionales (sometimes) para permitir actualizaciones parciales.
 * Incluye validación de solapamiento de vigencia al actualizar fechas o poblaciones.
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class UpdateLpListaPrecioRequest extends FormRequest
{
    use HasListaPrecioStatus;

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
     * Todas las reglas usan 'sometimes' para permitir actualizaciones parciales.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $listaPrecioId = $this->route('lp_lista_precio') ?? $this->route('lista_precio');

        return [
            'nombre' => 'sometimes|string|max:255',
            'codigo' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('lp_listas_precios', 'codigo')->ignore($listaPrecioId)
            ],
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => [
                'sometimes',
                'date',
                function ($attribute, $value, $fail) {
                    $fechaInicio = $this->input('fecha_inicio');
                    if ($fechaInicio && Carbon::parse($value)->lt(Carbon::parse($fechaInicio))) {
                        $fail('La fecha de fin debe ser mayor o igual a la fecha de inicio.');
                    }
                },
            ],
            'descripcion' => 'nullable|string',
            'status' => self::getStatusValidationRule(),
            'poblaciones' => 'sometimes|array|min:1',
            'poblaciones.*' => 'required|integer|exists:poblacions,id',
        ];
    }

    /**
     * Configurar validaciones adicionales después de las reglas básicas.
     * Valida que no existan solapamientos de vigencia para las poblaciones especificadas.
     *
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $listaPrecioId = $this->route('lp_lista_precio') ?? $this->route('lista_precio');
            $listaPrecio = is_object($listaPrecioId) ? $listaPrecioId : null;
            $listaPrecioId = is_object($listaPrecioId) ? $listaPrecio->id : $listaPrecioId;

            // Obtener fechas actuales o las nuevas si se están actualizando
            $fechaInicio = $this->filled('fecha_inicio')
                ? Carbon::parse($this->fecha_inicio)
                : ($listaPrecio ? Carbon::parse($listaPrecio->fecha_inicio) : null);

            $fechaFin = $this->filled('fecha_fin')
                ? Carbon::parse($this->fecha_fin)
                : ($listaPrecio ? Carbon::parse($listaPrecio->fecha_fin) : null);

            if ($fechaInicio && $fechaFin) {
                $poblaciones = $this->filled('poblaciones')
                    ? $this->poblaciones
                    : ($listaPrecio && $listaPrecio->relationLoaded('poblaciones')
                        ? $listaPrecio->poblaciones->pluck('id')->toArray()
                        : []);

                if (!empty($poblaciones)) {
                    $service = new LpPrecioProductoService();

                    foreach ($poblaciones as $poblacionId) {
                        $sinSolapamiento = $service->validarSolapamientoVigencia(
                            $poblacionId,
                            $fechaInicio,
                            $fechaFin,
                            $listaPrecioId
                        );

                        if (!$sinSolapamiento) {
                            $validator->errors()->add(
                                'poblaciones',
                                "Ya existe una lista de precios activa con vigencia solapada para la población ID {$poblacionId} en el período especificado."
                            );
                        }
                    }
                }
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
        $statusMessages = self::getStatusValidationMessages();

        return array_merge([
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede exceder 100 caracteres.',
            'codigo.unique' => 'El código de la lista de precios ya existe.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'poblaciones.array' => 'Las poblaciones deben ser un array.',
            'poblaciones.min' => 'Debe especificar al menos una población.',
            'poblaciones.*.required' => 'Cada población es obligatoria.',
            'poblaciones.*.integer' => 'Cada población debe ser un número entero.',
            'poblaciones.*.exists' => 'Una o más poblaciones especificadas no existen.',
        ], $statusMessages);
    }
}
