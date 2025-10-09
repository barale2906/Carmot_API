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
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->comment('nombre del curso');
            $table->double('duracion')->comment('duración del curso en horas');
            $table->integer('tipo')->comment('0 curso práctico, 1 Técnico laboral');
            $table->integer('status')->default(1)->comment('0 inactivo, 1 Activo');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
