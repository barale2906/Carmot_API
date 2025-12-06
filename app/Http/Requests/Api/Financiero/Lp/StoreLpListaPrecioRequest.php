<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Services\Financiero\LpPrecioProductoService;
use App\Traits\Financiero\HasListaPrecioStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request StoreLpListaPrecioRequest
 *
 * Valida los datos para crear una nueva lista de precios.
 * Incluye validación de fechas, poblaciones, estados y solapamiento de vigencia.
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class StoreLpListaPrecioRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:100|unique:lp_listas_precios,codigo',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'descripcion' => 'nullable|string',
            'status' => self::getStatusValidationRule(),
            'poblaciones' => 'required|array|min:1',
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
            if ($this->filled('fecha_inicio') && $this->filled('fecha_fin') && $this->filled('poblaciones')) {
                $fechaInicio = Carbon::parse($this->fecha_inicio);
                $fechaFin = Carbon::parse($this->fecha_fin);
                $service = new LpPrecioProductoService();

                foreach ($this->poblaciones as $poblacionId) {
                    $sinSolapamiento = $service->validarSolapamientoVigencia(
                        $poblacionId,
                        $fechaInicio,
                        $fechaFin
                    );

                    if (!$sinSolapamiento) {
                        $validator->errors()->add(
                            'poblaciones',
                            "Ya existe una lista de precios activa con vigencia solapada para la población ID {$poblacionId} en el período especificado."
                        );
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
            'nombre.required' => 'El nombre de la lista de precios es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede exceder 100 caracteres.',
            'codigo.unique' => 'El código de la lista de precios ya existe.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser mayor o igual a la fecha de inicio.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'poblaciones.required' => 'Debe especificar al menos una población.',
            'poblaciones.array' => 'Las poblaciones deben ser un array.',
            'poblaciones.min' => 'Debe especificar al menos una población.',
            'poblaciones.*.required' => 'Cada población es obligatoria.',
            'poblaciones.*.integer' => 'Cada población debe ser un número entero.',
            'poblaciones.*.exists' => 'Una o más poblaciones especificadas no existen.',
        ], $statusMessages);
    }
}
