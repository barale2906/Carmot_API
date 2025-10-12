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
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sede_id');
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->unsignedBigInteger('area_id');
            $table->foreign('area_id')->references('id')->on('areas');

            $table->integer('grupo_id')->nullable()->comment('id del grupo');
            $table->string('grupo_nombre')->nullable()->comment('nombre del grupo');

            $table->boolean('tipo')->default(true)->comment('true: horario sede, false: horario grupo');
            $table->boolean('periodo')->default(true)->comment('true: inicia, false: termina aplica para el horario de la sede');
            $table->enum('dia', ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'])->comment('día de la semana');
            $table->time('hora')->nullable()->comment('hora de inicio o cierre del horario');
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
        Schema::dropIfExists('horarios');
    }
};
