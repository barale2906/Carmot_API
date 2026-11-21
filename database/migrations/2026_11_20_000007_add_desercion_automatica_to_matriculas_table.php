<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->boolean('desercion_automatica')->default(false)->after('fecha_estado_academico')
                ->comment('Desertada por el sistema (2 fallas seguidas): vuelve a Activo al registrarle asistencia');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropColumn('desercion_automatica');
        });
    }
};
