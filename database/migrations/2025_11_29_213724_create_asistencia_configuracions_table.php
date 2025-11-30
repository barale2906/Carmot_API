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
        Schema::create('asistencia_configuraciones', function (Blueprint $table) {
            $table->id();

            // Alcance de la configuración
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->onDelete('cascade')->comment('si es NULL, aplica a todos los cursos');
            $table->foreignId('modulo_id')->nullable()->constrained('modulos')->onDelete('cascade')->comment('si es NULL, aplica a todos los módulos del curso');

            // Configuración de asistencia
            $table->decimal('porcentaje_minimo', 5, 2)->default(80.00)->comment('porcentaje mínimo de asistencia requerido (0-100)');
            $table->integer('horas_minimas')->nullable()->comment('horas mínimas de asistencia requeridas (alternativa al porcentaje)');
            $table->boolean('aplicar_justificaciones')->default(true)->comment('si las ausencias justificadas cuentan para el mínimo');
            $table->boolean('perder_por_fallas')->default(true)->comment('si se pierde por no cumplir el mínimo');

            // Configuración de vigencia
            $table->date('fecha_inicio_vigencia')->nullable()->comment('fecha desde la cual aplica esta configuración');
            $table->date('fecha_fin_vigencia')->nullable()->comment('fecha hasta la cual aplica esta configuración');

            // Observaciones
            $table->text('observaciones')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Índices para búsquedas rápidas
            $table->index(['curso_id', 'modulo_id'], 'idx_curso_modulo');
            $table->index(['fecha_inicio_vigencia', 'fecha_fin_vigencia'], 'idx_vigencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencia_configuraciones');
    }
};
