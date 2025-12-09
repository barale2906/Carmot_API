<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Agrega los campos codigo_academico y codigo_inventario a la tabla sedes
     * para permitir la generación de números de recibo con prefijos por sede y origen.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->string('codigo_academico', 10)->nullable()->unique()->after('email')->comment('Código de identificación para recibos académicos');
            $table->string('codigo_inventario', 10)->nullable()->unique()->after('codigo_academico')->comment('Código de identificación para recibos de inventario');

            $table->index('codigo_academico', 'idx_codigo_academico');
            $table->index('codigo_inventario', 'idx_codigo_inventario');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina los campos codigo_academico y codigo_inventario de la tabla sedes.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropIndex('idx_codigo_inventario');
            $table->dropIndex('idx_codigo_academico');
            $table->dropColumn(['codigo_inventario', 'codigo_academico']);
        });
    }
};

