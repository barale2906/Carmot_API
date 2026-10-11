<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aprobación del cierre de caja: quién lo aprueba, cuándo y con qué observaciones.
     * El cierre del cajero queda en estado 2 (Por aprobar) hasta su aprobación.
     */
    public function up(): void
    {
        Schema::table('ld_turnos_caja', function (Blueprint $table) {
            $table->foreignId('aprobado_por_id')->nullable()->after('observaciones_cierre')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable()->after('aprobado_por_id');
            $table->text('observaciones_aprobacion')->nullable()->after('aprobado_at');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('ld_turnos_caja', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprobado_por_id');
            $table->dropColumn(['aprobado_at', 'observaciones_aprobacion']);
        });
    }
};
