<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla descuento_lista_precio que establece la relación
     * muchos a muchos entre descuentos y listas de precios.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('descuento_lista_precio', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la relación descuento-lista_precio');

            $table->unsignedBigInteger('descuento_id')->comment('ID del descuento');
            $table->unsignedBigInteger('lista_precio_id')->comment('ID de la lista de precios');

            $table->timestamps();

            // Foreign keys
            $table->foreign('descuento_id')
                  ->references('id')
                  ->on('descuentos')
                  ->onDelete('cascade');

            $table->foreign('lista_precio_id')
                  ->references('id')
                  ->on('lp_listas_precios')
                  ->onDelete('cascade');

            // Unique constraint para evitar duplicados
            $table->unique(['descuento_id', 'lista_precio_id'], 'idx_descuento_lista_precio');

            // Índices
            $table->index('descuento_id', 'idx_descuento');
            $table->index('lista_precio_id', 'idx_lista_precio');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla descuento_lista_precio si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('descuento_lista_precio');
    }
};

