<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio DocPlantillaVersionResolverService
 *
 * Determina qué versión de plantilla aplica a un documento según la
 * configuración de su tipo.
 *
 * Los tipos que conforman la matrícula (contrato, pagaré, hoja de matrícula)
 * resuelven la versión cuya vigencia contiene la fecha de esa matrícula, de modo
 * que reimprimirlos devuelve las condiciones que aplicaban entonces. Los demás
 * (sábanas de notas, constancias, cartas) usan la versión vigente hoy.
 *
 * @package App\Services\Academico\Documentacion
 */
class DocPlantillaVersionResolverService
{
    /**
     * Resuelve la versión de plantilla aplicable a un tipo de documento.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @param Carbon|null      $fechaReferencia Fecha a la que se resuelve la vigencia; null = hoy.
     * @return DocPlantilla|null
     */
    public function resolver(DocTipoDocumento $tipoDocumento, ?Carbon $fechaReferencia = null): ?DocPlantilla
    {
        $fecha = ($tipoDocumento->conforma_matricula && $fechaReferencia)
            ? $fechaReferencia
            : Carbon::today();

        return $tipoDocumento->plantillas()
            ->vigentesEn($fecha)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Obtiene la fecha de referencia de una entidad para un tipo de documento.
     *
     * Devuelve null cuando el tipo no conforma la matrícula. Si la conforma pero no
     * tiene configurado un campo de fecha —o la entidad no lo trae— se usa la fecha
     * en que se imprime el documento.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @param Model|null       $entidad
     * @return Carbon|null
     */
    public function fechaReferencia(DocTipoDocumento $tipoDocumento, ?Model $entidad): ?Carbon
    {
        if (!$tipoDocumento->conforma_matricula) {
            return null;
        }

        if (!$tipoDocumento->campo_fecha_referencia || !$entidad) {
            return Carbon::today();
        }

        $valor = data_get($entidad, $tipoDocumento->campo_fecha_referencia);

        return $valor ? Carbon::parse($valor) : Carbon::today();
    }
}
