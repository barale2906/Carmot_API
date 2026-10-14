<?php

namespace App\Services\Activos;

use App\Models\Activos\ActActivo;
use App\Models\Activos\ActMovimiento;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones de un activo fijo que dejan rastro en su historial (act_movimientos):
 * el ingreso al inventario, los traslados entre sedes/áreas y los cambios de estado.
 * La ubicación y el estado solo cambian por aquí, nunca con la edición general.
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
     * Traslada el activo a otra sede/área (y opcionalmente cambia el responsable).
     *
     * @param ActActivo $activo
     * @param array{sede_id: int, area_id: int|null, responsable_id?: int|null, fecha: string, motivo: string} $datos
     * @param int|null $usuarioId
     * @return ActMovimiento
     */
    public function trasladar(ActActivo $activo, array $datos, ?int $usuarioId): ActMovimiento
    {
        return DB::transaction(function () use ($activo, $datos, $usuarioId) {
            $movimiento = $activo->movimientos()->create([
                'tipo'            => 'traslado',
                'sede_origen_id'  => $activo->sede_id,
                'area_origen_id'  => $activo->area_id,
                'sede_destino_id' => $datos['sede_id'],
                'area_destino_id' => $datos['area_id'] ?? null,
                'fecha'           => $datos['fecha'],
                'motivo'          => $datos['motivo'],
                'usuario_id'      => $usuarioId,
            ]);

            $activo->update([
                'sede_id'        => $datos['sede_id'],
                'area_id'        => $datos['area_id'] ?? null,
                'responsable_id' => array_key_exists('responsable_id', $datos) ? $datos['responsable_id'] : $activo->responsable_id,
            ]);

            return $movimiento;
        });
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
}
