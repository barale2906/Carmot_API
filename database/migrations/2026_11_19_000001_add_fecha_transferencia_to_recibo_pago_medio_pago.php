<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la fecha en que el cliente realizó la transferencia bancaria.
     * Es nullable porque los medios que no son transferencia (y los recibos anteriores) no la tienen.
     */
    public function up(): void
    {
        Schema::table('recibo_pago_medio_pago', function (Blueprint $table) {
            $table->date('fecha_transferencia')->nullable()->after('numero_transaccion')
                ->comment('Fecha en que se realizó la transferencia bancaria');
        });
    }

    public function down(): void
    {
        Schema::table('recibo_pago_medio_pago', function (Blueprint $table) {
            $table->dropColumn('fecha_transferencia');
        });
    }
};
