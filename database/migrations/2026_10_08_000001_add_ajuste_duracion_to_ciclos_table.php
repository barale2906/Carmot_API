<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soporte para ciclos de duración fija (fecha_fin_automatica = false con
     * fecha_fin definida): el contenido de los módulos se ajusta con un factor
     * para que el ciclo termine en la fecha fijada.
     *
     *  - ciclos.fecha_fin_teorica: fecha en que terminaría dictando el 100 % de las horas.
     *  - ciclos.factor_ajuste: proporción de horas que se dicta (1 = completo; NULL = no aplica o no cabe).
     *  - ciclos.advertencia_ajuste: aviso para el usuario (factor bajo el umbral, fecha que no alcanza, etc.).
     *  - ciclo_grupo.horas_planeadas: horas de contenido asignadas al grupo en este ciclo.
     */
    public function up(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->date('fecha_fin_teorica')->nullable()->after('fecha_fin')
                ->comment('Fecha fin si se dictara el 100 % de las horas');
            $table->decimal('factor_ajuste', 5, 4)->nullable()->after('fecha_fin_automatica')
                ->comment('Proporción de horas dictadas en modo fecha fin fija (1 = completo)');
            $table->string('advertencia_ajuste', 1000)->nullable()->after('factor_ajuste')
                ->comment('Advertencia del último cálculo de fechas');
        });

        Schema::table('ciclo_grupo', function (Blueprint $table) {
            $table->decimal('horas_planeadas', 8, 2)->nullable()->after('fecha_fin_grupo')
                ->comment('Horas de contenido asignadas al grupo en este ciclo');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('ciclo_grupo', function (Blueprint $table) {
            $table->dropColumn('horas_planeadas');
        });

        Schema::table('ciclos', function (Blueprint $table) {
            $table->dropColumn(['fecha_fin_teorica', 'factor_ajuste', 'advertencia_ajuste']);
        });
    }
};
