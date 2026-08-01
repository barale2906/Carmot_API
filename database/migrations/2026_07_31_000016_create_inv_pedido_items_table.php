<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_pedido_items — líneas de producto de un pedido de inventario.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_pedido_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_id')
                ->constrained('inv_pedidos')
                ->onDelete('cascade');

            $table->foreignId('producto_id')
                ->constrained('inv_productos')
                ->onDelete('restrict')
                ->comment('Producto simple o kit vendido');

            $table->unsignedInteger('cantidad');

            $table->decimal('precio_unitario', 14, 2)
                ->comment('Precio capturado al momento del pedido — inmutable');

            $table->decimal('subtotal', 14, 2)
                ->comment('cantidad × precio_unitario');

            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_pedido_items');
    }
};
