<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_precios_producto — precios de venta de productos de inventario.
     *
     * Vincula un producto (simple o kit) a una lista de precios de inventario (origen=0).
     * El precio es inmutable para pedidos ya creados; los cambios solo afectan pedidos futuros.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_precios_producto', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lista_precio_id')
                ->constrained('lp_listas_precios')
                ->onDelete('restrict')
                ->comment('Lista de precios de inventario (origen=0)');

            $table->foreignId('producto_id')
                ->constrained('inv_productos')
                ->onDelete('restrict')
                ->comment('Producto vendible (tipo=simple o tipo=kit)');

            $table->decimal('precio', 14, 2)
                ->comment('Precio de venta al estudiante');

            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['lista_precio_id', 'producto_id'], 'uq_inv_precio_lista_producto');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_precios_producto');
    }
};
