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
            $table->string('calculation_type')->default('predefined')->comment("Tipo de cálculo ('predefined', 'custom_fields', 'sql_query').");
            $table->integer('base_model')->nullable()->comment('ID del modelo en la configuración de KPIs base para el cálculo (ej. App\Models\Academico\Matricula)');
            $table->string('default_period_type')->nullable()->comment('Tipo de periodo por defecto (daily, weekly, monthly, yearly, custom)');
            $table->date('default_period_start_date')->nullable()->comment('Fecha de inicio del periodo por defecto');
            $table->date('default_period_end_date')->nullable()->comment('Fecha de fin del periodo por defecto');
            $table->boolean('use_custom_time_range')->default(false)->comment('Si el KPI debe usar un rango de tiempo personalizado');
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
