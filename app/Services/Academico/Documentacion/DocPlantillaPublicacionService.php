<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicio DocPlantillaPublicacionService
 *
 * Gestiona la publicación de versiones de plantilla: al activar una versión
 * cierra la vigencia de la anterior para que las ventanas de fechas nunca se
 * solapen, condición necesaria para poder resolver sin ambigüedad qué
 * contenido aplicaba en una fecha dada.
 *
 * @package App\Services\Academico\Documentacion
 */
class DocPlantillaPublicacionService
{
    /**
     * Activa una versión de plantilla y cierra la vigencia de la anterior.
     *
     * @param DocPlantilla $plantilla
     * @param Carbon       $fechaInicio Inicio de vigencia de la nueva versión.
     * @return DocPlantilla
     */
    public function activar(DocPlantilla $plantilla, Carbon $fechaInicio): DocPlantilla
    {
        return DB::transaction(function () use ($plantilla, $fechaInicio) {
            $vigente = $this->versionActiva($plantilla);

            if ($vigente) {
                $vigente->update([
                    'fecha_fin' => $fechaInicio->copy()->subDay(),
                    'status'    => DocPlantilla::STATUS_INACTIVA,
                ]);
            }

            $plantilla->update([
                'fecha_inicio' => $fechaInicio,
                'fecha_fin'    => null,
                'status'       => DocPlantilla::STATUS_ACTIVA,
            ]);

            return $plantilla->fresh();
        });
    }

    /**
     * Inactiva una versión de plantilla cerrando su vigencia.
     *
     * Una versión que estuvo activa conserva su ventana histórica: se le
     * asigna fecha_fin para que los documentos generados en su momento sigan
     * resolviéndose contra ella.
     *
     * @param DocPlantilla $plantilla
     * @return DocPlantilla
     */
    public function inactivar(DocPlantilla $plantilla): DocPlantilla
    {
        $datos = ['status' => DocPlantilla::STATUS_INACTIVA];

        if ($plantilla->status === DocPlantilla::STATUS_ACTIVA && $plantilla->fecha_fin === null) {
            $datos['fecha_fin'] = Carbon::today();
        }

        $plantilla->update($datos);

        return $plantilla->fresh();
    }

    /**
     * Obtiene la versión activa del mismo tipo de documento, si existe.
     *
     * @param DocPlantilla $plantilla Versión que se va a activar.
     * @return DocPlantilla|null
     */
    public function versionActiva(DocPlantilla $plantilla): ?DocPlantilla
    {
        return DocPlantilla::where('tipo_documento_id', $plantilla->tipo_documento_id)
            ->where('id', '!=', $plantilla->id)
            ->activa()
            ->orderByDesc('fecha_inicio')
            ->first();
    }
}
