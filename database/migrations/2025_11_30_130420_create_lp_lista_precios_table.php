<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla lp_listas_precios que define las listas de precios
     * con su vigencia y alcance geográfico.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lp_listas_precios', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la lista de precios');

            $table->string('nombre', 255)->comment('Nombre descriptivo de la lista');
            $table->string('codigo', 100)->nullable()->unique()->comment('Código único de la lista');
            $table->date('fecha_inicio')->comment('Fecha de inicio de vigencia');
            $table->date('fecha_fin')->comment('Fecha de fin de vigencia');
            $table->text('descripcion')->nullable()->comment('Descripción de la lista de precios');
            $table->tinyInteger('status')->default(1)->comment('0: Inactiva, 1: En Proceso, 2: Aprobada, 3: Activa');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['fecha_inicio', 'fecha_fin'], 'idx_fechas');
            $table->index('status', 'idx_status');
            $table->index('codigo', 'idx_codigo');
        });

        // Agregar CHECK constraint para validar que fecha_fin >= fecha_inicio
        DB::statement('ALTER TABLE lp_listas_precios ADD CONSTRAINT chk_fecha_fin_mayor_igual_inicio CHECK (fecha_fin >= fecha_inicio)');
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla lp_listas_precios si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('lp_listas_precios');
    }
};
