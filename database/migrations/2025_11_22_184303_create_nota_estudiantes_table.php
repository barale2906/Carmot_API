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
        Schema::create('nota_estudiantes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('estudiante_id')->constrained('users')->onDelete('cascade')->comment('estudiante al que pertenece la nota');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade')->comment('grupo donde está el estudiante');
            $table->foreignId('modulo_id')->constrained('modulos')->onDelete('cascade')->comment('módulo al que pertenece la nota');
            $table->foreignId('esquema_calificacion_id')->constrained('esquema_calificacions')->onDelete('cascade')->comment('esquema de calificación usado');
            $table->foreignId('tipo_nota_esquema_id')->constrained('tipo_nota_esquemas')->onDelete('cascade')->comment('tipo de nota específico');

            $table->decimal('nota', 5, 2)->comment('valor de la nota');
            $table->decimal('nota_ponderada', 5, 2)->comment('nota ponderada (nota × peso / 100)');
            $table->date('fecha_registro')->comment('fecha en que se registró la nota');
            $table->foreignId('registrado_por_id')->constrained('users')->onDelete('cascade')->comment('profesor que registró la nota');
            $table->text('observaciones')->nullable()->comment('observaciones adicionales');
            $table->integer('status')->default(1)->comment('0: pendiente, 1: registrada, 2: cerrada');

            $table->softDeletes();
            $table->timestamps();

            // Índice único para evitar notas duplicadas para el mismo estudiante/tipo/grupo/módulo
            $table->unique(['estudiante_id', 'grupo_id', 'modulo_id', 'tipo_nota_esquema_id'], 'nota_estudiante_unique');

            // Índices para búsquedas rápidas
            $table->index(['estudiante_id', 'modulo_id']);
            $table->index(['grupo_id', 'modulo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nota_estudiantes');
    }
};
