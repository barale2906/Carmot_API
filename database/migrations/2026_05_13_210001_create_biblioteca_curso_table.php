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
        Schema::create('biblioteca_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biblioteca_id')
                  ->constrained('biblioteca')
                  ->onDelete('cascade');
            $table->foreignId('curso_id')
                  ->constrained('cursos')
                  ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['biblioteca_id', 'curso_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biblioteca_curso');
    }
};
