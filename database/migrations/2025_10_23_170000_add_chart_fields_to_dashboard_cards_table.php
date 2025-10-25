<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar campos de gráficos a la tabla dashboard_cards
 *
 * Esta migración agrega los campos necesarios para soportar la configuración
 * de gráficos en las tarjetas de dashboard, incluyendo tipo de gráfico,
 * parámetros, agrupación y filtros.
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
            // Tipo de gráfico (bar, pie, line, area, scatter)
            $table->string('chart_type')->nullable()->after('kpi_id')
                ->comment('Tipo de gráfico a mostrar (bar, pie, line, area, scatter)');

            // Parámetros específicos del gráfico (JSON)
            $table->json('chart_parameters')->nullable()->after('chart_type')
                ->comment('Parámetros específicos del tipo de gráfico seleccionado');

            // Campo por el cual agrupar los datos
            $table->string('group_by')->nullable()->after('chart_parameters')
                ->comment('Campo por el cual agrupar los datos para el gráfico');

            // Filtros a aplicar a los datos (JSON)
            $table->json('filters')->nullable()->after('group_by')
                ->comment('Filtros dinámicos a aplicar a los datos del gráfico');
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
            $table->dropColumn([
                'chart_type',
                'chart_parameters',
                'group_by',
                'filters'
            ]);
        });
    }
};
