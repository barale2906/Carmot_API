<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla lp_tipos_producto que define los tipos de productos
     * disponibles en el sistema (curso, modulo, complementario).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lp_tipos_producto', function (Blueprint $table) {
            $table->id()->comment('Identificador único del tipo de producto');
            
            $table->string('nombre', 255)->comment('Nombre del tipo de producto');
            $table->string('codigo', 50)->unique()->comment('Código único del tipo (curso, modulo, complementario)');
            $table->boolean('es_financiable')->default(false)->comment('Indica si el producto puede ser financiado');
            $table->text('descripcion')->nullable()->comment('Descripción del tipo de producto');
            $table->tinyInteger('status')->default(1)->comment('0: inactivo, 1: activo');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('codigo', 'idx_codigo');
            $table->index('status', 'idx_status');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla lp_tipos_producto si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('lp_tipos_producto');
    }
};
