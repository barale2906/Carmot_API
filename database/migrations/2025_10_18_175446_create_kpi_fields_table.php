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
        Schema::create('kpi_fields', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('kpi_id');
            $table->foreign('kpi_id')->references('id')->on('kpis')->onDelete('cascade');

            $table->string('field_name')->comment("El nombre del campo en la base de datos (ej. total_amount, status, created_at).");
            $table->string('display_name')->comment("Nombre amigable para mostrar al usuario (ej. Monto Total, Estado del Ticket).");
            $table->string('field_type')->comment('Tipo de dato del campo (ej. numeric, integer, biginteger, string, text, date, datetime, timestamp, time, year, boolean, tinyint, decimal, float, double, json, longtext, mediumtext, char, varchar). Útil para validaciones y UI.');
            $table->string('operation')->nullable()->comment('La operación a realizar con este campo (ej. sum, count, avg, min, max, where, group_by).');
            $table->string('operator')->nullable()->comment('Para condiciones where (ej. =, >, <, LIKE, IN).');
            $table->string('value')->nullable()->comment(" El valor a comparar si operation es where (ej. 'completed', 'pending').");
            $table->boolean('is_required')->default(false)->comment('Si este campo es obligatorio para el cálculo.');
            $table->integer('order')->default(0)->comment('Para ordenar la presentación de los campos.');
            $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_fields');
    }
};
