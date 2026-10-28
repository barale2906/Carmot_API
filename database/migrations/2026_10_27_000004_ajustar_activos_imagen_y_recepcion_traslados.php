<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Activos fijos:
     * - Se elimina el código interno: el activo se identifica por su ID.
     * - Se agrega la imagen del activo (ruta en el disco público).
     * - El traslado pasa a tener dos pasos: se envía (pendiente) y la sede de destino
     *   registra la recepción; solo entonces el activo cambia de ubicación. También se
     *   puede cancelar mientras esté pendiente. Los traslados anteriores quedan recibidos.
     */
    public function up(): void
    {
        Schema::table('act_activos', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn('codigo');
            $table->string('imagen')->nullable()->after('descripcion')->comment('ruta de la imagen en el disco public');
        });

        Schema::table('act_movimientos', function (Blueprint $table) {
            $table->string('estado_traslado', 20)->nullable()->after('tipo')->comment('solo traslados: pendiente | recibido | cancelado');
            $table->foreignId('responsable_destino_id')->nullable()->after('area_destino_id')
                ->constrained('users')->nullOnDelete()->comment('responsable a asignar al recibir; null conserva el actual');
            $table->date('fecha_recepcion')->nullable()->after('usuario_id');
            $table->foreignId('recibido_por_id')->nullable()->after('fecha_recepcion')->constrained('users')->nullOnDelete();
            $table->text('observaciones_recepcion')->nullable()->after('recibido_por_id')->comment('estado en que llegó el activo');
            $table->foreignId('cancelado_por_id')->nullable()->after('observaciones_recepcion')->constrained('users')->nullOnDelete();
            $table->text('motivo_cancelacion')->nullable()->after('cancelado_por_id');

            $table->index(['tipo', 'estado_traslado'], 'idx_act_movimientos_tipo_estado');
        });

        DB::table('act_movimientos')
            ->where('tipo', 'traslado')
            ->update([
                'estado_traslado' => 'recibido',
                'fecha_recepcion' => DB::raw('fecha'),
                'recibido_por_id' => DB::raw('usuario_id'),
            ]);
    }

    /**
     * Revierte los cambios. Los códigos eliminados no se recuperan: se regeneran a partir del ID.
     */
    public function down(): void
    {
        Schema::table('act_movimientos', function (Blueprint $table) {
            $table->dropIndex('idx_act_movimientos_tipo_estado');
            $table->dropConstrainedForeignId('responsable_destino_id');
            $table->dropConstrainedForeignId('recibido_por_id');
            $table->dropConstrainedForeignId('cancelado_por_id');
            $table->dropColumn(['estado_traslado', 'fecha_recepcion', 'observaciones_recepcion', 'motivo_cancelacion']);
        });

        Schema::table('act_activos', function (Blueprint $table) {
            $table->dropColumn('imagen');
            $table->string('codigo', 40)->nullable()->after('id');
        });

        DB::table('act_activos')->update(['codigo' => DB::raw("CONCAT('ACT-', LPAD(id, 6, '0'))")]);

        Schema::table('act_activos', function (Blueprint $table) {
            $table->unique('codigo');
        });
    }
};
