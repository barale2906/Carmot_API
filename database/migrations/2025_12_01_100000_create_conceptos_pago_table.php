<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla conceptos_pago que contiene los diferentes conceptos
     * por los cuales se van a recibir pagos (matrícula, cobros adicionales,
     * recargos por pago con tarjeta, pagos por acuerdo de pago, etc.).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('conceptos_pago', function (Blueprint $table) {
            $table->id()->comment('Identificador único del concepto de pago');

            $table->string('nombre', 255)->comment('Nombre del concepto de pago');
            $table->integer('tipo')->comment('Tipo del concepto: 0=Cartera, 1=Financiero, 2=Inventario, 3=Otro (índice del array TIPOS_DEFAULT)');
            $table->decimal('valor', 10, 2)->comment('Valor del concepto de pago (hasta 2 decimales)');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('nombre', 'idx_nombre');
            $table->index('tipo', 'idx_tipo');
            $table->index('valor', 'idx_valor');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla conceptos_pago si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('conceptos_pago');
    }
};

