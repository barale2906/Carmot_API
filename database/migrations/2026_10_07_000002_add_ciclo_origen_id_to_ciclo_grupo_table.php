<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega ciclo_origen_id al pivot ciclo_grupo para distinguir las fechas
     * calculadas por el propio ciclo (NULL) de las copiadas desde otro ciclo
     * que comparte el grupo (calendario cíclico). Sin esta marca, dos ciclos
     * que comparten un grupo se copian mutuamente y un recálculo nunca
     * propaga los cambios.
     *
     * Backfill: entre filas del mismo grupo con fechas idénticas, la del
     * ciclo con menor id se considera el origen y las demás, copias.
     */
    public function up(): void
    {
        Schema::table('ciclo_grupo', function (Blueprint $table) {
            $table->unsignedBigInteger('ciclo_origen_id')->nullable()->after('fecha_fin_grupo')
                ->comment('Ciclo del que se copiaron las fechas del grupo; NULL = calculadas por este ciclo');
            $table->foreign('ciclo_origen_id')->references('id')->on('ciclos')->nullOnDelete();
        });

        $copias = DB::table('ciclo_grupo as copia')
            ->join('ciclo_grupo as origen', function ($join) {
                $join->on('origen.grupo_id', '=', 'copia.grupo_id')
                    ->on('origen.fecha_inicio_grupo', '=', 'copia.fecha_inicio_grupo')
                    ->on('origen.fecha_fin_grupo', '=', 'copia.fecha_fin_grupo')
                    ->on('origen.ciclo_id', '<', 'copia.ciclo_id');
            })
            ->groupBy('copia.id')
            ->selectRaw('copia.id as id, MIN(origen.ciclo_id) as origen_id')
            ->get();

        foreach ($copias as $copia) {
            DB::table('ciclo_grupo')->where('id', $copia->id)->update(['ciclo_origen_id' => $copia->origen_id]);
        }
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('ciclo_grupo', function (Blueprint $table) {
            $table->dropForeign(['ciclo_origen_id']);
            $table->dropColumn('ciclo_origen_id');
        });
    }
};
