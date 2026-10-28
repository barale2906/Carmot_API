<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La agenda deja de ser solo de citas: programa la próxima acción con el lead (llamada,
     * visita, mensaje...). Las agendas existentes eran citas presenciales, por eso el default es visita.
     */
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            $table->string('tipo', 20)->default('visita')->after('jornada')->comment('llamada, visita, mensaje u otro');
            $table->text('nota')->nullable()->after('tipo')->comment('qué se debe hacer en la acción programada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'nota']);
        });
    }
};
