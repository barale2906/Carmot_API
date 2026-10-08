<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea el calendario de días en que el instituto no labora (festivos,
     * vacaciones colectivas, cierres de sede). Un registro con sede_id NULL
     * aplica a todas las sedes.
     */
    public function up(): void
    {
        Schema::create('dias_no_laborables', function (Blueprint $table) {
            $table->id();

            $table->date('fecha')->comment('Día en que no se labora');
            $table->string('nombre')->comment('Motivo corto, ej. "Día de la Independencia"');
            $table->string('tipo', 20)->default('festivo')->comment('festivo | vacaciones | institucional | otro');

            $table->unsignedBigInteger('sede_id')->nullable()->comment('NULL = aplica a todas las sedes');
            $table->foreign('sede_id')->references('id')->on('sedes');

            $table->text('observaciones')->nullable();
            $table->integer('status')->default(1)->comment('1: activo, 0: inactivo');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['fecha', 'sede_id'], 'idx_dia_no_laborable_fecha_sede');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('dias_no_laborables');
    }
};
