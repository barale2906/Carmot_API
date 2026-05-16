<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla lp_producto_referencias que establece la relación
     * polimórfica muchos a muchos entre productos LP y sus referencias
     * académicas (cursos o módulos).
     *
     * Un producto puede estar vinculado a uno o más cursos y/o módulos.
     * Un curso o módulo puede estar vinculado a uno o más productos LP.
     * Los productos sin referencias académicas (diplomas, certificados, etc.)
     * simplemente no tienen registros en esta tabla.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lp_producto_referencias', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la referencia');

            $table->unsignedBigInteger('lp_producto_id')
                  ->comment('ID del producto LP');

            $table->unsignedBigInteger('referencia_id')
                  ->comment('ID del curso o módulo referenciado');

            $table->enum('referencia_tipo', ['curso', 'modulo'])
                  ->comment('Tipo de referencia académica: curso o modulo');

            $table->timestamps();

            // Foreign key al producto LP (cascade delete: al eliminar el producto, se eliminan sus referencias)
            $table->foreign('lp_producto_id')
                  ->references('id')
                  ->on('lp_productos')
                  ->onDelete('cascade');

            // Constraint único: un producto no puede tener dos veces la misma referencia del mismo tipo
            $table->unique(
                ['lp_producto_id', 'referencia_id', 'referencia_tipo'],
                'uk_producto_referencia'
            );

            // Índices para optimizar consultas de ambos lados de la relación
            $table->index('lp_producto_id', 'idx_lp_producto_id');
            $table->index(['referencia_id', 'referencia_tipo'], 'idx_referencia');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('lp_producto_referencias');
    }
};
