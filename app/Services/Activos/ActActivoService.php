<?php

namespace App\Services\Activos;

use App\Models\Activos\ActActivo;
use App\Models\Activos\ActMovimiento;
use App\Services\ArchivoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Operaciones de un activo fijo que dejan rastro en su historial (act_movimientos):
 * el ingreso al inventario, los traslados entre sedes/áreas y los cambios de estado.
 * La ubicación y el estado solo cambian por aquí, nunca con la edición general.
 *
 * El traslado tiene dos pasos: se envía (queda pendiente y el activo sigue en su
 * ubicación de origen) y la sede de destino registra la recepción, que es cuando el
 * activo cambia de sede, área y, si se indicó, de responsable.
 */
class ActActivoService
{
    /**
     * Registra un activo nuevo junto con su movimiento de ingreso.
     *
     * @param array<string, mixed> $datos Datos validados del activo
     * @param int|null $usuarioId
     * @return ActActivo
     */
    public function registrar(array $datos, ?int $usuarioId): ActActivo
    {
        return DB::transaction(function () use ($datos, $usuarioId) {
            $activo = ActActivo::create($datos);

            $activo->movimientos()->create([
                'tipo'            => 'ingreso',
                'sede_destino_id' => $activo->sede_id,
                'area_destino_id' => $activo->area_id,
                'estado_nuevo'    => $activo->estado,
                'fecha'           => $activo->fecha_ingreso,
                'motivo'          => 'Ingreso al inventario de activos.',
                'usuario_id'      => $usuarioId,
            ]);

            return $activo;
        });
    }

    /**
     * Envía el activo a otra sede/área. Queda pendiente de recepción: la ubicación no cambia todavía.
     *
     * @param ActActivo $activo
     * @param array{sede_id: int, area_id: int|null, responsable_id?: int|null, fecha: string, motivo: string} $datos
     * @param int|null $usuarioId
     * @return ActMovimiento
     */
    public function trasladar(ActActivo $activo, array $datos, ?int $usuarioId): ActMovimiento
    {
        return $activo->movimientos()->create([
            'tipo'                   => ActMovimiento::TIPO_TRASLADO,
            'estado_traslado'        => ActMovimiento::TRASLADO_PENDIENTE,
            'sede_origen_id'         => $activo->sede_id,
            'area_origen_id'         => $activo->area_id,
            'sede_destino_id'        => $datos['sede_id'],
            'area_destino_id'        => $datos['area_id'] ?? null,
            'responsable_destino_id' => $datos['responsable_id'] ?? null,
            'fecha'                  => $datos['fecha'],
            'motivo'                 => $datos['motivo'],
            'usuario_id'             => $usuarioId,
        ]);
    }

    /**
     * Registra la recepción del traslado y ubica el activo en el destino. El responsable
     * queda, en este orden: el indicado al recibir, el propuesto al enviar o el que ya tenía.
     *
     * @param ActMovimiento $traslado Traslado pendiente
     * @param array{fecha_recepcion: string, observaciones_recepcion?: string|null, responsable_id?: int|null} $datos
     * @param int|null $usuarioId
     * @return ActMovimiento
     */
    public function recibirTraslado(ActMovimiento $traslado, array $datos, ?int $usuarioId): ActMovimiento
    {
        return DB::transaction(function () use ($traslado, $datos, $usuarioId) {
            $traslado->update([
                'estado_traslado'         => ActMovimiento::TRASLADO_RECIBIDO,
                'fecha_recepcion'         => $datos['fecha_recepcion'],
                'observaciones_recepcion' => $datos['observaciones_recepcion'] ?? null,
                'recibido_por_id'         => $usuarioId,
            ]);

            $activo = $traslado->activo;
            $activo->update([
                'sede_id'        => $traslado->sede_destino_id,
                'area_id'        => $traslado->area_destino_id,
                'responsable_id' => array_key_exists('responsable_id', $datos)
                    ? $datos['responsable_id']
                    : ($traslado->responsable_destino_id ?? $activo->responsable_id),
            ]);

            return $traslado;
        });
    }

    /**
     * Cancela un traslado pendiente: el activo nunca salió de su ubicación de origen.
     *
     * @param ActMovimiento $traslado Traslado pendiente
     * @param string $motivo
     * @param int|null $usuarioId
     * @return ActMovimiento
     */
    public function cancelarTraslado(ActMovimiento $traslado, string $motivo, ?int $usuarioId): ActMovimiento
    {
        $traslado->update([
            'estado_traslado'    => ActMovimiento::TRASLADO_CANCELADO,
            'motivo_cancelacion' => $motivo,
            'cancelado_por_id'   => $usuarioId,
        ]);

        return $traslado;
    }

    /**
     * Guarda (o reemplaza) la imagen del activo y borra la anterior del disco.
     *
     * @param ActActivo $activo
     * @param UploadedFile $imagen
     * @param int|null $usuarioId
     * @return ActActivo
     */
    public function guardarImagen(ActActivo $activo, UploadedFile $imagen, ?int $usuarioId): ActActivo
    {
        $anterior = $activo->imagen;
        $ruta = app(ArchivoService::class)->guardar($imagen, ActActivo::DISCO_IMAGEN, ArchivoService::CARPETA_ACTIVOS, $usuarioId);

        $activo->update(['imagen' => $ruta]);
        $this->borrarArchivo($anterior);

        return $activo;
    }

    /**
     * Quita la imagen del activo y borra el archivo.
     *
     * @param ActActivo $activo
     * @return ActActivo
     */
    public function eliminarImagen(ActActivo $activo): ActActivo
    {
        $anterior = $activo->imagen;
        $activo->update(['imagen' => null]);
        $this->borrarArchivo($anterior);

        return $activo;
    }

    /**
     * Cambia el estado del activo (operativo, en mantenimiento, disponible o de baja).
     *
     * @param ActActivo $activo
     * @param string $estado
     * @param string $fecha
     * @param string $motivo
     * @param int|null $usuarioId
     * @return ActMovimiento
     */
    public function cambiarEstado(ActActivo $activo, string $estado, string $fecha, string $motivo, ?int $usuarioId): ActMovimiento
    {
        return DB::transaction(function () use ($activo, $estado, $fecha, $motivo, $usuarioId) {
            $movimiento = $activo->movimientos()->create([
                'tipo'            => 'cambio_estado',
                'estado_anterior' => $activo->estado,
                'estado_nuevo'    => $estado,
                'fecha'           => $fecha,
                'motivo'          => $motivo,
                'usuario_id'      => $usuarioId,
            ]);

            $activo->update(['estado' => $estado]);

            return $movimiento;
        });
    }

    /**
     * Borra un archivo de imagen del disco si existe.
     *
     * @param string|null $ruta
     * @return void
     */
    private function borrarArchivo(?string $ruta): void
    {
        if ($ruta) {
            Storage::disk(ActActivo::DISCO_IMAGEN)->delete($ruta);
        }
    }
}
