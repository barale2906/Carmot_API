<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogos del libro diario: tipos de movimiento manual e impuestos/retenciones.
     */
    public function up(): void
    {
        Schema::create('ld_tipos_movimiento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->unsignedTinyInteger('clase')->comment('1=Egreso, 2=Otro ingreso');
            $table->string('descripcion', 255)->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('0=Inactivo, 1=Activo');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clase', 'status']);
        });

        Schema::create('ld_impuestos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->decimal('porcentaje', 6, 3)->comment('porcentaje sugerido sobre el subtotal');
            $table->unsignedTinyInteger('efecto')->comment('1=Suma al total (IVA), 2=Resta del total (retención)');
            $table->unsignedTinyInteger('status')->default(1)->comment('0=Inactivo, 1=Activo');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('ld_impuestos');
        Schema::dropIfExists('ld_tipos_movimiento');
    }
};
