<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_ordenes_compra — cabecera de una orden de compra a un proveedor.
     *
     * Flujo: borrador → enviada → (recibida_parcial) → recibida | cancelada
     * Cuando se recibe (parcial o total), se genera un documento de movimiento tipo=entrada.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_ordenes_compra', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proveedor_id')
                ->constrained('inv_proveedores')
                ->onDelete('restrict');

            $table->foreignId('almacen_id')
                ->constrained('inv_almacenes')
                ->onDelete('restrict');

            $table->foreignId('responsable_id')
                ->constrained('users')
                ->onDelete('restrict');

            $table->enum('status', ['borrador', 'enviada', 'recibida_parcial', 'recibida', 'cancelada'])
                ->default('borrador');

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->text('observaciones')->nullable();
            $table->date('fecha_esperada')->nullable();

            $table->timestamps();

            $table->index(['proveedor_id', 'status'], 'idx_inv_oc_proveedor_status');
            $table->index(['almacen_id', 'status'], 'idx_inv_oc_almacen_status');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_ordenes_compra');
    }
};
