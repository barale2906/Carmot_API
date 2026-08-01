<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_entregas_simple — seguimiento de entrega de productos simples.
     *
     * Se genera automáticamente cuando el pedido pasa a status 'pagado'.
     * Una fila por cada inv_pedido_item de tipo simple.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_entregas_simple', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pedido_item_id')
                ->constrained('inv_pedido_items')
                ->onDelete('restrict');

            $table->foreignId('producto_id')
                ->constrained('inv_productos')
                ->onDelete('restrict')
                ->comment('Denormalizado para consultas rápidas');

            $table->unsignedInteger('cantidad_entregada')->default(0);

            $table->enum('status', ['pendiente', 'entregado'])->default('pendiente');

            $table->timestamp('fecha_entrega')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Cajero que ejecutó la entrega');

            $table->timestamps();

            $table->index(['status'], 'idx_inv_entregas_simple_status');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_entregas_simple');
    }
};
