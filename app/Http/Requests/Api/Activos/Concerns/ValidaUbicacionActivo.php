<?php

namespace App\Http\Requests\Api\Activos\Concerns;

use App\Models\Configuracion\Area;

/**
 * Validaciones de ubicación compartidas por el registro y el traslado de activos:
 * el usuario debe tener asignada la sede y el área debe pertenecer a esa sede.
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
}
