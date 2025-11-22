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
        Schema::create('ciclos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sede_id');
            $table->foreign('sede_id')->references('id')->on('sedes');

            $table->unsignedBigInteger('curso_id');
            $table->foreign('curso_id')->references('id')->on('cursos');

            $table->string('nombre')->comment('nombre del ciclo');
            $table->text('descripcion')->nullable()->comment('descripción del ciclo');

            $table->date('fecha_inicio')->nullable()->comment('Fecha de inicio del ciclo');
            $table->date('fecha_fin')->nullable()->comment('Fecha de finalización del ciclo');
            $table->integer('duracion_dias')->nullable()->comment('Duración del ciclo en días (calculado automáticamente)');
            $table->boolean('fecha_fin_automatica')->default(true)->comment('Indica si la fecha de fin se calcula automáticamente');
            $table->integer('inscritos')->nullable()->comment('Cantidad de estudiantes inscritos al ciclo');

            $table->integer('status')->default(1)->comment('1: activo, 0: inactivo');

            $table->softDeletes();
            $table->timestamps();
        });

        // Crear tabla pivot para la relación muchos a muchos entre ciclos y grupos
        Schema::create('ciclo_grupo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ciclo_id');
            $table->unsignedBigInteger('grupo_id');
            $table->timestamps();

            $table->foreign('ciclo_id')->references('id')->on('ciclos')->onDelete('cascade');
            $table->foreign('grupo_id')->references('id')->on('grupos')->onDelete('cascade');

            $table->integer('orden')->default(1)->comment('Orden secuencial en que se dictará el grupo dentro del ciclo');
            $table->date('fecha_inicio_grupo')->nullable()->comment('Fecha de inicio específica del grupo dentro del ciclo');
            $table->date('fecha_fin_grupo')->nullable()->comment('Fecha de fin específica del grupo dentro del ciclo');

            // Índice único para evitar duplicados
            $table->unique(['ciclo_id', 'grupo_id'], 'ciclo_grupo_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ciclo_grupo');
        Schema::dropIfExists('ciclos');
    }
};
