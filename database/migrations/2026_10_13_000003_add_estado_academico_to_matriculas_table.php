<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado académico del estudiante en la matrícula, independiente del `status`
     * administrativo (Inactivo/Activo/Anulado), con el historial de cada cambio.
     */
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->unsignedTinyInteger('estado_academico')
                ->default(1)
                ->after('status')
                ->comment('1 Activo, 2 Aplazado, 3 Desertado, 4 Retirado, 5 Graduado');
            $table->date('fecha_estado_academico')
                ->nullable()
                ->after('estado_academico')
                ->comment('fecha desde la que rige el estado académico actual');
            $table->index('estado_academico', 'idx_matriculas_estado_academico');
        });

        Schema::create('matricula_estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->unsignedTinyInteger('estado_anterior')->comment('estado académico antes del cambio');
            $table->unsignedTinyInteger('estado_nuevo')->comment('estado académico asignado');
            $table->date('fecha')->comment('fecha desde la que rige el nuevo estado');
            $table->text('motivo')->comment('motivo del cambio (deserción, retiro voluntario, grado...)');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete()->comment('usuario que registró el cambio');
            $table->timestamps();

            $table->index(['matricula_id', 'fecha'], 'idx_matricula_estados_matricula_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matricula_estados');

        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropIndex('idx_matriculas_estado_academico');
            $table->dropColumn(['estado_academico', 'fecha_estado_academico']);
        });
    }
};
