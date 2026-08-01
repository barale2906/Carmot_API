<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_pedidos — cabecera de una venta de inventario a un estudiante.
     *
     * El campo valor_total es inmutable: captura el precio vigente al momento del pedido.
     * Los abonos sucesivos reducen el saldo; cuando llega a 0 se dispara la entrega.
     *
     * Status: activo → pagado → entregando → entregado | cancelado
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_pedidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('estudiante_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Estudiante que compra');

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->onDelete('restrict');

            $table->foreignId('almacen_id')
                ->constrained('inv_almacenes')
                ->onDelete('restrict')
                ->comment('Almacén de despacho seleccionado por el cajero');

            $table->foreignId('cajero_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Usuario cajero que abrió el pedido');

            $table->decimal('valor_total', 14, 2)
                ->comment('Suma de ítems al momento del pedido — inmutable');

            $table->decimal('abono_acumulado', 14, 2)->default(0)
                ->comment('Suma de todos los recibos registrados');

            $table->decimal('saldo', 14, 2)
                ->comment('valor_total − abono_acumulado');

            $table->enum('status', ['activo', 'pagado', 'entregando', 'entregado', 'cancelado'])
                ->default('activo');

            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index(['estudiante_id', 'status'], 'idx_inv_pedidos_estudiante_status');
            $table->index(['sede_id', 'status'],       'idx_inv_pedidos_sede_status');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_pedidos');
    }
};
