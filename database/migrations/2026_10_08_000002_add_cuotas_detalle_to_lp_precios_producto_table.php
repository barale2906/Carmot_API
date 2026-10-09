<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega cuotas_detalle: valor individual de cada cuota (opcional).
     * Cuando es null, el valor de las cuotas se calcula automáticamente (valor_cuota).
     */
    public function up(): void
    {
        Schema::table('lp_precios_producto', function (Blueprint $table) {
            $table->json('cuotas_detalle')->nullable()->after('valor_cuota')
                ->comment('Valores individuales de cada cuota, en orden (opcional). Su suma debe ser igual a precio_total');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('lp_precios_producto', function (Blueprint $table) {
            $table->dropColumn('cuotas_detalle');
        });
    }
};
