<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla lp_lista_precio_poblacion que establece la relación
     * muchos a muchos entre listas de precios y poblaciones (ciudades).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lp_lista_precio_poblacion', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la relación lista-población');

            $table->unsignedBigInteger('lista_precio_id')->comment('ID de la lista de precios');
            $table->unsignedBigInteger('poblacion_id')->comment('ID de la población');

            $table->timestamps();

            // Foreign keys
            $table->foreign('lista_precio_id')
                  ->references('id')
                  ->on('lp_listas_precios')
                  ->onDelete('cascade');

            $table->foreign('poblacion_id')
                  ->references('id')
                  ->on('poblacions')
                  ->onDelete('cascade');

            // Unique constraint para evitar duplicados
            $table->unique(['lista_precio_id', 'poblacion_id'], 'uk_lista_poblacion');

            // Índices
            $table->index('lista_precio_id', 'idx_lista_precio');
            $table->index('poblacion_id', 'idx_poblacion');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla lp_lista_precio_poblacion si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('lp_lista_precio_poblacion');
    }
};
