<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turnos de caja por cajero y sede, autorizaciones para reabrir caja en el día
     * y bitácora de eventos del turno (apertura, cierre, reversión, anulaciones).
     */
    public function up(): void
    {
        Schema::create('ld_autorizaciones_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cajero_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha')->comment('día en que el cajero puede abrir un nuevo turno');
            $table->foreignId('autorizado_por_id')->constrained('users')->cascadeOnDelete();
            $table->string('motivo', 500);
            $table->unsignedBigInteger('turno_caja_id')->nullable()->comment('turno que consumió la autorización');
            $table->timestamps();

            $table->index(['cajero_id', 'fecha']);
        });

        Schema::create('ld_turnos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes');
            $table->foreignId('cajero_id')->constrained('users');
            $table->date('fecha');
            $table->decimal('base_inicial', 14, 2);
            $table->timestamp('abierto_at');
            $table->unsignedTinyInteger('status')->default(0)->comment('0=Abierto, 1=Cerrado');
            $table->timestamp('cerrado_at')->nullable();
            $table->foreignId('cerrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('efectivo_esperado', 14, 2)->nullable();
            $table->decimal('efectivo_contado', 14, 2)->nullable();
            $table->decimal('diferencia', 14, 2)->nullable()->comment('contado - esperado');
            $table->json('resumen')->nullable()->comment('totales congelados al cierre');
            $table->text('observaciones_cierre')->nullable();
            $table->boolean('ajustado')->default(false)->comment('se anuló un registro después del cierre');
            $table->foreignId('autorizacion_id')->nullable()->constrained('ld_autorizaciones_turno')->nullOnDelete();
            $table->timestamps();

            $table->index(['cajero_id', 'status']);
            $table->index(['sede_id', 'fecha']);
        });

        Schema::table('ld_autorizaciones_turno', function (Blueprint $table) {
            $table->foreign('turno_caja_id')->references('id')->on('ld_turnos_caja')->nullOnDelete();
        });

        Schema::create('ld_turno_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_caja_id')->constrained('ld_turnos_caja')->cascadeOnDelete();
            $table->string('accion', 40);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo')->nullable();
            $table->json('datos')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('ld_turno_eventos');
        Schema::table('ld_autorizaciones_turno', function (Blueprint $table) {
            $table->dropForeign(['turno_caja_id']);
        });
        Schema::dropIfExists('ld_turnos_caja');
        Schema::dropIfExists('ld_autorizaciones_turno');
    }
};
