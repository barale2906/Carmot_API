<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_entregas_kit — cabecera de entrega de un kit.
     *
     * Agrupa el despacho de todos los componentes del kit para un ítem de pedido.
     * Una fila por cada inv_pedido_item de tipo kit.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_entregas_kit', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_item_id')
                ->constrained('inv_pedido_items')
                ->onDelete('restrict');

            $table->foreignId('kit_producto_id')
                ->constrained('inv_productos')
                ->onDelete('restrict')
                ->comment('Denormalizado: el producto de tipo kit');

            $table->unsignedInteger('cantidad_kits');

            $table->enum('status', ['pendiente', 'parcial', 'completo'])->default('pendiente');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Cajero que ejecutó la entrega');

            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_entregas_kit');
    }
};
