<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indica a qué cuota (cartera) y rubro corresponde cada descuento aplicado en un recibo.
     */
    public function up(): void
    {
        Schema::table('recibo_pago_descuento', function (Blueprint $table) {
            $table->foreignId('cartera_id')
                ->nullable()
                ->after('descuento_id')
                ->constrained('carteras')
                ->nullOnDelete()
                ->comment('cuota de cartera sobre la que se aplicó el descuento');
            $table->string('rubro', 20)
                ->nullable()
                ->after('cartera_id')
                ->comment('matricula | cuota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recibo_pago_descuento', function (Blueprint $table) {
            $table->dropForeign(['cartera_id']);
            $table->dropColumn(['cartera_id', 'rubro']);
        });
    }
};
