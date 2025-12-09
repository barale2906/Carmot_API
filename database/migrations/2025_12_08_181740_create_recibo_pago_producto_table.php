<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla pivot recibo_pago_producto que almacena
     * los productos incluidos en cada recibo de pago.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('recibo_pago_producto', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro');

            $table->foreignId('recibo_pago_id')->constrained('recibos_pago')->onDelete('cascade')->comment('ID del recibo de pago');
            $table->foreignId('producto_id')->constrained('lp_productos')->onDelete('restrict')->comment('ID del producto (LpProducto)');

            $table->unsignedInteger('cantidad')->default(1)->comment('Cantidad del producto');
            $table->decimal('precio_unitario', 15, 2)->comment('Precio unitario aplicado');
            $table->decimal('subtotal', 15, 2)->comment('Subtotal (cantidad * precio_unitario)');

            $table->timestamps();

            // Índices
            $table->index('recibo_pago_id', 'idx_recibo_pago');
            $table->index('producto_id', 'idx_producto');

            // Nota: Las validaciones de valores positivos y cálculos se realizan
            // a nivel de aplicación en los Form Requests y modelos
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla recibo_pago_producto si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('recibo_pago_producto');
    }
};

