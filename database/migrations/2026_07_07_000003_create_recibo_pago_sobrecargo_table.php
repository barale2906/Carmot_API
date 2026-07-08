<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla recibo_pago_sobrecargo que registra en detalle
     * los sobrecargos aplicados a cada recibo de pago, vinculando
     * el sobrecargo al medio de pago específico que lo disparó.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('recibo_pago_sobrecargo', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro');

            $table->foreignId('recibo_pago_id')
                ->constrained('recibos_pago')
                ->onDelete('cascade')
                ->comment('Recibo de pago al que pertenece este sobrecargo');

            $table->foreignId('descuento_id')
                ->constrained('descuentos')
                ->onDelete('restrict')
                ->comment('Configuración del sobrecargo aplicado (tipo_movimiento=sobrecargo)');

            // Vincula el sobrecargo al medio de pago concreto que lo disparó.
            // Permite mostrar en el recibo: "Recargo Visa 2% sobre $100.000 en tarjeta = $2.000".
            $table->foreignId('recibo_pago_medio_pago_id')
                ->constrained('recibo_pago_medio_pago')
                ->onDelete('cascade')
                ->comment('Medio de pago que disparó este sobrecargo');

            $table->decimal('valor_base', 15, 2)->comment('Monto del medio de pago sobre el que se calculó el porcentaje');
            $table->decimal('valor_sobrecargo', 15, 2)->comment('Monto calculado: porcentaje * valor_base');
            $table->decimal('valor_final', 15, 2)->comment('valor_base + valor_sobrecargo');

            $table->timestamps();

            // Índices
            $table->index('recibo_pago_id', 'idx_rps_recibo');
            $table->index('descuento_id', 'idx_rps_descuento');
            $table->index('recibo_pago_medio_pago_id', 'idx_rps_medio_pago');
        });

        DB::statement('ALTER TABLE recibo_pago_sobrecargo ADD CONSTRAINT chk_rps_valor_sobrecargo_positivo CHECK (valor_sobrecargo >= 0)');
        DB::statement('ALTER TABLE recibo_pago_sobrecargo ADD CONSTRAINT chk_rps_valor_final_calculado CHECK (ABS(valor_final - (valor_base + valor_sobrecargo)) < 0.01)');
    }

    /**
     * Revierte las migraciones.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('recibo_pago_sobrecargo');
    }
};
