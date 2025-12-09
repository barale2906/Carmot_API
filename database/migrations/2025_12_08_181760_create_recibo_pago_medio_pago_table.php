<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla recibo_pago_medio_pago que almacena
     * los medios de pago utilizados en cada recibo.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('recibo_pago_medio_pago', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro');

            $table->foreignId('recibo_pago_id')->constrained('recibos_pago')->onDelete('cascade')->comment('ID del recibo de pago');

            $table->string('medio_pago', 50)->comment('Medio de pago (efectivo, tarjeta, transferencia, cheque, etc.)');
            $table->decimal('valor', 15, 2)->comment('Valor pagado con este medio');
            $table->string('referencia', 100)->nullable()->comment('Referencia del pago (número de cheque, transferencia, etc.)');
            $table->string('banco', 100)->nullable()->comment('Banco relacionado (si aplica)');

            $table->timestamps();

            // Índices
            $table->index('recibo_pago_id', 'idx_recibo_pago');
            $table->index('medio_pago', 'idx_medio_pago');

            // Nota: Las validaciones de valores positivos se realizan
            // a nivel de aplicación en los Form Requests y modelos
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla recibo_pago_medio_pago si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('recibo_pago_medio_pago');
    }
};

