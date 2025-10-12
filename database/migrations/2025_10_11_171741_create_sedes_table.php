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
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->comment('nombre de la sede');
            $table->string('direccion')->comment('direccion de la sede');
            $table->string('telefono')->comment('telefono de la sede');
            $table->string('email')->comment('email de la sede');
            $table->time('hora_inicio')->comment('hora de inicio de la sede');
            $table->time('hora_fin')->comment('hora de fin de la sede');

            $table->unsignedBigInteger('poblacion_id');
            $table->foreign('poblacion_id')->references('id')->on('poblacions');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sedes');
    }
};
