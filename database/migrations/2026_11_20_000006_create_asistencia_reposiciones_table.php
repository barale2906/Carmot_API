<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reposiciones: el estudiante toma una clase en otro grupo de su curso (misma semana y módulo).
     */
    public function up(): void
    {
        Schema::create('asistencia_reposiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('users')->onDelete('cascade')->comment('estudiante que repone la clase');
            $table->foreignId('clase_origen_id')->constrained('asistencia_clases_programadas')->onDelete('cascade')->comment('clase de su grupo que no tomará');
            $table->foreignId('clase_destino_id')->constrained('asistencia_clases_programadas')->onDelete('cascade')->comment('clase de otro grupo en la que la tomará');
            $table->text('motivo')->nullable()->comment('razón de la reposición');
            $table->foreignId('registrada_por_id')->nullable()->constrained('users')->onDelete('set null')->comment('quien notificó la reposición');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['estudiante_id', 'clase_origen_id'], 'idx_reposicion_estudiante_origen');
            $table->index('clase_destino_id', 'idx_reposicion_destino');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia_reposiciones');
    }
};
