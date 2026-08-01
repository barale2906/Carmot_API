<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_orden_compra_items — ítems de una orden de compra.
     *
     * cantidad_recibida se incrementa cada vez que se registra una recepción parcial.
     * Cuando cantidad_recibida >= cantidad_solicitada el ítem se considera completo.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_orden_compra_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orden_id')
                ->constrained('inv_ordenes_compra')
                ->onDelete('cascade');

            $table->foreignId('producto_id')
                ->constrained('inv_productos')
                ->onDelete('restrict');

            $table->unsignedInteger('cantidad_solicitada');
            $table->decimal('precio_costo_unitario', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->unsignedInteger('cantidad_recibida')->default(0);

            $table->timestamps();

            $table->unique(['orden_id', 'producto_id'], 'uq_inv_oc_item_orden_producto');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_orden_compra_items');
    }
};
