<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvStock;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para operaciones atómicas sobre inv_stock.
 *
 * Todos los métodos deben ejecutarse dentro de DB::transaction()
 * para garantizar consistencia con los documentos de movimiento.
 */
class InvStockService
{
    /**
     * Incrementa el stock de un producto en un almacén.
     * Crea el registro si no existe.
     *
     * @param int $almacenId
     * @param int $productoId
     * @param int $cantidad
     * @return InvStock
     */
    public static function incrementar(int $almacenId, int $productoId, int $cantidad): InvStock
    {
        $stock = InvStock::lockForUpdate()
            ->firstOrCreate(
                ['almacen_id' => $almacenId, 'producto_id' => $productoId],
                ['cantidad_total' => 0, 'cantidad_reservada' => 0, 'cantidad_disponible' => 0]
            );

        $stock->cantidad_total      += $cantidad;
        $stock->cantidad_disponible += $cantidad;
        $stock->ultimo_movimiento_at = now();
        $stock->save();

        return $stock->fresh();
    }

    /**
     * Decrementa el stock de un producto en un almacén.
     * Solo aplica a cantidad_disponible; la cantidad_reservada se maneja en el flujo de pedidos.
     *
     * @param int $almacenId
     * @param int $productoId
     * @param int $cantidad
     * @return InvStock
     * @throws \RuntimeException Si el stock disponible es insuficiente.
     */
    public static function decrementar(int $almacenId, int $productoId, int $cantidad): InvStock
    {
        $stock = InvStock::lockForUpdate()
            ->where('almacen_id', $almacenId)
            ->where('producto_id', $productoId)
            ->first();

        if (!$stock || $stock->cantidad_disponible < $cantidad) {
            $disponible = $stock?->cantidad_disponible ?? 0;
            throw new \RuntimeException(
                "Stock insuficiente. Disponible: {$disponible}, requerido: {$cantidad}."
            );
        }

        $stock->cantidad_total      -= $cantidad;
        $stock->cantidad_disponible -= $cantidad;
        $stock->ultimo_movimiento_at = now();
        $stock->save();

        return $stock->fresh();
    }

    /**
     * Aplica un ajuste al stock (positivo o negativo).
     *
     * @param int $almacenId
     * @param int $productoId
     * @param int $cantidad     Positivo = incremento, negativo = decremento
     * @return InvStock
     */
    public static function ajustar(int $almacenId, int $productoId, int $cantidad): InvStock
    {
        if ($cantidad >= 0) {
            return static::incrementar($almacenId, $productoId, $cantidad);
        }

        return static::decrementar($almacenId, $productoId, abs($cantidad));
    }

    /**
     * Traslada stock de un almacén origen a uno destino.
     *
     * @param int $almacenOrigenId
     * @param int $almacenDestinoId
     * @param int $productoId
     * @param int $cantidad
     * @return array{origen: InvStock, destino: InvStock}
     */
    public static function trasladar(
        int $almacenOrigenId,
        int $almacenDestinoId,
        int $productoId,
        int $cantidad
    ): array {
        $origen  = static::decrementar($almacenOrigenId, $productoId, $cantidad);
        $destino = static::incrementar($almacenDestinoId, $productoId, $cantidad);

        return ['origen' => $origen, 'destino' => $destino];
    }

    /**
     * Reserva unidades de stock cuando un pedido queda pagado (pendiente de entrega).
     * No cambia cantidad_total, solo traslada de disponible a reservado.
     *
     * @param int $almacenId
     * @param int $productoId
     * @param int $cantidad
     * @return InvStock
     */
    public static function reservar(int $almacenId, int $productoId, int $cantidad): InvStock
    {
        $stock = InvStock::lockForUpdate()
            ->where('almacen_id', $almacenId)
            ->where('producto_id', $productoId)
            ->first();

        if (!$stock || $stock->cantidad_disponible < $cantidad) {
            $disponible = $stock?->cantidad_disponible ?? 0;
            throw new \RuntimeException(
                "Stock insuficiente para reservar. Disponible: {$disponible}, requerido: {$cantidad}."
            );
        }

        $stock->cantidad_reservada  += $cantidad;
        $stock->cantidad_disponible -= $cantidad;
        $stock->ultimo_movimiento_at = now();
        $stock->save();

        return $stock->fresh();
    }

    /**
     * Libera la reserva y aplica la salida definitiva al entregar.
     * Decrementa cantidad_total y cantidad_reservada (ya no está disponible ni reservado).
     *
     * @param int $almacenId
     * @param int $productoId
     * @param int $cantidad
     * @return InvStock
     */
    public static function entregarReservado(int $almacenId, int $productoId, int $cantidad): InvStock
    {
        $stock = InvStock::lockForUpdate()
            ->where('almacen_id', $almacenId)
            ->where('producto_id', $productoId)
            ->first();

        if (!$stock || $stock->cantidad_reservada < $cantidad) {
            throw new \RuntimeException('No hay suficientes unidades reservadas para la entrega.');
        }

        $stock->cantidad_total     -= $cantidad;
        $stock->cantidad_reservada -= $cantidad;
        $stock->ultimo_movimiento_at = now();
        $stock->save();

        return $stock->fresh();
    }
}
