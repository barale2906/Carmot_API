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
        Schema::create('area_sede', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('sede_id');

            $table->foreign('area_id')->references('id')->on('areas')->onDelete('cascade');
            $table->foreign('sede_id')->references('id')->on('sedes')->onDelete('cascade');

            $table->timestamps();

            // Índice único para evitar duplicados
            $table->unique(['area_id', 'sede_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('area_sede');
    }
};
