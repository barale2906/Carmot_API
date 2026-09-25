<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocTipoDocumento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Servicio DocVariableResolverService
 *
 * Resuelve las variables del catálogo (`config/documentacion.php`) contra una
 * entidad concreta y sustituye los marcadores `{{variable}}` del contenido de
 * una plantilla por sus valores ya formateados.
 *
 * El contenido de la plantilla se trata siempre como dato, nunca como plantilla
 * Blade: la sustitución es textual y cada valor se escapa con `e()`, de modo que
 * ni el contenido ni los datos del estudiante puedan inyectar HTML o PHP.
 *
 * @package App\Services\Academico\Documentacion
 */
class DocVariableResolverService
{
    /** Expresión que reconoce los marcadores de variable en el contenido. */
    private const PATRON_VARIABLE = '/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/';

    /** Prefijo que distingue los marcadores de bloque de las variables. */
    public const PREFIJO_BLOQUE = 'bloque.';

    /** Nombres de los meses para el formato de fecha en texto. */
    private const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    /**
     * @param NumeroALetrasService $numeroALetras Conversor de valores a letras.
     */
    public function __construct(private NumeroALetrasService $numeroALetras)
    {
    }

    /**
     * Catálogo de variables disponibles para un tipo de entidad.
     *
     * Incluye las variables propias de la entidad y las globales, que aplican
     * a cualquier documento.
     *
     * @param string|null $entidadType Clase Eloquent de la entidad, o null.
     * @return array<int, array{clave: string, label: string, type: string, grupo: string}>
     */
    public function catalogo(?string $entidadType): array
    {
        $catalogo = [];

        if ($entidadType) {
            foreach (config("documentacion.variables.{$entidadType}", []) as $clave => $definicion) {
                $catalogo[] = [
                    'clave' => $clave,
                    'label' => $definicion['label'],
                    'type'  => $definicion['type'],
                    'grupo' => 'entidad',
                ];
            }
        }

        foreach (config('documentacion.variables_globales', []) as $clave => $definicion) {
            $catalogo[] = [
                'clave' => $clave,
                'label' => $definicion['label'],
                'type'  => $definicion['type'],
                'grupo' => 'global',
            ];
        }

        return $catalogo;
    }

    /**
     * Claves válidas del catálogo para un tipo de entidad.
     *
     * @param string|null $entidadType
     * @return array<int, string>
     */
    public function clavesValidas(?string $entidadType): array
    {
        return array_column($this->catalogo($entidadType), 'clave');
    }

    /**
     * Resuelve y formatea los valores de las variables indicadas.
     *
     * Las variables globales se resuelven desde el contexto de generación y la
     * configuración del instituto; las demás se leen de la entidad siguiendo la
     * ruta de la clave (soporta relaciones y accessors con notación de punto).
     *
     * @param array<int, string> $claves    Claves del catálogo a resolver.
     * @param Model|null         $entidad   Entidad origen de los datos.
     * @param array<string, mixed> $contexto Contexto de generación (documento, usuario).
     * @return array<string, string> Valores formateados indexados por clave.
     */
    public function resolver(array $claves, ?Model $entidad, array $contexto = []): array
    {
        $entidadType = $entidad ? get_class($entidad) : null;
        $globales    = config('documentacion.variables_globales', []);
        $porEntidad  = $entidadType ? config("documentacion.variables.{$entidadType}", []) : [];
        $contexto    = array_replace_recursive(
            ['instituto' => config('documentacion.instituto', [])],
            $contexto
        );

        $valores = [];

        foreach ($claves as $clave) {
            if (isset($globales[$clave])) {
                $valores[$clave] = $this->formatear(
                    Arr::get($contexto, $globales[$clave]['origen'] ?? $clave),
                    $globales[$clave]['type']
                );

                continue;
            }

            if (isset($porEntidad[$clave]) && $entidad) {
                $valores[$clave] = $this->formatear(
                    data_get($entidad, $porEntidad[$clave]['origen'] ?? $clave),
                    $porEntidad[$clave]['type']
                );
            }
        }

        return $valores;
    }

    /**
     * Sustituye los marcadores `{{variable}}` del contenido por sus valores.
     *
     * Cada valor se escapa antes de insertarse. Los marcadores sin valor
     * resuelto se dejan intactos, para que un error de digitación sea visible
     * en el documento en vez de desaparecer silenciosamente.
     *
     * @param string                $contenidoHtml Contenido de la plantilla.
     * @param array<string, string> $valores       Valores ya formateados.
     * @return string
     */
    public function renderizar(string $contenidoHtml, array $valores): string
    {
        return preg_replace_callback(
            self::PATRON_VARIABLE,
            fn (array $coincidencia) => array_key_exists($coincidencia[1], $valores)
                ? e($valores[$coincidencia[1]])
                : $coincidencia[0],
            $contenidoHtml
        );
    }

    /**
     * Claves de variable usadas dentro de un contenido.
     *
     * @param string $contenidoHtml
     * @return array<int, string>
     */
    public function clavesUsadas(string $contenidoHtml): array
    {
        preg_match_all(self::PATRON_VARIABLE, $contenidoHtml, $coincidencias);

        return array_values(array_unique($coincidencias[1]));
    }

    /**
     * Variables usadas en un contenido que no están habilitadas para el tipo.
     *
     * Garantiza que una plantilla solo pueda insertar las variables que se
     * eligieron para ese tipo de documento. Los marcadores de bloque se excluyen:
     * se validan contra el catálogo de bloques, no contra el de variables.
     *
     * @param string           $contenidoHtml
     * @param DocTipoDocumento $tipoDocumento
     * @return array<int, string>
     */
    public function variablesNoHabilitadas(string $contenidoHtml, DocTipoDocumento $tipoDocumento): array
    {
        $usadas = array_filter(
            $this->clavesUsadas($contenidoHtml),
            fn (string $clave) => !str_starts_with($clave, self::PREFIJO_BLOQUE)
        );

        return array_values(array_diff($usadas, $tipoDocumento->clavesHabilitadas()));
    }

    /**
     * Aplica el formato correspondiente al tipo de la variable.
     *
     * Lo usan también las celdas de los bloques, para que una columna de dinero o
     * de fecha se vea igual que la variable equivalente.
     *
     * @param mixed  $valor
     * @param string $tipo
     * @return string
     */
    public function formatear(mixed $valor, string $tipo): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return match ($tipo) {
            'integer'      => (string) (int) $valor,
            'decimal'      => $this->numero((float) $valor),
            'money'        => '$ ' . $this->numero((float) $valor),
            'money_letras' => $this->numeroALetras->convertir((float) $valor),
            'date'         => Carbon::parse($valor)->format('d/m/Y'),
            'date_larga'   => $this->fechaLarga(Carbon::parse($valor)),
            'datetime'     => Carbon::parse($valor)->format('d/m/Y H:i'),
            'boolean'      => $valor ? 'Sí' : 'No',
            default        => (string) $valor,
        };
    }

    /**
     * Formatea un número con separador de miles, omitiendo los decimales en cero.
     *
     * @param float $valor
     * @return string
     */
    private function numero(float $valor): string
    {
        return fmod($valor, 1.0) === 0.0
            ? number_format($valor, 0, ',', '.')
            : number_format($valor, 2, ',', '.');
    }

    /**
     * Formatea una fecha en texto largo (24 de septiembre de 2026).
     *
     * @param Carbon $fecha
     * @return string
     */
    private function fechaLarga(Carbon $fecha): string
    {
        return $fecha->day . ' de ' . self::MESES[$fecha->month] . ' de ' . $fecha->year;
    }
}
