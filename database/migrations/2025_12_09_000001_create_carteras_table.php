<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla carteras — cuentas por cobrar generadas al matricular.
     *
     * Cada fila representa una cuota o cargo (matrícula, cuota mensual, etc.).
     * La cartera es inmutable: no se edita ni elimina; solo cambia de estado
     * mediante acciones controladas (aplicarPago, revertirPago, anular, acuerdoPago).
     */
    public function up(): void
    {
        Schema::create('carteras', function (Blueprint $table) {
            $table->id();

            $table->foreignId('matricula_id')->constrained('matriculas')->onDelete('restrict')
                ->comment('matrícula que origina esta deuda');

            $table->foreignId('sede_id')->constrained('sedes')->onDelete('restrict')
                ->comment('sede donde se generó la matrícula');

            $table->foreignId('estudiante_id')->constrained('users')->onDelete('restrict')
                ->comment('estudiante deudor');

            // ----------------------------------------------------------------
            // Datos del cargo
            // ----------------------------------------------------------------
            $table->unsignedInteger('numero_cuota')->default(0)
                ->comment('0 = cargo matrícula; 1..N = cuotas mensuales');

            $table->decimal('valor', 15, 2)->comment('valor total de la cuota');
            $table->decimal('saldo', 15, 2)->comment('saldo pendiente por pagar (excluye mora)');
            $table->decimal('abono', 15, 2)->default(0)->comment('total abonado hasta la fecha');
            $table->decimal('descuento', 15, 2)->default(0)->comment('descuento acumulado aplicado');
            // mora_acumulada: suma de todos los recargos por mora calculados por el cron diario.
            // Se registra también en descuento_aplicado (tipo_movimiento=sobrecargo) para historial detallado.
            $table->decimal('mora_acumulada', 15, 2)->default(0)->comment('Total de mora cobrada por vencimiento (cron diario)');
            $table->date('fecha_ultimo_cobro_mora')->nullable()->comment('Última fecha en que el cron aplicó mora; evita doble cobro en el mismo día');

            $table->date('fecha_vencimiento')->index()
                ->comment('fecha límite de pago de esta cuota');

            // ----------------------------------------------------------------
            // Estado — gestionado por HasCarteraStatus (0=Activa, 1=Abonada, 2=Cerrada, 3=Anulada, 4=EnAcuerdo)
            // ----------------------------------------------------------------
            $table->unsignedTinyInteger('status')->default(0)->index()
                ->comment('0=Activa, 1=Abonada, 2=Cerrada, 3=Anulada, 4=EnAcuerdo');

            $table->longText('observaciones')->nullable();

            $table->timestamps();

            // Índices compuestos para consultas de cartera
            $table->index(['matricula_id', 'status'], 'idx_cartera_matricula_status');
            $table->index(['estudiante_id', 'status'], 'idx_cartera_estudiante_status');
            $table->index(['sede_id', 'fecha_vencimiento'], 'idx_cartera_sede_fecha');
        });

        // Agregar FK de matriculas → lp_precios_producto ahora que ambas tablas existen.
        Schema::table('matriculas', function (Blueprint $table) {
            $table->foreign('lp_precio_producto_id')
                ->references('id')->on('lp_precios_producto')
                ->nullOnDelete();
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('carteras');

        // Revertir el FK de matriculas que se agregó aquí
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropForeign(['lp_precio_producto_id']);
        });
    }
};
