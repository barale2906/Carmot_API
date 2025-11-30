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
        Schema::create('asistencia_clases_programadas', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade')->comment('grupo al que pertenece la clase');
            $table->foreignId('ciclo_id')->constrained('ciclos')->onDelete('cascade')->comment('ciclo al que pertenece la clase');

            // Información de la clase
            $table->date('fecha_clase')->comment('fecha en que se dicta la clase');
            $table->time('hora_inicio')->comment('hora de inicio de la clase');
            $table->time('hora_fin')->comment('hora de fin de la clase');
            $table->decimal('duracion_horas', 4, 2)->comment('duración en horas de la clase');

            // Estado y control
            $table->enum('estado', ['programada', 'dictada', 'cancelada', 'reprogramada'])->default('programada')->comment('estado de la clase');
            $table->text('observaciones')->nullable()->comment('observaciones sobre la clase (ej: cambio de aula)');

            // Auditoría
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->onDelete('set null')->comment('usuario que programó la clase');
            $table->datetime('fecha_programacion')->nullable()->comment('fecha en que se programó la clase');

            $table->softDeletes();
            $table->timestamps();

            // Índice único para evitar clases duplicadas
            $table->unique(['grupo_id', 'ciclo_id', 'fecha_clase', 'hora_inicio'], 'unique_clase_grupo_ciclo_fecha_hora');

            // Índices para búsquedas rápidas
            $table->index('fecha_clase', 'idx_fecha_clase');
            $table->index(['ciclo_id', 'grupo_id'], 'idx_ciclo_grupo');
            $table->index('estado', 'idx_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencia_clases_programadas');
    }
};
