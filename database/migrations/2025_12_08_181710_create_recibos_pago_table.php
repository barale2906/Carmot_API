<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla recibos_pago que almacena todos los recibos de pago
     * generados en el sistema financiero.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('recibos_pago', function (Blueprint $table) {
            $table->id()->comment('Identificador único del recibo de pago');

            $table->string('numero_recibo', 50)->unique()->comment('Número completo del recibo (prefijo + consecutivo)');
            $table->unsignedInteger('consecutivo')->comment('Consecutivo por sede y origen');
            $table->string('prefijo', 10)->comment('Prefijo de la sede según origen');
            $table->integer('origen')->default(1)->comment('Tipo de origen (0=Inventarios, 1=Académico)');
            $table->date('fecha_recibo')->comment('Fecha del recibo');
            $table->dateTime('fecha_transaccion')->comment('Momento en que ingresó el dinero');
            $table->decimal('valor_total', 15, 2)->comment('Valor total del recibo');
            $table->decimal('descuento_total', 15, 2)->default(0)->comment('Descuento total aplicado');
            $table->string('banco', 100)->nullable()->comment('Banco donde ingresó el dinero');
            $table->integer('status')->default(0)->comment('Estado del recibo (0=En proceso, 1=Creado, 2=Cerrado, 3=Anulado)');
            $table->unsignedInteger('cierre')->nullable()->comment('Número de cierre de caja');

            $table->foreignId('sede_id')->constrained('sedes')->onDelete('restrict')->comment('ID de la sede que genera el recibo');
            $table->foreignId('estudiante_id')->nullable()->constrained('users')->onDelete('restrict')->comment('ID del estudiante (User)');
            $table->foreignId('cajero_id')->constrained('users')->onDelete('restrict')->comment('ID del cajero (User) que genera el recibo');
            $table->foreignId('matricula_id')->nullable()->constrained('matriculas')->onDelete('restrict')->comment('ID de la matrícula asociada');

            $table->softDeletes();
            $table->timestamps();

            // Índices
            $table->index('numero_recibo', 'idx_numero_recibo');
            $table->index(['sede_id', 'origen', 'consecutivo'], 'idx_sede_origen_consecutivo');
            $table->index('fecha_recibo', 'idx_fecha_recibo');
            $table->index('status', 'idx_status');
            $table->index('cierre', 'idx_cierre');
            $table->index('estudiante_id', 'idx_estudiante');
            $table->index('cajero_id', 'idx_cajero');
            $table->index('origen', 'idx_origen');

            // Nota: Las validaciones de valores positivos y cálculos se realizan
            // a nivel de aplicación en los Form Requests y modelos
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla recibos_pago si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('recibos_pago');
    }
};

