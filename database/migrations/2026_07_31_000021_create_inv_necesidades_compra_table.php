<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_necesidades_compra — alerta de falta de stock para una entrega pendiente.
     *
     * Se genera cuando no hay stock suficiente en el momento de despachar.
     * Cuando el stock llega (entrada de mercancía), se notifica a los cajeros de la sede
     * y se marca como atendida.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_necesidades_compra', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                ->constrained('inv_productos')
                ->onDelete('restrict')
                ->comment('Producto simple que se necesita');

            $table->unsignedInteger('cantidad_necesaria');

            $table->nullableMorphs('entregable');

            $table->foreignId('almacen_id')
                ->constrained('inv_almacenes')
                ->onDelete('restrict')
                ->comment('Almacén donde debe llegar el stock');

            $table->foreignId('estudiante_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Estudiante que está esperando la entrega');

            $table->enum('status', ['pendiente', 'atendida', 'cancelada'])->default('pendiente');

            $table->boolean('notificado')->default(false)
                ->comment('Si ya se disparó el badge/notificación a los cajeros');

            $table->timestamps();

            $table->index(['producto_id', 'almacen_id', 'status'], 'idx_inv_necesidades_prod_alm_status');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_necesidades_compra');
    }
};
