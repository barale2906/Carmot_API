<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el campo origen a lp_listas_precios para distinguir
     * listas académicas (1) de listas de inventarios (0).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('lp_listas_precios', function (Blueprint $table) {
            $table->tinyInteger('origen')
                ->default(1)
                ->comment('Origen de la lista: 0=Inventarios, 1=Académico')
                ->after('status');

            $table->index('origen', 'idx_lp_origen');
        });
    }

    /**
     * Revierte la migración eliminando el campo origen.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('lp_listas_precios', function (Blueprint $table) {
            $table->dropIndex('idx_lp_origen');
            $table->dropColumn('origen');
        });
    }
};
