<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registra quién, cuándo y por qué se canceló o anuló un pedido de inventario.
     */
    public function up(): void
    {
        Schema::table('inv_pedidos', function (Blueprint $table) {
            $table->text('motivo_cancelacion')->nullable()->after('observaciones')
                ->comment('motivo obligatorio al cancelar o anular el pedido');
            $table->foreignId('cancelado_por_id')->nullable()->after('motivo_cancelacion')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_cancelacion')->nullable()->after('cancelado_por_id');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('inv_pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelado_por_id');
            $table->dropColumn(['motivo_cancelacion', 'fecha_cancelacion']);
        });
    }
};
