<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla pivot recibo_pago_descuento que almacena
     * los descuentos aplicados en cada recibo de pago.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('recibo_pago_descuento', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro');

            $table->foreignId('recibo_pago_id')->constrained('recibos_pago')->onDelete('cascade')->comment('ID del recibo de pago');
            $table->foreignId('descuento_id')->constrained('descuentos')->onDelete('restrict')->comment('ID del descuento aplicado');

            $table->decimal('valor_descuento', 15, 2)->comment('Valor del descuento aplicado');
            $table->decimal('valor_original', 15, 2)->comment('Valor original antes del descuento');
            $table->decimal('valor_final', 15, 2)->comment('Valor final después del descuento');

            $table->timestamps();

            // Índices
            $table->index('recibo_pago_id', 'idx_recibo_pago');
            $table->index('descuento_id', 'idx_descuento');

            // Nota: Las validaciones de valores positivos y cálculos se realizan
            // a nivel de aplicación en los Form Requests y modelos
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla recibo_pago_descuento si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('recibo_pago_descuento');
    }
};

