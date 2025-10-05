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
        Schema::create('referidos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('curso_id');
            $table->foreign('curso_id')->references('id')->on('cursos');

            $table->unsignedBigInteger('gestor_id');
            $table->foreign('gestor_id')->references('id')->on('users');

            $table->string('nombre')->nullable()->comment('Nombre del posible estudiante');
            $table->string('celular')->comment('numero de celular de contacto');
            $table->string('ciudad')->comment('ciudad de referencia');

            $table->integer('status')->default(0)->comment('0 creado, 1 interesado, 2 pendiente por matricular, 3 Matriculado, 4 declinado');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referidos');
    }
};
