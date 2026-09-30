<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ENTREGA_SIMPLE     = 'App\\Models\\Inventarios\\InvEntregaSimple';
    private const ENTREGA_COMPONENTE = 'App\\Models\\Inventarios\\InvEntregaKitComponente';

    /**
     * Ejecuta las migraciones.
     *
     * Depura las necesidades de compra que dejó el cálculo anterior, que abría una
     * necesidad a nombre del grupo al preparar la entrega del kit y nunca la cerraba:
     *
     *  1. Duplicadas: una línea de entrega con varias necesidades pendientes conserva
     *     solo la de la variante concreta (la más reciente) y cancela las demás.
     *  2. Fantasma: se atienden las necesidades de líneas que ya fueron entregadas.
     *  3. Huérfanas: se cancelan las necesidades de pedidos anulados.
     *
     * Es idempotente: solo toca necesidades en estado pendiente.
     *
     * @return void
     */
    public function up(): void
    {
        DB::transaction(function () {
            $this->cancelarDuplicadas();
            $this->atenderLineasEntregadas();
            $this->cancelarDePedidosAnulados();
        });
    }

    /**
     * Revierte las migraciones.
     *
     * La depuración de datos no se revierte: las necesidades eliminadas eran errores.
     *
     * @return void
     */
    public function down(): void
    {
        //
    }

    /**
     * Por cada línea de entrega con más de una necesidad pendiente conserva la
     * preferida: la de un producto que no es grupo y, entre ellas, la más reciente.
     *
     * @return void
     */
    private function cancelarDuplicadas(): void
    {
        $pendientes = DB::table('inv_necesidades_compra as n')
            ->join('inv_productos as p', 'p.id', '=', 'n.producto_id')
            ->where('n.status', 'pendiente')
            ->select('n.id', 'n.entregable_type', 'n.entregable_id', 'p.tipo')
            ->get()
            ->groupBy(fn ($n) => $n->entregable_type . ':' . $n->entregable_id)
            ->filter(fn ($grupo) => $grupo->count() > 1);

        foreach ($pendientes as $grupo) {
            $conservar = $grupo->sortBy([
                fn ($a, $b) => ($a->tipo === 'grupo') <=> ($b->tipo === 'grupo'),
                fn ($a, $b) => $b->id <=> $a->id,
            ])->first();

            DB::table('inv_necesidades_compra')
                ->whereIn('id', $grupo->pluck('id')->reject(fn ($id) => $id === $conservar->id))
                ->update(['status' => 'cancelada', 'updated_at' => now()]);
        }
    }

    /**
     * Atiende las necesidades pendientes cuya línea de entrega ya se entregó.
     *
     * @return void
     */
    private function atenderLineasEntregadas(): void
    {
        $lineas = [
            self::ENTREGA_SIMPLE     => 'inv_entregas_simple',
            self::ENTREGA_COMPONENTE => 'inv_entregas_kit_componente',
        ];

        foreach ($lineas as $tipo => $tabla) {
            DB::table('inv_necesidades_compra')
                ->where('status', 'pendiente')
                ->where('entregable_type', $tipo)
                ->whereIn('entregable_id', DB::table($tabla)->where('status', 'entregado')->select('id'))
                ->update(['status' => 'atendida', 'updated_at' => now()]);
        }
    }

    /**
     * Cancela las necesidades pendientes de pedidos anulados.
     *
     * @return void
     */
    private function cancelarDePedidosAnulados(): void
    {
        $simples = DB::table('inv_entregas_simple as e')
            ->join('inv_pedido_items as i', 'i.id', '=', 'e.pedido_item_id')
            ->join('inv_pedidos as p', 'p.id', '=', 'i.pedido_id')
            ->where('p.status', 'cancelado')
            ->select('e.id');

        $componentes = DB::table('inv_entregas_kit_componente as c')
            ->join('inv_entregas_kit as k', 'k.id', '=', 'c.entrega_kit_id')
            ->join('inv_pedido_items as i', 'i.id', '=', 'k.pedido_item_id')
            ->join('inv_pedidos as p', 'p.id', '=', 'i.pedido_id')
            ->where('p.status', 'cancelado')
            ->select('c.id');

        DB::table('inv_necesidades_compra')
            ->where('status', 'pendiente')
            ->where(fn ($q) => $q
                ->where(fn ($s) => $s->where('entregable_type', self::ENTREGA_SIMPLE)->whereIn('entregable_id', $simples))
                ->orWhere(fn ($k) => $k->where('entregable_type', self::ENTREGA_COMPONENTE)->whereIn('entregable_id', $componentes))
            )
            ->update(['status' => 'cancelada', 'updated_at' => now()]);
    }
};
