<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla descuento_producto que establece la relación
     * muchos a muchos entre descuentos y productos.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('descuento_producto', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la relación descuento-producto');

            $table->unsignedBigInteger('descuento_id')->comment('ID del descuento');
            $table->unsignedBigInteger('producto_id')->comment('ID del producto');

            $table->timestamps();

            // Foreign keys
            $table->foreign('descuento_id')
                  ->references('id')
                  ->on('descuentos')
                  ->onDelete('cascade');

            $table->foreign('producto_id')
                  ->references('id')
                  ->on('lp_productos')
                  ->onDelete('cascade');

            // Unique constraint para evitar duplicados
            $table->unique(['descuento_id', 'producto_id'], 'idx_descuento_producto');

            // Índices
            $table->index('descuento_id', 'idx_descuento');
            $table->index('producto_id', 'idx_producto');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla descuento_producto si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('descuento_producto');
    }
};

