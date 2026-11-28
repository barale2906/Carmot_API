<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de traslados de una matrícula (cambio de programación, de curso, de sede o de
     * ambos). La matrícula se conserva y solo cambia su ubicación: cada fila deja el origen, el
     * destino, lo financiero y el resultado de la nivelación para poder hacerle seguimiento.
     */
    public function up(): void
    {
        Schema::create('matricula_traslados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->string('tipo', 20)->comment('programacion | curso | sede | sede_curso (se deduce de origen y destino)');
            $table->date('fecha')->comment('fecha desde la que rige el traslado');

            $table->foreignId('sede_origen_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('curso_origen_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('ciclo_origen_id')->constrained('ciclos')->restrictOnDelete();
            $table->foreignId('programacion_origen_id')->nullable()->constrained('programacions')->nullOnDelete();
            $table->foreignId('lp_origen_id')->nullable()->constrained('lp_precios_producto')->nullOnDelete();

            $table->foreignId('sede_destino_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('curso_destino_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('ciclo_destino_id')->constrained('ciclos')->restrictOnDelete();
            $table->foreignId('programacion_destino_id')->nullable()->constrained('programacions')->nullOnDelete();
            $table->foreignId('lp_destino_id')->nullable()->constrained('lp_precios_producto')->nullOnDelete();

            $table->text('motivo')->comment('motivo del traslado');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete()->comment('usuario que registró el traslado');

            $table->decimal('valor_pagado', 15, 2)->default(0)->comment('lo pagado por la matrícula hasta el traslado; no se traslada');
            $table->decimal('saldo_anulado', 15, 2)->default(0)->comment('saldo pendiente de la sede de origen que se cancela');
            $table->decimal('saldo_nuevo', 15, 2)->default(0)->comment('saldo acordado que se genera en la sede de destino');
            $table->decimal('costo_traslado', 15, 2)->default(0)->comment('cobro por el traslado (concepto de pago Traslado); 0 si no se cobra');
            $table->json('carteras')->nullable()->comment('ids de carteras: anuladas, cerradas y creadas por el traslado');

            $table->unsignedInteger('reposiciones_canceladas')->default(0)->comment('reposiciones pendientes del ciclo de origen que se cancelaron');

            $table->json('nivelacion')->nullable()->comment('resultado de cruzar la planeación de temas entre ciclo origen y destino');
            $table->boolean('nivelacion_pendiente')->default(false)->comment('faltan temas por dictar al estudiante');
            $table->timestamp('nivelacion_resuelta_at')->nullable();
            $table->foreignId('nivelacion_resuelta_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nivelacion_observaciones')->nullable();

            $table->timestamps();

            $table->index(['matricula_id', 'fecha'], 'idx_matricula_traslados_matricula_fecha');
            $table->index('nivelacion_pendiente', 'idx_matricula_traslados_nivelacion');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('matricula_traslados');
    }
};
