<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla descuento_poblacion que establece la relación
     * muchos a muchos entre descuentos y poblaciones (ciudades).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('descuento_poblacion', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la relación descuento-poblacion');

            $table->unsignedBigInteger('descuento_id')->comment('ID del descuento');
            $table->unsignedBigInteger('poblacion_id')->comment('ID de la población');

            $table->timestamps();

            // Foreign keys
            $table->foreign('descuento_id')
                  ->references('id')
                  ->on('descuentos')
                  ->onDelete('cascade');

            $table->foreign('poblacion_id')
                  ->references('id')
                  ->on('poblacions')
                  ->onDelete('cascade');

            // Unique constraint para evitar duplicados
            $table->unique(['descuento_id', 'poblacion_id'], 'idx_descuento_poblacion');

            // Índices
            $table->index('descuento_id', 'idx_descuento');
            $table->index('poblacion_id', 'idx_poblacion');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla descuento_poblacion si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('descuento_poblacion');
    }
};

