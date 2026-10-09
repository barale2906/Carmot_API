<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el tipo de discapacidad (catálogo) y la descripción libre de la enfermedad prioritaria.
     */
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->string('tipo_discapacidad', 40)->nullable()->after('discapacidad')
                ->comment('tipo de discapacidad (catálogo Matricula::TIPOS_DISCAPACIDAD); solo si discapacidad = true');
            $table->string('enfermedad_detalle')->nullable()->after('enfermedad_prioritaria')
                ->comment('cuál es la enfermedad de atención prioritaria; solo si enfermedad_prioritaria = true');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropColumn(['tipo_discapacidad', 'enfermedad_detalle']);
        });
    }
};
