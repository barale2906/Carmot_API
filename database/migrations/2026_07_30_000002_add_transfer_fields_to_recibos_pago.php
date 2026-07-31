<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega los campos necesarios para el flujo de aprobación de transferencias.
     * También hace nullable numero_recibo/consecutivo/prefijo porque para transferencias
     * estos se asignan en el momento de aprobación, no de creación.
     * Incluye motivo_anulacion que faltaba en el schema original.
     */
    public function up(): void
    {
        Schema::table('recibos_pago', function (Blueprint $table) {
            // motivo_anulacion no existe en el schema actual aunque el modelo lo referencia
            if (! Schema::hasColumn('recibos_pago', 'motivo_anulacion')) {
                $table->string('motivo_anulacion', 500)->nullable()
                    ->comment('Motivo obligatorio al anular el recibo');
            }

            $table->dateTime('fecha_aprobacion')->nullable()
                ->comment('Fecha en que el validador aprueba el recibo por transferencia');
            $table->unsignedBigInteger('aprobado_por_id')->nullable()
                ->comment('Usuario que aprobó el recibo por transferencia');
            $table->string('motivo_rechazo', 500)->nullable()
                ->comment('Razón del rechazo registrada por el validador');
            $table->boolean('aplicar_descuento')->default(false)
                ->comment('Indica si el cajero solicitó aplicar descuento de pronto pago');

            $table->foreign('aprobado_por_id')->references('id')->on('users')->onDelete('restrict');
        });

        // Cambiar a nullable por separado para evitar conflictos con el alter anterior
        Schema::table('recibos_pago', function (Blueprint $table) {
            $table->string('numero_recibo', 50)->nullable()->change();
            $table->unsignedInteger('consecutivo')->nullable()->change();
            $table->string('prefijo', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recibos_pago', function (Blueprint $table) {
            $table->dropForeign(['aprobado_por_id']);
            $table->dropColumn(['fecha_aprobacion', 'aprobado_por_id', 'motivo_rechazo', 'aplicar_descuento']);
            if (Schema::hasColumn('recibos_pago', 'motivo_anulacion')) {
                $table->dropColumn('motivo_anulacion');
            }
        });
    }
};
