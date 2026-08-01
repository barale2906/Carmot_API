<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvNecesidadCompra;
use App\Models\Inventarios\InvStock;
use App\Notifications\Inventarios\InvPendienteDisponibleNotification;
use App\Models\User;

/**
 * InvNecesidadService — genera y atiende necesidades de compra por falta de stock.
 */
class InvNecesidadService
{
    /**
     * Crea una necesidad de compra si no existe una activa igual.
     * Evita duplicados por (producto_id, almacen_id, entregable_type, entregable_id).
     *
     * @param int    $productoId
     * @param int    $cantidadNecesaria
     * @param int    $almacenId
     * @param int    $estudianteId
     * @param string $entregableType    FQCN del modelo de entrega
     * @param int    $entregableId
     * @return InvNecesidadCompra
     */
    public static function generarSiNoExiste(
        int $productoId,
        int $cantidadNecesaria,
        int $almacenId,
        int $estudianteId,
        string $entregableType,
        int $entregableId
    ): InvNecesidadCompra {
        return InvNecesidadCompra::firstOrCreate(
            [
                'producto_id'     => $productoId,
                'almacen_id'      => $almacenId,
                'entregable_type' => $entregableType,
                'entregable_id'   => $entregableId,
                'status'          => InvNecesidadCompra::STATUS_PENDIENTE,
            ],
            [
                'cantidad_necesaria' => $cantidadNecesaria,
                'estudiante_id'      => $estudianteId,
                'notificado'         => false,
            ]
        );
    }

    /**
     * Verifica necesidades pendientes cubiertas por un stock recién incrementado.
     * Para cada necesidad cubierta: la marca como atendida y notifica a los cajeros.
     *
     * Debe llamarse después de cada `InvStockService::incrementar()` o importación de stock.
     *
     * @param int $productoId
     * @param int $almacenId
     * @return void
     */
    public static function verificarPendientesCubiertos(int $productoId, int $almacenId): void
    {
        $stock = InvStock::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->first();

        if (!$stock) {
            return;
        }

        $necesidades = InvNecesidadCompra::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->where('status', InvNecesidadCompra::STATUS_PENDIENTE)
            ->where('notificado', false)
            ->with('almacen.sede')
            ->get();

        foreach ($necesidades as $necesidad) {
            if ($stock->cantidad_disponible >= $necesidad->cantidad_necesaria) {
                $necesidad->update([
                    'status'     => InvNecesidadCompra::STATUS_ATENDIDA,
                    'notificado' => true,
                ]);

                static::notificarCajerosSede($necesidad);
            }
        }
    }

    /**
     * Notifica a todos los cajeros de la sede que tienen permiso inv_ventasCrear.
     *
     * @param InvNecesidadCompra $necesidad
     * @return void
     */
    private static function notificarCajerosSede(InvNecesidadCompra $necesidad): void
    {
        $sedeId = $necesidad->almacen?->sede_id;

        if (!$sedeId) {
            return;
        }

        $cajeros = User::whereHas('almacenes', fn ($q) => $q->where('sede_id', $sedeId))
            ->permission('inv_ventasCrear')
            ->get();

        foreach ($cajeros as $cajero) {
            $cajero->notify(new InvPendienteDisponibleNotification($necesidad));
        }
    }
}
