<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Elimina las columnas referencia_id y referencia_tipo de lp_productos,
     * ya que la relación polimórfica se gestiona ahora mediante la tabla
     * lp_producto_referencias, que permite múltiples referencias por producto.
     *
     * PREREQUISITO: Esta migración debe ejecutarse DESPUÉS de
     * 2026_05_15_000001_create_lp_producto_referencias_table.php
     * y de haber migrado los datos existentes si los hubiera.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('lp_productos', function (Blueprint $table) {
            // Eliminar índice antes de las columnas
            $table->dropIndex('idx_referencia');

            $table->dropColumn(['referencia_id', 'referencia_tipo']);
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Restaura las columnas referencia_id y referencia_tipo en lp_productos.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('lp_productos', function (Blueprint $table) {
            $table->unsignedBigInteger('referencia_id')
                  ->nullable()
                  ->comment('ID del curso o módulo relacionado (si aplica)');

            $table->enum('referencia_tipo', ['curso', 'modulo'])
                  ->nullable()
                  ->comment('Tipo de referencia');

            $table->index(['referencia_id', 'referencia_tipo'], 'idx_referencia');
        });
    }
};
