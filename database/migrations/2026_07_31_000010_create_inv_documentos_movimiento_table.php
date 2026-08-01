<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_documentos_movimiento (cabecera de cada transacción de stock).
     *
     * Un documento puede tener múltiples líneas (inv_movimientos).
     * Los documentos son inmutables una vez confirmados; solo se pueden anular.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_documentos_movimiento', function (Blueprint $table) {
            $table->id()->comment('Identificador único del documento');

            $table->string('numero_documento', 30)->unique()
                ->comment('Número secuencial por tipo (ej. ENT-2026-000001)');

            $table->enum('tipo_documento', ['entrada', 'salida', 'traslado', 'ajuste', 'devolucion'])
                ->comment('Tipo de operación: entrada/salida/traslado/ajuste/devolucion');

            $table->foreignId('almacen_id')
                ->constrained('inv_almacenes')
                ->onDelete('restrict')
                ->comment('Almacén origen del movimiento');

            $table->foreignId('almacen_destino_id')
                ->nullable()
                ->constrained('inv_almacenes')
                ->onDelete('restrict')
                ->comment('Almacén destino (solo traslados)');

            $table->foreignId('proveedor_id')
                ->nullable()
                ->constrained('inv_proveedores')
                ->onDelete('restrict')
                ->comment('Proveedor asociado (solo entradas de compra)');

            // FK blanda a inv_pedidos (se agrega constraint en Sprint 3)
            $table->unsignedBigInteger('pedido_id')->nullable()
                ->comment('Pedido de venta asociado (solo salidas por venta)');

            $table->text('motivo')->nullable()
                ->comment('Descripción o justificación del movimiento');

            $table->enum('status', ['borrador', 'confirmado', 'anulado'])->default('confirmado')
                ->comment('Estado del documento');

            $table->string('motivo_anulacion', 500)->nullable()
                ->comment('Obligatorio al anular el documento');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Usuario que registró el documento');

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['almacen_id', 'tipo_documento', 'created_at'], 'idx_inv_doc_almacen_tipo');
            $table->index('status', 'idx_inv_doc_status');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_documentos_movimiento');
    }
};
