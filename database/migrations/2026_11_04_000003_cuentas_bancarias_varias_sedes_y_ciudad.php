<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una cuenta bancaria del instituto puede recibir pagos de varias sedes y/o de todas
     * las sedes de una ciudad (población): se reemplaza sede_id por la tabla pivote
     * cuenta_bancaria_sede y se agrega poblacion_id. Las cuentas existentes conservan su sede.
     */
    public function up(): void
    {
        Schema::create('cuenta_bancaria_sede', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_bancaria_id')->constrained('cuentas_bancarias')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['cuenta_bancaria_id', 'sede_id']);
        });

        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->foreignId('poblacion_id')->nullable()->after('id')
                ->constrained('poblacions')->restrictOnDelete()
                ->comment('Ciudad: la cuenta aplica a todas sus sedes');
        });

        $ahora = now();
        DB::table('cuentas_bancarias')->whereNotNull('sede_id')->orderBy('id')->get(['id', 'sede_id'])
            ->each(fn ($c) => DB::table('cuenta_bancaria_sede')->insert([
                'cuenta_bancaria_id' => $c->id,
                'sede_id' => $c->sede_id,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]));

        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropForeign(['sede_id']);
            $table->dropIndex(['sede_id', 'status']);
            $table->dropColumn('sede_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->foreignId('sede_id')->nullable()->after('id')->constrained('sedes')->restrictOnDelete();
            $table->index(['sede_id', 'status']);
        });

        DB::table('cuenta_bancaria_sede')->orderBy('id')->get()->groupBy('cuenta_bancaria_id')
            ->each(fn ($filas, $cuentaId) => DB::table('cuentas_bancarias')
                ->where('id', $cuentaId)->update(['sede_id' => $filas->first()->sede_id]));

        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('poblacion_id');
        });

        Schema::dropIfExists('cuenta_bancaria_sede');
    }
};
