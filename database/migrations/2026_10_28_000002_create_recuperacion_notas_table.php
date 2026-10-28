<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota de recuperación de un módulo: se registra cuando el estudiante reprueba con la
 * nota final del esquema y define, por sí sola, si aprueba o reprueba el módulo.
 * Una sola recuperación por estudiante, grupo y módulo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recuperacion_notas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('estudiante_id')->constrained('users')->onDelete('cascade')->comment('estudiante que presenta la recuperación');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade')->comment('grupo donde cursó el módulo');
            $table->foreignId('modulo_id')->constrained('modulos')->onDelete('cascade')->comment('módulo que recupera');

            $table->decimal('nota', 5, 2)->comment('nota de la recuperación: reemplaza la nota final del módulo');
            $table->date('fecha_registro')->comment('fecha en que se presentó la recuperación');
            $table->foreignId('registrado_por_id')->constrained('users')->onDelete('cascade')->comment('usuario que registró la recuperación');
            $table->text('observaciones')->nullable()->comment('observaciones adicionales');

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['estudiante_id', 'grupo_id', 'modulo_id'], 'recuperacion_nota_unique');
            $table->index(['grupo_id', 'modulo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recuperacion_notas');
    }
};
