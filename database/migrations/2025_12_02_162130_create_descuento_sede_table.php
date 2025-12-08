<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla descuento_sede que establece la relación
     * muchos a muchos entre descuentos y sedes.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('descuento_sede', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la relación descuento-sede');

            $table->unsignedBigInteger('descuento_id')->comment('ID del descuento');
            $table->unsignedBigInteger('sede_id')->comment('ID de la sede');

            $table->timestamps();

            // Foreign keys
            $table->foreign('descuento_id')
                  ->references('id')
                  ->on('descuentos')
                  ->onDelete('cascade');

            $table->foreign('sede_id')
                  ->references('id')
                  ->on('sedes')
                  ->onDelete('cascade');

            // Unique constraint para evitar duplicados
            $table->unique(['descuento_id', 'sede_id'], 'idx_descuento_sede');

            // Índices
            $table->index('descuento_id', 'idx_descuento');
            $table->index('sede_id', 'idx_sede');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla descuento_sede si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('descuento_sede');
    }
};

