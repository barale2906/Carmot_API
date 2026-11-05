<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la cuenta del instituto a la que entró una transferencia. Los registros
     * anteriores conservan solo banco_id (banco de origen informado en su momento).
     */
    public function up(): void
    {
        Schema::table('recibo_pago_medio_pago', function (Blueprint $table) {
            $table->foreignId('cuenta_bancaria_id')->nullable()->after('banco_id')
                ->constrained('cuentas_bancarias')->restrictOnDelete()
                ->comment('Cuenta del instituto que recibió la transferencia');
        });
    }

    public function down(): void
    {
        Schema::table('recibo_pago_medio_pago', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_bancaria_id');
        });
    }
};
