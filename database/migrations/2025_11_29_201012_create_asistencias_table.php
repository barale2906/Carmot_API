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
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();

            // Relaciones principales
            $table->foreignId('estudiante_id')->constrained('users')->onDelete('cascade')->comment('estudiante que asiste');
            $table->foreignId('clase_programada_id')->constrained('asistencia_clases_programadas')->onDelete('cascade')->comment('clase a la que asiste');

            // Campos redundantes para optimización de consultas
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade')->comment('grupo (para búsquedas rápidas)');
            $table->foreignId('ciclo_id')->constrained('ciclos')->onDelete('cascade')->comment('ciclo (para búsquedas rápidas)');
            $table->foreignId('modulo_id')->constrained('modulos')->onDelete('cascade')->comment('módulo (para reportes)');
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade')->comment('curso (para reportes por curso)');

            // Información de asistencia
            $table->enum('estado', ['presente', 'ausente', 'justificado', 'tardanza'])->default('presente')->comment('estado de la asistencia');
            $table->time('hora_registro')->nullable()->comment('hora en que se registró la asistencia');
            $table->text('observaciones')->nullable()->comment('observaciones (ej: motivo de justificación)');

            // Auditoría
            $table->foreignId('registrado_por_id')->constrained('users')->onDelete('cascade')->comment('usuario que registró la asistencia');
            $table->datetime('fecha_registro')->comment('fecha y hora del registro');

            $table->softDeletes();
            $table->timestamps();

            // Índice único: un estudiante solo puede tener una asistencia por clase
            $table->unique(['estudiante_id', 'clase_programada_id'], 'unique_asistencia_estudiante_clase');

            // Índices para búsquedas y reportes
            $table->index(['estudiante_id', 'ciclo_id'], 'idx_estudiante_ciclo');
            $table->index(['estudiante_id', 'grupo_id'], 'idx_estudiante_grupo');
            $table->index(['estudiante_id', 'curso_id'], 'idx_estudiante_curso');
            $table->index('clase_programada_id', 'idx_clase_programada');
            $table->index('estado', 'idx_estado');
            $table->index('fecha_registro', 'idx_fecha_registro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
