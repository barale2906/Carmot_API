<?php

namespace App\Services\Academico\Documentacion\Bloques;

use Illuminate\Database\Eloquent\Model;

/**
 * Contrato de los bloques de documento.
 *
 * Un bloque es una consulta del sistema que se imprime como tabla dentro de un
 * documento (sábana de notas, estado de cartera, listado de recibos). El bloque
 * declara qué columnas sabe traer; cuáles de ellas se imprimen y en qué orden lo
 * decide el administrador al diseñar cada versión de la plantilla.
 *
 * @package App\Services\Academico\Documentacion\Bloques
 */
interface DocBloqueContract
{
    /**
     * Columnas que el bloque puede entregar.
     *
     * @return array<string, array{label: string, type: string}>
     */
    public function columnas(): array;

    /**
     * Consulta las filas y el resumen del bloque para una entidad.
     *
     * @param Model $entidad Entidad origen del documento.
     * @return array{filas: array<int, array<string, mixed>>, resumen: array{label: string, valores: array<string, mixed>}|null}
     */
    public function datos(Model $entidad): array;
}
