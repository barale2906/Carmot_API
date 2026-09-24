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
     * Registra una necesidad de compra solo por lo que realmente falta en bodega.
     *
     * Una necesidad significa "le debemos esto al estudiante y no lo tenemos". Si hay
     * stock suficiente no hay nada que comprar, aunque la entrega siga pendiente por
     * decisión del cajero o del comprador; en ese caso no se registra nada.
     *
     * @param int    $productoId
     * @param int    $requerido       Unidades pendientes de entregar
     * @param int    $almacenId
     * @param int    $estudianteId
     * @param string $entregableType  FQCN del modelo de entrega
     * @param int    $entregableId
     * @return InvNecesidadCompra|null  null si el stock ya cubre lo pendiente, caso
     *                                   en el que además cierra la necesidad abierta
     */
    public static function registrarSiFaltaStock(
        int $productoId,
        int $requerido,
        int $almacenId,
        int $estudianteId,
        string $entregableType,
        int $entregableId
    ): ?InvNecesidadCompra {
        $stock = \App\Models\Inventarios\InvStock::where('almacen_id', $almacenId)
            ->where('producto_id', $productoId)
            ->value('cantidad_disponible') ?? 0;

        $faltante = $requerido - $stock;

        if ($faltante <= 0) {
            // El stock ya cubre lo pendiente: si quedaba una necesidad abierta de un
            // momento anterior, deja de tener sentido y se cierra.
            InvNecesidadCompra::where('entregable_type', $entregableType)
                ->where('entregable_id', $entregableId)
                ->where('status', InvNecesidadCompra::STATUS_PENDIENTE)
                ->update(['status' => InvNecesidadCompra::STATUS_ATENDIDA]);

            return null;
        }

        return static::generarSiNoExiste(
            $productoId,
            $faltante,
            $almacenId,
            $estudianteId,
            $entregableType,
            $entregableId
        );
    }

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
        $necesidad = InvNecesidadCompra::firstOrCreate(
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

        // Tras una entrega parcial el faltante se reduce: la necesidad debe reflejar
        // siempre lo que aún se debe entregar, sin tocar el flag de notificación.
        if (! $necesidad->wasRecentlyCreated && $necesidad->cantidad_necesaria !== $cantidadNecesaria) {
            $necesidad->update(['cantidad_necesaria' => $cantidadNecesaria]);
        }

        return $necesidad;
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
