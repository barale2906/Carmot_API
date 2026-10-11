<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula cada recibo de pago al turno de caja en que se generó.
     */
    public function up(): void
    {
        Schema::table('recibos_pago', function (Blueprint $table) {
            $table->foreignId('turno_caja_id')->nullable()->after('cajero_id')
                ->constrained('ld_turnos_caja')->nullOnDelete();
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('recibos_pago', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turno_caja_id');
        });
    }
};
