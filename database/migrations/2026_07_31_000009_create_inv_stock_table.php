<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_stock — resumen de existencias por almacén y producto simple.
     *
     * cantidad_disponible = cantidad_total - cantidad_reservada.
     * La cantidad_reservada se incrementa en Sprint 3 cuando un pedido pagado
     * espera entrega, y se decrementa al despachar.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_stock', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro de stock');

            $table->foreignId('almacen_id')
                ->constrained('inv_almacenes')
                ->onDelete('restrict')
                ->comment('Almacén donde se encuentra el stock');

            $table->foreignId('producto_id')
                ->constrained('inv_productos')
                ->onDelete('restrict')
                ->comment('Producto simple con stock');

            $table->integer('cantidad_total')->default(0)
                ->comment('Total de unidades físicas en el almacén');

            $table->integer('cantidad_reservada')->default(0)
                ->comment('Unidades reservadas por pedidos pagados pendientes de entrega');

            $table->integer('cantidad_disponible')->default(0)
                ->comment('Unidades disponibles para nuevas ventas (total - reservada)');

            $table->timestamp('ultimo_movimiento_at')->nullable()
                ->comment('Fecha del último movimiento de stock');

            $table->timestamp('updated_at')->nullable();

            $table->unique(['almacen_id', 'producto_id'], 'uq_inv_stock_almacen_producto');
            $table->index(['producto_id', 'cantidad_disponible'], 'idx_inv_stock_disponible');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_stock');
    }
};
