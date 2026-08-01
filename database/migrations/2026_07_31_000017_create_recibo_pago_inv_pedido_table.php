<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla recibo_pago_inv_pedido — vincula cada recibo/abono al pedido de inventario.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('recibo_pago_inv_pedido', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recibo_pago_id')
                ->constrained('recibos_pago')
                ->onDelete('restrict');

            $table->foreignId('pedido_id')
                ->constrained('inv_pedidos')
                ->onDelete('restrict');

            $table->decimal('monto_abonado', 14, 2)
                ->comment('Cuánto aporta este recibo al saldo del pedido');

            $table->timestamps();

            $table->unique(['recibo_pago_id', 'pedido_id'], 'uq_recibo_pedido');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('recibo_pago_inv_pedido');
    }
};
