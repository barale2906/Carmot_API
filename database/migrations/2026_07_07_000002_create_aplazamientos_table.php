<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplazamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('ciclos')->cascadeOnDelete();
            $table->foreignId('tipo_aplazamiento_id')->constrained('tipo_aplazamientos');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('aplazamiento_padre_id')->nullable()->constrained('aplazamientos')->nullOnDelete();

            $table->date('fecha_aplazamiento');
            $table->date('fecha_inicio_original');
            $table->date('fecha_reinicio_probable');
            $table->integer('dias_aplazamiento');

            $table->date('fecha_reinicio_real')->nullable();
            $table->integer('dias_reales')->nullable();

            $table->boolean('mover_cartera')->default(false);
            $table->integer('clases_movidas')->default(0);
            $table->integer('carteras_movidas')->default(0);

            $table->text('observaciones')->nullable();

            // 0=Pendiente 1=Confirmado 2=Ampliado 3=Revertido 4=Interrumpido
            $table->integer('estado')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplazamientos');
    }
};
