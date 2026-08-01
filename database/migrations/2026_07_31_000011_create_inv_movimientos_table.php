<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_movimientos (líneas de cada documento de stock).
     *
     * Cada fila registra el movimiento de un producto específico.
     * Es un log inmutable; nunca se eliminan ni editan registros.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_movimientos', function (Blueprint $table) {
            $table->id()->comment('Identificador único del movimiento');

            $table->foreignId('documento_id')
                ->constrained('inv_documentos_movimiento')
                ->onDelete('restrict')
                ->comment('Documento al que pertenece esta línea');

            $table->foreignId('almacen_id')
                ->constrained('inv_almacenes')
                ->onDelete('restrict')
                ->comment('Almacén donde se aplica el movimiento');

            $table->foreignId('almacen_destino_id')
                ->nullable()
                ->constrained('inv_almacenes')
                ->onDelete('restrict')
                ->comment('Almacén destino (solo traslados)');

            $table->foreignId('producto_id')
                ->constrained('inv_productos')
                ->onDelete('restrict')
                ->comment('Producto afectado (debe ser tipo=simple)');

            $table->enum('tipo_movimiento', ['entrada', 'salida', 'traslado', 'ajuste_positivo', 'ajuste_negativo', 'devolucion'])
                ->comment('Dirección del movimiento');

            $table->unsignedInteger('cantidad')
                ->comment('Cantidad afectada — siempre positiva; el tipo indica dirección');

            $table->decimal('precio_costo', 14, 2)->nullable()
                ->comment('Precio de costo unitario (solo entradas)');

            // Morph para trazabilidad hacia entregas (se usa en Sprint 3)
            $table->string('referencia_type')->nullable()
                ->comment('Clase del origen (InvEntregaSimple, InvEntregaKitComponente…)');
            $table->unsignedBigInteger('referencia_id')->nullable()
                ->comment('ID del origen');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Usuario que registró el movimiento');

            $table->timestamp('created_at')->nullable();

            $table->index(['producto_id', 'almacen_id', 'created_at'], 'idx_inv_mov_prod_alm_fecha');
            $table->index(['referencia_type', 'referencia_id'], 'idx_inv_mov_referencia');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_movimientos');
    }
};
