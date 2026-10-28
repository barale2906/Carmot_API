<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre de notas por ciclo: mientras `notas_cerradas_at` tenga fecha, no se pueden
 * registrar, editar ni eliminar notas ni recuperaciones de los estudiantes del ciclo.
 * Cada cierre y reapertura queda en `ciclo_cierre_notas` con quién lo hizo y por qué.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->timestamp('notas_cerradas_at')->nullable()->after('advertencia_ajuste')->comment('fecha de cierre de notas; null = registro abierto');
            $table->foreignId('notas_cerradas_por_id')->nullable()->after('notas_cerradas_at')->constrained('users')->nullOnDelete()->comment('usuario que cerró las notas');
        });

        Schema::create('ciclo_cierre_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('ciclos')->onDelete('cascade')->comment('ciclo cerrado o reabierto');
            $table->enum('accion', ['cierre', 'reapertura'])->comment('acción realizada sobre las notas del ciclo');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade')->comment('usuario que realizó la acción');
            $table->string('motivo', 500)->nullable()->comment('motivo (obligatorio al reabrir)');
            $table->timestamps();

            $table->index(['ciclo_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ciclo_cierre_notas');

        Schema::table('ciclos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('notas_cerradas_por_id');
            $table->dropColumn('notas_cerradas_at');
        });
    }
};
