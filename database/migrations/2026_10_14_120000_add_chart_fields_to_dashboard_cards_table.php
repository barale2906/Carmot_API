<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaura en dashboard_cards las columnas de gráfico que usan el modelo, el FormRequest
 * y el cálculo de tarjetas (chart_type, chart_parameters, group_by, filters).
 *
 * La migración original (2025_10_23_170000) se eliminó en el rediseño de KPI, así que
 * las instalaciones nuevas no tenían estas columnas y crear tarjetas respondía 500 (bug B39).
 * Cada columna se agrega solo si falta, para no fallar donde la migración original sí corrió.
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
        Schema::table('dashboard_cards', function (Blueprint $table) {
            if (! Schema::hasColumn('dashboard_cards', 'chart_type')) {
                $table->string('chart_type')->nullable()->after('kpi_id')
                    ->comment('Tipo de gráfico a mostrar (bar, pie, line, area, scatter)');
            }
            if (! Schema::hasColumn('dashboard_cards', 'chart_parameters')) {
                $table->json('chart_parameters')->nullable()->after('chart_type')
                    ->comment('Parámetros específicos del tipo de gráfico seleccionado');
            }
            if (! Schema::hasColumn('dashboard_cards', 'group_by')) {
                $table->string('group_by')->nullable()->after('chart_parameters')
                    ->comment('Campo por el cual agrupar los datos para el gráfico');
            }
            if (! Schema::hasColumn('dashboard_cards', 'filters')) {
                $table->json('filters')->nullable()->after('group_by')
                    ->comment('Filtros dinámicos a aplicar a los datos del gráfico');
            }
        });
    }

    /**
     * Revierte las migraciones.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('dashboard_cards', function (Blueprint $table) {
            $table->dropColumn(['chart_type', 'chart_parameters', 'group_by', 'filters']);
        });
    }
};
