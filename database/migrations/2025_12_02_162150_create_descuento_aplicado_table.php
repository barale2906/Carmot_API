<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla descuento_aplicado que registra el historial
     * de descuentos aplicados a diferentes conceptos de pago.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('descuento_aplicado', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro de descuento aplicado');

            $table->unsignedBigInteger('descuento_id')->comment('ID del descuento aplicado');
            $table->string('concepto_tipo', 255)->comment('Tipo de concepto: matricula, cuota, pago_contado, etc.');
            $table->unsignedBigInteger('concepto_id')->comment('ID del concepto de pago');
            $table->decimal('valor_original', 15, 2)->comment('Valor original antes del descuento');
            $table->decimal('valor_descuento', 15, 2)->comment('Valor del descuento aplicado');
            $table->decimal('valor_final', 15, 2)->comment('Valor final después del descuento');
            $table->unsignedBigInteger('producto_id')->nullable()->comment('ID del producto relacionado');
            $table->unsignedBigInteger('lista_precio_id')->nullable()->comment('ID de la lista de precios relacionada');
            $table->unsignedBigInteger('sede_id')->nullable()->comment('ID de la sede donde se aplicó');
            $table->text('observaciones')->nullable()->comment('Observaciones adicionales');

            $table->timestamps();

            // Foreign keys
            $table->foreign('descuento_id')
                  ->references('id')
                  ->on('descuentos')
                  ->onDelete('restrict');

            $table->foreign('producto_id')
                  ->references('id')
                  ->on('lp_productos')
                  ->onDelete('set null');

            $table->foreign('lista_precio_id')
                  ->references('id')
                  ->on('lp_listas_precios')
                  ->onDelete('set null');

            $table->foreign('sede_id')
                  ->references('id')
                  ->on('sedes')
                  ->onDelete('set null');

            // Índices
            $table->index('descuento_id', 'idx_descuento');
            $table->index(['concepto_tipo', 'concepto_id'], 'idx_concepto');
            $table->index('producto_id', 'idx_producto');
            $table->index('lista_precio_id', 'idx_lista_precio');
            $table->index('sede_id', 'idx_sede');
            $table->index('created_at', 'idx_created_at');
        });

        // Agregar CHECK constraints
        DB::statement('ALTER TABLE descuento_aplicado ADD CONSTRAINT chk_descuento_aplicado_valor_original_positivo CHECK (valor_original >= 0)');
        DB::statement('ALTER TABLE descuento_aplicado ADD CONSTRAINT chk_descuento_aplicado_valor_descuento_positivo CHECK (valor_descuento >= 0)');
        DB::statement('ALTER TABLE descuento_aplicado ADD CONSTRAINT chk_descuento_aplicado_valor_final_positivo CHECK (valor_final >= 0)');
        DB::statement('ALTER TABLE descuento_aplicado ADD CONSTRAINT chk_descuento_aplicado_valor_final_calculado CHECK (valor_final = valor_original - valor_descuento)');
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla descuento_aplicado si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('descuento_aplicado');
    }
};

