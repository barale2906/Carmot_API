<?php

namespace App\Http\Requests\Api\Activos\Concerns;

use App\Models\Configuracion\Area;
use App\Models\User;

/**
 * Validaciones compartidas por el registro, la edición y el traslado de activos:
 * el usuario debe tener asignada la sede, el área debe pertenecer a esa sede y el
 * responsable debe ser personal del instituto (no un estudiante).
 */
trait ValidaUbicacionActivo
{
    /**
     * @param \Illuminate\Validation\Validator $validator
     * @param int|null $sedeId
     * @param int|null $areaId
     * @return void
     */
    protected function validarUbicacion($validator, ?int $sedeId, ?int $areaId): void
    {
        if (! $sedeId) {
            return;
        }

        if (! $this->user()->tieneAccesoSede($sedeId)) {
            $validator->errors()->add('sede_id', 'No tiene asignada esta sede.');
        }

        $areaEnSede = ! $areaId || Area::whereKey($areaId)
            ->whereHas('sedes', fn ($q) => $q->where('sedes.id', $sedeId))
            ->exists();

        if (! $areaEnSede) {
            $validator->errors()->add('area_id', 'El área seleccionada no pertenece a la sede.');
        }
    }

    /**
     * Regla del responsable: debe ser personal, no un estudiante (F15).
     *
     * @return \Closure
     */
    protected function reglaResponsable(): \Closure
    {
        return function (string $atributo, $valor, \Closure $fallar) {
            if ($valor && ! User::personal()->whereKey($valor)->exists()) {
                $fallar('El responsable debe ser un usuario del personal, no un estudiante.');
            }
        };
    }
}
