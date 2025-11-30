<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla lp_productos que contiene el catálogo general de productos
     * (cursos, módulos y productos complementarios).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lp_productos', function (Blueprint $table) {
            $table->id()->comment('Identificador único del producto');
            
            $table->unsignedBigInteger('tipo_producto_id')->comment('Tipo de producto');
            $table->string('nombre', 255)->comment('Nombre del producto');
            $table->string('codigo', 100)->nullable()->unique()->comment('Código único del producto');
            $table->text('descripcion')->nullable()->comment('Descripción del producto');
            $table->unsignedBigInteger('referencia_id')->nullable()->comment('ID del curso o módulo relacionado (si aplica)');
            $table->enum('referencia_tipo', ['curso', 'modulo'])->nullable()->comment('Tipo de referencia');
            $table->tinyInteger('status')->default(1)->comment('0: inactivo, 1: activo');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('tipo_producto_id')
                  ->references('id')
                  ->on('lp_tipos_producto')
                  ->onDelete('restrict');
            
            // Índices
            $table->index('tipo_producto_id', 'idx_tipo_producto');
            $table->index(['referencia_id', 'referencia_tipo'], 'idx_referencia');
            $table->index('status', 'idx_status');
            $table->index('codigo', 'idx_codigo');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla lp_productos si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('lp_productos');
    }
};
