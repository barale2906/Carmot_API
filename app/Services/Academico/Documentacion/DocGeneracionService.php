<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocDocumento;
use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Servicio DocGeneracionService
 *
 * Genera documentos a partir de la versión de plantilla que corresponde y deja
 * congelado el resultado: el HTML con las variables ya resueltas y los valores
 * que se usaron quedan guardados en el documento. Volver a consultarlo devuelve
 * siempre lo mismo, aunque después cambie la plantilla o los datos de origen.
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
     * Carga la entidad asociada a un tipo de documento.
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
     * Genera un documento y guarda el contenido ya renderizado.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @param DocPlantilla     $plantilla       Versión aplicable, ya resuelta.
     * @param Model|null       $entidad         Entidad origen de los datos.
     * @param Carbon|null      $fechaReferencia Fecha con la que se resolvió la versión.
     * @param User|null        $usuario         Usuario que genera el documento.
     * @return DocDocumento
     */
    public function generar(
        DocTipoDocumento $tipoDocumento,
        DocPlantilla $plantilla,
        ?Model $entidad,
        ?Carbon $fechaReferencia,
        ?User $usuario
    ): DocDocumento {
        return DB::transaction(function () use ($tipoDocumento, $plantilla, $entidad, $fechaReferencia, $usuario) {
            $numero = $this->siguienteNumero($tipoDocumento);

            $valores = $this->variables->resolver(
                $tipoDocumento->clavesHabilitadas(),
                $entidad,
                $this->contexto($tipoDocumento, $numero, $usuario)
            );

            return DocDocumento::create([
                'tipo_documento_id'     => $tipoDocumento->id,
                'plantilla_id'          => $plantilla->id,
                'entidad_type'          => $entidad ? get_class($entidad) : null,
                'entidad_id'            => $entidad?->getKey(),
                'numero_documento'      => $numero,
                'origen'                => DocDocumento::ORIGEN_GENERADO,
                'contenido_renderizado' => $this->componer($plantilla, $entidad, $valores),
                'variables_aplicadas'   => $valores,
                'fecha_referencia'      => $fechaReferencia,
                'status'                => DocDocumento::STATUS_VIGENTE,
                'generado_por'          => $usuario?->id,
            ]);
        });
    }

    /**
     * Previsualiza una versión de plantilla sin emitir el documento.
     *
     * Resuelve variables y bloques contra una entidad real para que quien diseña
     * el documento vea el resultado antes de publicar la versión. No persiste nada.
     *
     * @param DocPlantilla $plantilla
     * @param Model|null   $entidad
     * @param User|null    $usuario
     * @return string
     */
    public function previsualizar(DocPlantilla $plantilla, ?Model $entidad, ?User $usuario): string
    {
        $plantilla->loadMissing('tipoDocumento');

        $valores = $this->variables->resolver(
            $plantilla->tipoDocumento->clavesHabilitadas(),
            $entidad,
            $this->contexto($plantilla->tipoDocumento, 'PREVISUALIZACIÓN', $usuario)
        );

        return $this->componer($plantilla, $entidad, $valores);
    }

    /**
     * Anula un documento conservando su contenido y su rastro.
     *
     * @param DocDocumento $documento
     * @param string       $motivo
     * @return DocDocumento
     */
    public function anular(DocDocumento $documento, string $motivo): DocDocumento
    {
        $documento->update([
            'status'           => DocDocumento::STATUS_ANULADO,
            'motivo_anulacion' => $motivo,
        ]);

        return $documento->fresh();
    }

    /**
     * Compone el contenido final: primero las variables, luego los bloques.
     *
     * Los marcadores de bloque no son claves del catálogo de variables, así que
     * el primer paso los deja intactos y el segundo los reemplaza por su tabla.
     *
     * @param DocPlantilla          $plantilla
     * @param Model|null            $entidad
     * @param array<string, string> $valores
     * @return string
     */
    private function componer(DocPlantilla $plantilla, ?Model $entidad, array $valores): string
    {
        $contenido = $this->variables->renderizar($plantilla->contenido_html, $valores);

        return $this->bloques->renderizar($contenido, $plantilla, $entidad);
    }

    /**
     * Construye el contexto de las variables globales del documento.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @param string           $numero
     * @param User|null        $usuario
     * @return array<string, mixed>
     */
    private function contexto(DocTipoDocumento $tipoDocumento, string $numero, ?User $usuario): array
    {
        $hoy = Carbon::today()->toDateString();

        return [
            'documento' => [
                'numero'      => $numero,
                'fecha'       => $hoy,
                'fecha_larga' => $hoy,
                'tipo'        => $tipoDocumento->nombre,
            ],
            'usuario' => [
                'nombre' => $usuario?->name,
            ],
        ];
    }

    /**
     * Calcula el siguiente consecutivo del tipo de documento para el año en curso.
     *
     * Se ejecuta dentro de la transacción de creación y bloquea las filas del
     * tipo para evitar números duplicados si se generan documentos en paralelo.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @return string
     */
    private function siguienteNumero(DocTipoDocumento $tipoDocumento): string
    {
        $prefijo = $tipoDocumento->prefijo_numero . '-' . Carbon::today()->year . '-';

        $ultimo = DocDocumento::withTrashed()
            ->where('tipo_documento_id', $tipoDocumento->id)
            ->where('numero_documento', 'like', $prefijo . '%')
            ->lockForUpdate()
            ->orderByDesc('numero_documento')
            ->value('numero_documento');

        $consecutivo = $ultimo
            ? ((int) substr($ultimo, strlen($prefijo))) + 1
            : 1;

        return $prefijo . str_pad((string) $consecutivo, 6, '0', STR_PAD_LEFT);
    }
}
