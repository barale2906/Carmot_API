<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla pivot recibo_pago_lista_precio que relaciona
     * los recibos de pago con las listas de precios utilizadas.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('recibo_pago_lista_precio', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro');

            $table->foreignId('recibo_pago_id')->constrained('recibos_pago')->onDelete('cascade')->comment('ID del recibo de pago');
            $table->foreignId('lista_precio_id')->constrained('lp_listas_precios')->onDelete('restrict')->comment('ID de la lista de precios');

            $table->timestamps();

            // Índices
            $table->index('recibo_pago_id', 'idx_recibo_pago');
            $table->index('lista_precio_id', 'idx_lista_precio');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla recibo_pago_lista_precio si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('recibo_pago_lista_precio');
    }
};

