<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la marca `entrega_completa` a inv_pedido_items.
 *
 * Registra la preferencia del comprador de recibir el producto de una sola vez.
 * Mientras esté activa, el ítem nunca se entrega parcialmente: si el stock no
 * cubre toda la cantidad, no se descarga nada y queda pendiente completo.
 *
 * Es distinto de excluir el ítem de la entrega inmediata (`items[].entregar`),
 * que es una decisión puntual del momento de facturar: esta marca persiste y
 * sigue protegiendo al ítem en las entregas posteriores.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::table('inv_pedido_items', function (Blueprint $table) {
            $table->boolean('entrega_completa')
                ->default(false)
                ->after('cantidad')
                ->comment('El comprador exige recibir el ítem completo: no admite entregas parciales');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('inv_pedido_items', function (Blueprint $table) {
            $table->dropColumn('entrega_completa');
        });
    }
};
