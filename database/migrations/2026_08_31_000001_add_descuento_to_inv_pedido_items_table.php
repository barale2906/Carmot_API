<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega campos de descuento a inv_pedido_items para registrar el precio de lista
     * original y el descuento aplicado por unidad al momento de la venta.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('inv_pedido_items', function (Blueprint $table) {
            $table->decimal('precio_lista', 14, 2)
                ->nullable()
                ->after('cantidad')
                ->comment('Precio de lista antes de descuento — null en ventas anteriores a esta migración');

            $table->decimal('descuento_unitario', 14, 2)
                ->default(0)
                ->after('precio_lista')
                ->comment('Descuento aplicado por unidad (precio_lista - precio_unitario)');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::table('inv_pedido_items', function (Blueprint $table) {
            $table->dropColumn(['precio_lista', 'descuento_unitario']);
        });
    }
};
