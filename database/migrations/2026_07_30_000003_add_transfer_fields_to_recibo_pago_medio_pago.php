<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega banco_id, numero_transaccion y comprobante_path a la tabla de medios de pago.
     * numero_transaccion tiene unicidad global: ningún banco genera el mismo número dos veces.
     */
    public function up(): void
    {
        Schema::table('recibo_pago_medio_pago', function (Blueprint $table) {
            $table->foreignId('banco_id')->nullable()->after('banco')
                ->constrained('bancos')->onDelete('restrict')
                ->comment('Banco desde donde se realizó la transferencia');
            $table->string('numero_transaccion', 100)->nullable()->unique()->after('banco_id')
                ->comment('Número único de transacción bancaria (único global)');
            $table->string('comprobante_path', 500)->nullable()->after('numero_transaccion')
                ->comment('Ruta relativa del comprobante en storage/app/public');
        });
    }

    public function down(): void
    {
        Schema::table('recibo_pago_medio_pago', function (Blueprint $table) {
            $table->dropForeign(['banco_id']);
            $table->dropColumn(['banco_id', 'numero_transaccion', 'comprobante_path']);
        });
    }
};
