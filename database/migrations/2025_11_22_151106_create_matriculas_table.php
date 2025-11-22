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
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->foreignId('ciclo_id')->constrained('ciclos')->onDelete('cascade');
            $table->foreignId('estudiante_id')->constrained('users')->onDelete('cascade')->comment('estudiante matriculado');
            $table->foreignId('matriculado_por_id')->constrained('users')->onDelete('cascade')->comment('usuario que matriculó al estudiante');
            $table->foreignId('comercial_id')->constrained('users')->onDelete('cascade')->comment('usuario que gestionó la venta');
            $table->date('fecha_matricula')->comment('fecha de la matrícula');
            $table->date('fecha_inicio')->comment('fecha de inicio de las clases');
            $table->double('monto')->comment('monto de la matrícula');
            $table->longText('observaciones')->nullable()->comment('observaciones de la matrícula');
            $table->integer('status')->default(1)->comment('0: inactivo, 1: activo, 2: anulado');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};
