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
        Schema::create('agendas', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('referido_id');
            $table->foreign('referido_id')->references('id')->on('referidos');

            $table->unsignedBigInteger('agendador_id');
            $table->foreign('agendador_id')->references('id')->on('users');

            $table->date('fecha')->comment('Fecha de la cita agendada');
            $table->time('hora')->comment('Hora en que se genera el registro');
            $table->string('jornada')->comment('am o pm');
            $table->integer('status')->default(0)->comment('0 agendado, 1 asistio, 2 no asisitio, 3 reprogramo, 4 cancelo');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agendas');
    }
};
