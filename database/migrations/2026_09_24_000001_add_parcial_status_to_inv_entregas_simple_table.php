<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el estado 'parcial' a inv_entregas_simple.
 *
 * Permite entregar parte de la cantidad vendida cuando el stock no alcanza para
 * cubrir todo el ítem, dejando el resto como pendiente y su necesidad de compra
 * activa. Antes solo existían 'pendiente' y 'entregado', lo que obligaba a
 * esperar stock completo para poder despachar algo.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE inv_entregas_simple
             MODIFY COLUMN status ENUM('pendiente','parcial','entregado')
             NOT NULL DEFAULT 'pendiente'"
        );
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        DB::statement("UPDATE inv_entregas_simple SET status = 'pendiente' WHERE status = 'parcial'");

        DB::statement(
            "ALTER TABLE inv_entregas_simple
             MODIFY COLUMN status ENUM('pendiente','entregado')
             NOT NULL DEFAULT 'pendiente'"
        );
    }
};
