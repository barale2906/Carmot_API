<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula la matrícula con la programación elegida (de ella sale la jornada
     * que determina los descuentos aplicables).
     */
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->foreignId('programacion_id')
                ->nullable()
                ->after('ciclo_id')
                ->constrained('programacions')
                ->nullOnDelete()
                ->comment('programación elegida al matricular (define la jornada)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropForeign(['programacion_id']);
            $table->dropColumn('programacion_id');
        });
    }
};
