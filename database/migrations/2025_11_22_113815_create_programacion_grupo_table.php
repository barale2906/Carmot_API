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
        Schema::create('programacion_grupo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('programacion_id');
            $table->unsignedBigInteger('grupo_id');
            $table->timestamps();

            $table->foreign('programacion_id')->references('id')->on('programacions')->onDelete('cascade');
            $table->foreign('grupo_id')->references('id')->on('grupos')->onDelete('cascade');

            $table->date('fecha_inicio_grupo')->nullable()->comment('Fecha de inicio específica del grupo dentro de la programación');
            $table->date('fecha_fin_grupo')->nullable()->comment('Fecha de fin específica del grupo dentro de la programación');

            // Índice único para evitar duplicados
            $table->unique(['programacion_id', 'grupo_id'], 'programacion_grupo_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programacion_grupo');
    }
};

