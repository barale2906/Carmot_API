<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     */
    public function up(): void
    {
        Schema::table('referidos', function (Blueprint $table) {
            $table->softDeletes()->comment('Campo para soft delete - fecha de eliminación');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::table('referidos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
