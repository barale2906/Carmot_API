<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Concepto explícito de la cuota de cartera (B38). Las cuotas de un acuerdo de pago se
 * mostraban como "Matrícula"/"Mensualidad" porque el concepto se deducía del número de
 * cuota. Null conserva esa deducción para las cuotas de matrícula.
 */
return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('carteras', function (Blueprint $table) {
            $table->string('concepto', 60)->nullable()->after('numero_cuota')
                ->comment('Concepto de la cuota (p. ej. Inicial Acuerdo); null = Matrícula/Mensualidad según numero_cuota');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('carteras', function (Blueprint $table) {
            $table->dropColumn('concepto');
        });
    }
};
