<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prórrogas de pago: plazo nuevo (máx. 15 días) que quien toma la asistencia acuerda con un
     * estudiante en mora para dejarlo entrar a clase. No toca la cartera: las cuotas conservan su
     * vencimiento y su estructura.
     */
    public function up(): void
    {
        Schema::create('prorrogas_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('users')->onDelete('cascade')->comment('estudiante en mora');
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->onDelete('set null')->comment('sede de la cuota vencida más antigua');
            $table->json('cartera_ids')->comment('cuotas vencidas que cubre la prórroga (foto al registrarla)');
            $table->decimal('saldo_vencido', 12, 2)->comment('saldo vencido al registrarla');
            $table->date('fecha_acuerdo')->comment('día en que se acordó');
            $table->date('fecha_compromiso')->comment('fecha hasta la que el estudiante puede entrar a clase');
            $table->string('estado', 20)->default('vigente')->comment('vigente | cumplido | incumplido');
            $table->text('observacion')->nullable();
            $table->foreignId('registrada_por_id')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['estudiante_id', 'estado'], 'idx_prorroga_estudiante_estado');
            $table->index(['estado', 'fecha_compromiso'], 'idx_prorroga_estado_compromiso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prorrogas_pago');
    }
};
