<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocDocumento;
use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio DocGeneracionService
 *
 * Arma el contenido de un documento en el momento en que se pide, resolviendo la
 * plantilla aplicable, las variables y los bloques contra los datos actuales del
 * registro. Nada del contenido se almacena.
 *
 * Para los documentos que conforman la matrícula la plantilla aplicable es la que
 * estaba vigente en la fecha de esa matrícula, así que reimprimir un contrato
 * devuelve siempre las condiciones bajo las que se firmó, aunque hoy rija otra
 * versión. Para los demás se usa la plantilla vigente hoy.
 *
 * @package App\Services\Academico\Documentacion
 */
class DocGeneracionService
{
    /**
     * @param DocPlantillaVersionResolverService $versiones Resolución de la versión aplicable.
     * @param DocVariableResolverService         $variables Resolución y sustitución de variables.
     * @param DocBloqueRenderService             $bloques   Impresión de los bloques de consulta.
     */
    public function __construct(
        private DocPlantillaVersionResolverService $versiones,
        private DocVariableResolverService $variables,
        private DocBloqueRenderService $bloques
    ) {
    }

    /**
     * Carga el registro asociado a un tipo de documento.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @param int|null         $entidadId
     * @return Model|null
     */
    public function resolverEntidad(DocTipoDocumento $tipoDocumento, ?int $entidadId): ?Model
    {
        if (!$tipoDocumento->entidad_type || !$entidadId) {
            return null;
        }

        return $tipoDocumento->entidad_type::find($entidadId);
    }

    /**
     * Determina la versión de plantilla aplicable y la fecha con que se resolvió.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @param Model|null       $entidad
     * @return array{plantilla: DocPlantilla|null, fecha_referencia: Carbon|null}
     */
    public function resolverVigencia(DocTipoDocumento $tipoDocumento, ?Model $entidad): array
    {
        $fechaReferencia = $this->versiones->fechaReferencia($tipoDocumento, $entidad);

        return [
            'plantilla'        => $this->versiones->resolver($tipoDocumento, $fechaReferencia),
            'fecha_referencia' => $fechaReferencia,
        ];
    }

    /**
     * Arma el contenido del documento con los datos del registro.
     *
     * Primero sustituye las variables y después imprime los bloques, porque los
     * marcadores de bloque no son claves del catálogo de variables y el primer
     * paso los deja intactos.
     *
     * @param DocPlantilla $plantilla
     * @param Model|null   $entidad
     * @param User|null    $usuario
     * @return string HTML del documento, listo para mostrar o convertir a PDF.
     */
    public function renderizar(DocPlantilla $plantilla, ?Model $entidad, ?User $usuario): string
    {
        $plantilla->loadMissing('tipoDocumento');

        $valores = $this->variables->resolver(
            $plantilla->tipoDocumento->clavesHabilitadas(),
            $entidad,
            $this->contexto($plantilla->tipoDocumento, $usuario)
        );

        $contenido = $this->variables->renderizar($plantilla->contenido_html, $valores);

        return $this->bloques->renderizar($contenido, $plantilla, $entidad);
    }

    /**
     * Registra en la bitácora que se imprimió un documento.
     *
     * Guarda solo el rastro de la impresión, no su contenido: quién la hizo,
     * cuándo, de qué tipo, para qué registro y con qué versión de plantilla.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @param DocPlantilla     $plantilla
     * @param Model|null       $entidad
     * @param Carbon|null      $fechaReferencia
     * @param User|null        $usuario
     * @return DocDocumento
     */
    public function registrarEmision(
        DocTipoDocumento $tipoDocumento,
        DocPlantilla $plantilla,
        ?Model $entidad,
        ?Carbon $fechaReferencia,
        ?User $usuario
    ): DocDocumento {
        return DocDocumento::create([
            'tipo_documento_id' => $tipoDocumento->id,
            'plantilla_id'      => $plantilla->id,
            'entidad_type'      => $entidad ? get_class($entidad) : null,
            'entidad_id'        => $entidad?->getKey(),
            'origen'            => DocDocumento::ORIGEN_GENERADO,
            'fecha_referencia'  => $fechaReferencia,
            'generado_por'      => $usuario?->id,
        ]);
    }

    /**
     * Construye el contexto de las variables globales del documento.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @param User|null        $usuario
     * @return array<string, mixed>
     */
    private function contexto(DocTipoDocumento $tipoDocumento, ?User $usuario): array
    {
        $hoy = Carbon::today()->toDateString();

        return [
            'documento' => [
                'fecha'       => $hoy,
                'fecha_larga' => $hoy,
                'tipo'        => $tipoDocumento->nombre,
            ],
            'usuario' => [
                'nombre' => $usuario?->name,
            ],
        ];
    }
}
