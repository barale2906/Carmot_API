<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla descuento_curso: cursos a los que se restringe un descuento.
     * Un descuento sin cursos asociados aplica a todos los cursos.
     */
    public function up(): void
    {
        Schema::create('descuento_curso', function (Blueprint $table) {
            $table->id();

            $table->foreignId('descuento_id')->constrained('descuentos')->onDelete('cascade');
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');

            $table->timestamps();

            $table->unique(['descuento_id', 'curso_id'], 'uq_descuento_curso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('descuento_curso');
    }
};
