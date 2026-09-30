<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvEntregaKitComponente;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvNecesidadCompra;
use App\Models\Inventarios\InvPedido;
use App\Models\Inventarios\InvStock;
use App\Notifications\Inventarios\InvPendienteDisponibleNotification;
use App\Models\User;

/**
 * InvNecesidadService — genera y atiende necesidades de compra por falta de stock.
 *
 * Regla central: una línea de entrega (InvEntregaSimple o InvEntregaKitComponente)
 * tiene como máximo UNA necesidad pendiente. Su producto y cantidad se ajustan a
 * medida que se conoce la variante elegida o avanza la entrega, y se cierra cuando
 * la línea ya no depende de comprar.
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
        $stock = InvStock::where('almacen_id', $almacenId)
            ->where('producto_id', $productoId)
            ->value('cantidad_disponible') ?? 0;

        $faltante = $requerido - $stock;

        if ($faltante <= 0) {
            // El stock ya cubre lo pendiente: si quedaba una necesidad abierta de un
            // momento anterior, deja de tener sentido y se cierra.
            static::cerrarDeEntregable($entregableType, $entregableId);

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
     * Crea la necesidad pendiente de una línea de entrega o actualiza la existente.
     *
     * La línea es la clave: si ya tenía una necesidad pendiente (por ejemplo a nombre
     * del grupo, antes de que el cajero eligiera la variante) se reutiliza, ajustando
     * producto y cantidad, en vez de crear un duplicado.
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
                'entregable_type' => $entregableType,
                'entregable_id'   => $entregableId,
                'status'          => InvNecesidadCompra::STATUS_PENDIENTE,
            ],
            [
                'producto_id'        => $productoId,
                'almacen_id'         => $almacenId,
                'cantidad_necesaria' => $cantidadNecesaria,
                'estudiante_id'      => $estudianteId,
                'notificado'         => false,
            ]
        );

        // La necesidad refleja siempre el producto concreto y lo que aún se debe
        // entregar (la variante puede elegirse después y el faltante se reduce con
        // cada entrega parcial), sin tocar el flag de notificación.
        if (! $necesidad->wasRecentlyCreated) {
            $necesidad->fill([
                'producto_id'        => $productoId,
                'cantidad_necesaria' => $cantidadNecesaria,
            ]);

            if ($necesidad->isDirty()) {
                $necesidad->save();
            }
        }

        return $necesidad;
    }

    /**
     * Marca como atendida la necesidad pendiente de una línea de entrega.
     *
     * Se usa cuando la línea ya no depende de comprar: fue entregada o el stock la cubre.
     *
     * @param string $entregableType  FQCN del modelo de entrega
     * @param int    $entregableId
     * @return void
     */
    public static function cerrarDeEntregable(string $entregableType, int $entregableId): void
    {
        InvNecesidadCompra::where('entregable_type', $entregableType)
            ->where('entregable_id', $entregableId)
            ->where('status', InvNecesidadCompra::STATUS_PENDIENTE)
            ->update(['status' => InvNecesidadCompra::STATUS_ATENDIDA]);
    }

    /**
     * Cancela las necesidades pendientes de todas las líneas de entrega de un pedido.
     *
     * Al anular el pedido ya no se le debe nada al estudiante: no hay que comprar.
     *
     * @param InvPedido $pedido
     * @return void
     */
    public static function cancelarDePedido(InvPedido $pedido): void
    {
        $pedido->loadMissing(['items.entregaSimple', 'items.entregaKit.componentes']);

        $simples     = $pedido->items->pluck('entregaSimple.id')->filter();
        $componentes = $pedido->items->pluck('entregaKit')->filter()->flatMap->componentes->pluck('id');

        InvNecesidadCompra::where('status', InvNecesidadCompra::STATUS_PENDIENTE)
            ->where(fn ($q) => $q
                ->where(fn ($s) => $s->where('entregable_type', InvEntregaSimple::class)->whereIn('entregable_id', $simples))
                ->orWhere(fn ($k) => $k->where('entregable_type', InvEntregaKitComponente::class)->whereIn('entregable_id', $componentes))
            )
            ->update(['status' => InvNecesidadCompra::STATUS_CANCELADA]);
    }

    /**
     * Atiende las necesidades pendientes que cubre un stock recién incrementado.
     *
     * El stock disponible se reparte entre las necesidades del producto en el almacén
     * por orden de antigüedad (primero la más vieja). Cada necesidad cubierta se marca
     * atendida y se notifica a los cajeros; el reparto se detiene en la primera que no
     * alcanza a cubrirse, para no saltarse a quien lleva más tiempo esperando.
     *
     * Se invoca desde `InvStockService::incrementar()`, por donde pasa todo ingreso de stock.
     *
     * @param int $productoId
     * @param int $almacenId
     * @return void
     */
    public static function verificarPendientesCubiertos(int $productoId, int $almacenId): void
    {
        $restante = InvStock::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->value('cantidad_disponible') ?? 0;

        $necesidades = InvNecesidadCompra::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->where('status', InvNecesidadCompra::STATUS_PENDIENTE)
            ->where('notificado', false)
            ->with('almacen.sede')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($necesidades as $necesidad) {
            if ($restante < $necesidad->cantidad_necesaria) {
                break;
            }

            $restante -= $necesidad->cantidad_necesaria;

            $necesidad->update([
                'status'     => InvNecesidadCompra::STATUS_ATENDIDA,
                'notificado' => true,
            ]);

            static::notificarCajerosSede($necesidad);
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
