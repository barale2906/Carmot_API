<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocPlantillaBloque;
use App\Services\Academico\Documentacion\Bloques\DocBloqueContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio DocBloqueRenderService
 *
 * Sustituye los marcadores `{{ bloque.clave }}` del contenido por la tabla que
 * corresponde, consultada contra la entidad del documento.
 *
 * Las columnas, sus títulos y la fila de resumen los define el administrador en
 * cada versión de la plantilla; la tabla se pinta siempre con el mismo parcial,
 * así que agregar un bloque no implica mantener una vista nueva.
 *
 * @package App\Services\Academico\Documentacion
 */
class DocBloqueRenderService
{
    /** Vista que pinta la tabla de cualquier bloque. */
    private const VISTA_TABLA = 'pdf.bloques.tabla';

    /**
     * @param DocVariableResolverService $variables Formateo de las celdas por tipo.
     */
    public function __construct(private DocVariableResolverService $variables)
    {
    }

    /**
     * Catálogo de bloques disponibles para un tipo de entidad, con sus columnas.
     *
     * @param string|null $entidadType
     * @return array<int, array<string, mixed>>
     */
    public function catalogo(?string $entidadType): array
    {
        if (!$entidadType) {
            return [];
        }

        $catalogo = [];

        foreach (config("documentacion.bloques.{$entidadType}", []) as $clave => $definicion) {
            $catalogo[] = [
                'clave'       => $clave,
                'marcador'    => '{{ ' . DocVariableResolverService::PREFIJO_BLOQUE . $clave . ' }}',
                'label'       => $definicion['label'],
                'descripcion' => $definicion['descripcion'] ?? null,
                'columnas'    => $this->columnasDe($clave, $entidadType),
            ];
        }

        return $catalogo;
    }

    /**
     * Claves de bloque válidas para un tipo de entidad.
     *
     * @param string|null $entidadType
     * @return array<int, string>
     */
    public function clavesValidas(?string $entidadType): array
    {
        return array_keys(config("documentacion.bloques.{$entidadType}", []));
    }

    /**
     * Columnas que declara un bloque del catálogo.
     *
     * @param string      $clave
     * @param string|null $entidadType
     * @return array<int, array{clave: string, label: string, type: string}>
     */
    public function columnasDe(string $clave, ?string $entidadType): array
    {
        $bloque = $this->instanciar($clave, $entidadType);

        if (!$bloque) {
            return [];
        }

        $columnas = [];

        foreach ($bloque->columnas() as $columna => $definicion) {
            $columnas[] = [
                'clave' => $columna,
                'label' => $definicion['label'],
                'type'  => $definicion['type'],
            ];
        }

        return $columnas;
    }

    /**
     * Claves de bloque usadas dentro de un contenido.
     *
     * @param string $contenidoHtml
     * @return array<int, string>
     */
    public function clavesUsadas(string $contenidoHtml): array
    {
        $prefijo = DocVariableResolverService::PREFIJO_BLOQUE;

        $usadas = array_filter(
            $this->variables->clavesUsadas($contenidoHtml),
            fn (string $clave) => str_starts_with($clave, $prefijo)
        );

        return array_values(array_map(
            fn (string $clave) => substr($clave, strlen($prefijo)),
            $usadas
        ));
    }

    /**
     * Sustituye los marcadores de bloque del contenido por sus tablas.
     *
     * Un bloque usado en el contenido sin configuración guardada se imprime con
     * todas sus columnas, para que el documento nunca salga vacío por falta de
     * configuración.
     *
     * @param string       $contenidoHtml
     * @param DocPlantilla $plantilla
     * @param Model|null   $entidad
     * @return string
     */
    public function renderizar(string $contenidoHtml, DocPlantilla $plantilla, ?Model $entidad): string
    {
        $entidadType = $entidad ? get_class($entidad) : null;
        $configs     = $plantilla->bloques()->get()->keyBy('bloque_key');
        $contenido   = $contenidoHtml;

        foreach ($this->clavesUsadas($contenidoHtml) as $clave) {
            $marcador = DocVariableResolverService::PREFIJO_BLOQUE . $clave;
            $tabla    = $entidad
                ? $this->tabla($clave, $entidadType, $entidad, $configs->get($clave))
                : '';

            $contenido = preg_replace_callback(
                '/\{\{\s*' . preg_quote($marcador, '/') . '\s*\}\}/',
                fn () => $tabla,
                $contenido
            );
        }

        return $contenido;
    }

    /**
     * Construye el HTML de la tabla de un bloque.
     *
     * @param string                  $clave
     * @param string|null             $entidadType
     * @param Model                   $entidad
     * @param DocPlantillaBloque|null $config
     * @return string
     */
    private function tabla(string $clave, ?string $entidadType, Model $entidad, ?DocPlantillaBloque $config): string
    {
        $bloque = $this->instanciar($clave, $entidadType);

        if (!$bloque) {
            return '';
        }

        $declaradas = $bloque->columnas();
        $elegidas   = $config?->columnas ?: array_keys($declaradas);
        $titulos    = $config?->titulos ?? [];

        $columnas = [];

        foreach ($elegidas as $columna) {
            if (!isset($declaradas[$columna])) {
                continue;
            }

            $columnas[$columna] = [
                'label' => $titulos[$columna] ?? $declaradas[$columna]['label'],
                'type'  => $declaradas[$columna]['type'],
            ];
        }

        if (empty($columnas)) {
            return '';
        }

        $datos = $bloque->datos($entidad);

        return view(self::VISTA_TABLA, [
            'columnas' => $columnas,
            'filas'    => $this->formatearFilas($datos['filas'], $columnas),
            'resumen'  => ($config === null || $config->mostrar_resumen)
                ? $this->formatearResumen($datos['resumen'] ?? null, $columnas)
                : null,
        ])->render();
    }

    /**
     * Formatea cada celda según el tipo de su columna.
     *
     * @param array<int, array<string, mixed>>                     $filas
     * @param array<string, array{label: string, type: string}>    $columnas
     * @return array<int, array<string, string>>
     */
    private function formatearFilas(array $filas, array $columnas): array
    {
        $formateadas = [];

        foreach ($filas as $fila) {
            $celdas = [];

            foreach ($columnas as $columna => $definicion) {
                $celdas[$columna] = $this->variables->formatear($fila[$columna] ?? null, $definicion['type']);
            }

            $formateadas[] = $celdas;
        }

        return $formateadas;
    }

    /**
     * Formatea la fila de resumen del bloque.
     *
     * @param array{label: string, valores: array<string, mixed>}|null $resumen
     * @param array<string, array{label: string, type: string}>        $columnas
     * @return array{label: string, valores: array<string, string>}|null
     */
    private function formatearResumen(?array $resumen, array $columnas): ?array
    {
        if (!$resumen) {
            return null;
        }

        $valores = [];

        foreach ($columnas as $columna => $definicion) {
            $valores[$columna] = array_key_exists($columna, $resumen['valores'])
                ? $this->variables->formatear($resumen['valores'][$columna], $definicion['type'])
                : '';
        }

        return ['label' => $resumen['label'], 'valores' => $valores];
    }

    /**
     * Instancia el bloque registrado en el catálogo.
     *
     * @param string      $clave
     * @param string|null $entidadType
     * @return DocBloqueContract|null
     */
    private function instanciar(string $clave, ?string $entidadType): ?DocBloqueContract
    {
        $clase = config("documentacion.bloques.{$entidadType}.{$clave}.clase");

        return $clase ? app($clase) : null;
    }
}
