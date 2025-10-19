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
        Schema::create('dashboard_cards', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('dashboard_id');
            $table->foreign('dashboard_id')->references('id')->on('dashboards')->onDelete('cascade');

            $table->unsignedBigInteger('kpi_id');
            $table->foreign('kpi_id')->references('id')->on('kpis')->onDelete('cascade');

            $table->string('title')->nullable()->comment('Título del card (ej. "Ventas Totales", "Nuevos Clientes")');
            $table->string('background_color')->nullable()->comment('Color de fondo del card (ej. "#FF0000", "#00FF00")');
            $table->string('text_color')->nullable()->comment('Color de texto del card (ej. "#FF0000", "#00FF00")');
            $table->integer('width')->default(1)->comment('Ancho del card (ej. 1, 2, 3)');
            $table->integer('height')->default(1)->comment('Alto del card (ej. 1, 2, 3)');
            $table->integer('x_position')->default(0)->comment('Posición horizontal del card en el grid del dashboard.');
            $table->integer('y_position')->default(0)->comment('Posición vertical del card en el grid del dashboard');
            $table->string('period_type')->nullable()->comment('Tipo de periodo (ej. "daily", "weekly", "monthly", "yearly")');
            $table->date('period_start_date')->nullable()->comment('Fecha de inicio del periodo personalizado');
            $table->date('period_end_date')->nullable()->comment('Fecha de fin del periodo personalizado');
            $table->json('custom_field_values')->nullable()->comment('Valores específicos para campos configurables del KPI (ej. {"product_id": 123}).');
            $table->integer('order')->default(0)->comment('Orden del card (ej. 0, 1, 2)');
            $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_cards');
    }
};
