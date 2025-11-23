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
        Schema::create('tipo_nota_esquemas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('esquema_calificacion_id')->constrained('esquema_calificacions')->onDelete('cascade')->comment('esquema de calificación al que pertenece');

            $table->string('nombre_tipo')->comment('nombre del tipo de nota (ej: Parcial 1, Quiz, Proyecto Final)');
            $table->decimal('peso', 5, 2)->comment('peso en porcentaje (0-100)');
            $table->integer('orden')->default(1)->comment('orden de visualización');
            $table->decimal('nota_minima', 5, 2)->default(0)->comment('nota mínima permitida');
            $table->decimal('nota_maxima', 5, 2)->default(5)->comment('nota máxima permitida');
            $table->text('descripcion')->nullable()->comment('descripción del tipo de nota');

            $table->timestamps();

            // Índice para ordenar por esquema y orden
            $table->index(['esquema_calificacion_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_nota_esquemas');
    }
};
