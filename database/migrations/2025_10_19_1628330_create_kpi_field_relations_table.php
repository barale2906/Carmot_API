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
        Schema::create('kpi_field_relations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('kpi_id');
            $table->foreign('kpi_id')->references('id')->on('kpis')->onDelete('cascade');

            $table->unsignedBigInteger('field_a_id');
            $table->foreign('field_a_id')->references('id')->on('kpi_fields')->onDelete('cascade');

            $table->unsignedBigInteger('field_b_id');
            $table->foreign('field_b_id')->references('id')->on('kpi_fields')->onDelete('cascade');

            $table->string('operation')->comment('Operación matemática entre campos (divide, multiply, add, subtract, percentage)');
            $table->string('field_a_model')->nullable()->comment('Modelo del primer campo si es diferente al modelo base del KPI');
            $table->string('field_b_model')->nullable()->comment('Modelo del segundo campo si es diferente al modelo base del KPI');
            $table->json('field_a_conditions')->nullable()->comment('Condiciones adicionales para el primer campo');
            $table->json('field_b_conditions')->nullable()->comment('Condiciones adicionales para el segundo campo');
            $table->decimal('multiplier', 10, 4)->default(1)->comment('Multiplicador para el resultado final');
            $table->boolean('is_active')->default(true)->comment('Si la relación está activa');
            $table->integer('order')->default(0)->comment('Orden de procesamiento');

            $table->softDeletes();
            $table->timestamps();

            // Índices únicos
            $table->unique(['kpi_id', 'field_a_id', 'field_b_id'], 'unique_kpi_field_relation');

            // Índices para optimización
            $table->index(['kpi_id', 'is_active']);
            $table->index(['field_a_id']);
            $table->index(['field_b_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_field_relations');
    }
};
