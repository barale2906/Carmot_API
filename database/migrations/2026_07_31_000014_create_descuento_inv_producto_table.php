<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla descuento_inv_producto — pivot entre descuentos de inventario y productos.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('descuento_inv_producto', function (Blueprint $table) {
            $table->id();

            $table->foreignId('descuento_id')
                ->constrained('descuentos')
                ->onDelete('cascade')
                ->comment('Descuento de inventario (origen=0)');

            $table->foreignId('producto_id')
                ->constrained('inv_productos')
                ->onDelete('cascade');

            $table->timestamps();

            $table->unique(['descuento_id', 'producto_id'], 'uq_descuento_inv_producto');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('descuento_inv_producto');
    }
};
