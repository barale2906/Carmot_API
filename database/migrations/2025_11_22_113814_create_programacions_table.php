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
        Schema::create('programacions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->foreignId('sede_id')->constrained('sedes')->onDelete('cascade');

            $table->string('nombre')->comment('nombre de la programación');
            $table->longText('descripcion')->nullable()->comment('descripción de la programación');
            $table->date('fecha_inicio')->comment('fecha de inicio de la programación');
            $table->date('fecha_fin')->comment('fecha de fin de la programación');
            $table->integer('registrados')->default(0)->comment('cantidad de estudiantes registrados en la programación');
            $table->integer('jornada')->default(0)->comment('0: mañana, 1: tarde, 2: noche, 3: fin de semana mañana, 4: fin de semana tarde');
            $table->integer('status')->default(1)->comment('1: activo, 0: inactivo');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programacions');
    }
};
