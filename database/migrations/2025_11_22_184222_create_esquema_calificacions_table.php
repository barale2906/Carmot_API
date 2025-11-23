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
        Schema::create('esquema_calificacions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('modulo_id')->constrained('modulos')->onDelete('cascade')->comment('módulo al que pertenece el esquema');
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->onDelete('cascade')->comment('grupo específico (opcional, si es null aplica a todos los grupos del módulo)');
            $table->foreignId('profesor_id')->constrained('users')->onDelete('cascade')->comment('profesor que creó el esquema');

            $table->string('nombre_esquema')->comment('nombre del esquema de calificación');
            $table->text('descripcion')->nullable()->comment('descripción del esquema');
            $table->text('condicion_aplicacion')->nullable()->comment('condición de aplicación (ej: horario, condición especial)');
            $table->integer('status')->default(1)->comment('0: inactivo, 1: activo');

            $table->softDeletes();
            $table->timestamps();

            // Índice único para evitar esquemas duplicados en el mismo módulo/grupo
            $table->unique(['modulo_id', 'grupo_id', 'nombre_esquema'], 'esquema_modulo_grupo_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esquema_calificacions');
    }
};
