<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla pivot recibo_pago_concepto_pago que almacena
     * el detalle de los conceptos de pago en cada recibo.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('recibo_pago_concepto_pago', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro');

            $table->foreignId('recibo_pago_id')->constrained('recibos_pago')->onDelete('cascade')->comment('ID del recibo de pago');
            $table->foreignId('concepto_pago_id')->constrained('conceptos_pago')->onDelete('restrict')->comment('ID del concepto de pago');

            $table->decimal('valor', 15, 2)->comment('Valor pagado (fuera descuento)');
            $table->integer('tipo')->comment('Tipo según concepto de pago');
            $table->string('producto', 255)->nullable()->comment('Nombre del producto');
            $table->unsignedInteger('cantidad')->default(1)->comment('Cantidad del producto');
            $table->decimal('unitario', 15, 2)->comment('Precio unitario');
            $table->decimal('subtotal', 15, 2)->comment('Subtotal (cantidad * unitario)');
            $table->unsignedBigInteger('id_relacional')->nullable()->comment('ID de relación (cartera pagada o inventario)');
            $table->text('observaciones')->nullable()->comment('Observaciones adicionales');

            $table->timestamps();

            // Índices
            $table->index('recibo_pago_id', 'idx_recibo_pago');
            $table->index('concepto_pago_id', 'idx_concepto_pago');
            $table->index('id_relacional', 'idx_id_relacional');

            // Nota: Las validaciones de valores positivos y cálculos se realizan
            // a nivel de aplicación en los Form Requests y modelos
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla recibo_pago_concepto_pago si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('recibo_pago_concepto_pago');
    }
};

