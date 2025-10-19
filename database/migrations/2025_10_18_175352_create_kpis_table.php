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
            $table->string('base_model')->nullable()->comment("Nombre del modelo Eloquent base para el cálculo (ej. App\Models\Academico\Matricula).");
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
