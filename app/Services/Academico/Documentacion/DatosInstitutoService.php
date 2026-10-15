<?php

namespace App\Services\Academico\Documentacion;

use Illuminate\Database\Eloquent\Model;

/**
 * Servicio DatosInstitutoService
 *
 * Datos institucionales de un documento: nombre y NIT del instituto (config
 * documentacion.instituto) y la dirección y el teléfono de la **sede** del registro
 * (matrícula, cartera…). Decisión del 2026-10-14: cada documento muestra los datos de
 * contacto de su sede; los de INSTITUTO_* en el .env solo quedan como respaldo.
 *
 * @package App\Services\Academico\Documentacion
 */
class DatosInstitutoService
{
    /**
     * @param Model|null $entidad Registro del documento; se usa su relación `sede` si la tiene.
     * @return array{nombre: string, nit: string, direccion: string, telefono: string, sede: string|null}
     */
    public function paraEntidad(?Model $entidad): array
    {
        $datos = config('documentacion.instituto', []);
        $sede  = $entidad && method_exists($entidad, 'sede') ? $entidad->sede : null;

        return [
            'nombre'    => (string) ($datos['nombre'] ?? ''),
            'nit'       => (string) ($datos['nit'] ?? ''),
            'direccion' => (string) ($sede?->direccion ?: ($datos['direccion'] ?? '')),
            'telefono'  => (string) ($sede?->telefono ?: ($datos['telefono'] ?? '')),
            'sede'      => $sede?->nombre,
        ];
    }
}
