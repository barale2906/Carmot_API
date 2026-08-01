<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el campo origen a descuentos para distinguir
     * descuentos académicos (1) de descuentos de inventarios (0).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('descuentos', function (Blueprint $table) {
            $table->tinyInteger('origen')
                ->default(1)
                ->comment('Origen del descuento: 0=Inventarios, 1=Académico')
                ->after('status');

            $table->index('origen', 'idx_desc_origen');
        });
    }

    /**
     * Revierte la migración eliminando el campo origen.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('descuentos', function (Blueprint $table) {
            $table->dropIndex('idx_desc_origen');
            $table->dropColumn('origen');
        });
    }
};
