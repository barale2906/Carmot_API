<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda en cada seguimiento el estado en que quedó el lead, para leer la evolución en la bitácora.
     * Los seguimientos anteriores quedan en null: su estado histórico no se conoce.
     */
    public function up(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            $table->integer('estado_referido')->nullable()->after('seguimiento')->comment('estado del lead tras el seguimiento: 0 creado, 1 interesado, 2 pendiente, 3 matriculado, 4 declinado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            $table->dropColumn('estado_referido');
        });
    }
};
