<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();

            $table->string('name')->comment('Nombre del KPI (ej. "Ventas Totales", "Nuevos Clientes")');
            $table->string('code')->unique()->comment('Código interno para el KPI (ej. total_sales)');
            $table->text('description')->nullable()->comment('Descripción del KPI.');
            $table->string('unit')->nullable()->comment('Unidad de medida (ej. "USD", "%").');
            $table->boolean('is_active')->default(true)->comment('Habilita/deshabilita el KPI.');


            $table->integer('numerator_model')->nullable()->comment('ID del modelo para el numerador (referencia a config/kpis.php)');
            $table->string('numerator_field')->nullable()->comment('Campo del numerador');
            $table->string('numerator_operation')->default('count')->comment('Operación del numerador (count, sum, avg, max, min)');
            $table->integer('denominator_model')->nullable()->comment('ID del modelo para el denominador (referencia a config/kpis.php)');
            $table->string('denominator_field')->nullable()->comment('Campo del denominador');
            $table->string('denominator_operation')->default('count')->comment('Operación del denominador (count, sum, avg, max, min)');
            $table->integer('calculation_factor')->default(1)->comment('Factor de cálculo (*1, *100, *1000, etc. o cualquier otro numero que se desee multiplicar)');
            $table->float('target_value')->nullable()->comment('Meta del indicador.');
            $table->string('date_field')->default('created_at')->comment('Campo de fecha para calcular el KPI (default: created_at)');
            $table->string('period_type')->default('monthly')->comment('Tipo de periodo (daily, weekly, monthly, quarterly, yearly)');
            $table->string('chart_type')->nullable()->comment('Tipo de gráfico (bar, pie, line, area, scatter)');
            $table->json('chart_schema')->nullable()->comment('JSON con esquema del gráfico para ECharts');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpis');
    }
};
