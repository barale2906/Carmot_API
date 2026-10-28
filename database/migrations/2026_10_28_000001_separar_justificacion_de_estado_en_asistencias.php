<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La justificación deja de ser un estado de asistencia: quien no llegó queda "ausente"
 * (la falla se mantiene) y, aparte, puede tener la falla justificada con su motivo.
 * Los registros "justificado" pasan a ausente con justificación (el motivo era la
 * observación). Como la justificación ya no cuenta como asistencia, la regla
 * `aplicar_justificaciones` de la configuración desaparece.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->boolean('justificada')->default(false)->after('estado')->comment('la falla o tardanza tiene justificación (no la borra)');
            $table->string('justificacion', 500)->nullable()->after('justificada')->comment('motivo de la justificación');
        });

        DB::table('asistencias')->where('estado', 'justificado')->update([
            'estado'        => 'ausente',
            'justificada'   => true,
            'justificacion' => DB::raw('LEFT(observaciones, 500)'),
            'observaciones' => null,
        ]);

        DB::statement("ALTER TABLE asistencias MODIFY estado ENUM('presente', 'ausente', 'tardanza') NOT NULL DEFAULT 'presente' COMMENT 'estado de la asistencia'");

        Schema::table('asistencia_configuraciones', function (Blueprint $table) {
            $table->dropColumn('aplicar_justificaciones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asistencia_configuraciones', function (Blueprint $table) {
            $table->boolean('aplicar_justificaciones')->default(true)->after('horas_minimas')->comment('si las ausencias justificadas cuentan para el mínimo');
        });

        DB::statement("ALTER TABLE asistencias MODIFY estado ENUM('presente', 'ausente', 'justificado', 'tardanza') NOT NULL DEFAULT 'presente' COMMENT 'estado de la asistencia'");

        DB::table('asistencias')->where('estado', 'ausente')->where('justificada', true)->update([
            'estado'        => 'justificado',
            'observaciones' => DB::raw('justificacion'),
        ]);

        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropColumn(['justificada', 'justificacion']);
        });
    }
};
