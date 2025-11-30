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
     * Crea la tabla lp_precios_producto que define los precios de cada producto
     * dentro de una lista de precios, incluyendo precios de contado y financiación.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lp_precios_producto', function (Blueprint $table) {
            $table->id()->comment('Identificador único del precio de producto');
            
            $table->unsignedBigInteger('lista_precio_id')->comment('ID de la lista de precios');
            $table->unsignedBigInteger('producto_id')->comment('ID del producto');
            
            // Precio de contado
            $table->decimal('precio_contado', 15, 2)->default(0.00)->comment('Precio para pago de contado');
            
            // Financiación (solo para productos financiables)
            $table->decimal('precio_total', 15, 2)->nullable()->comment('Precio total del producto (para financiación)');
            $table->decimal('matricula', 15, 2)->default(0.00)->comment('Valor de la matrícula (obligatorio para cursos y módulos, puede ser 0)');
            $table->integer('numero_cuotas')->nullable()->comment('Número de cuotas');
            $table->decimal('valor_cuota', 15, 2)->nullable()->comment('Valor calculado de cada cuota (redondeado al 100) - se calcula al crear/actualizar');
            
            // Metadatos
            $table->text('observaciones')->nullable()->comment('Observaciones adicionales');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('lista_precio_id')
                  ->references('id')
                  ->on('lp_listas_precios')
                  ->onDelete('cascade');
            
            $table->foreign('producto_id')
                  ->references('id')
                  ->on('lp_productos')
                  ->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['lista_precio_id', 'producto_id'], 'uk_lista_producto');
            
            // Índices
            $table->index('lista_precio_id', 'idx_lista_precio');
            $table->index('producto_id', 'idx_producto');
        });
        
        // Agregar CHECK constraints para validaciones
        DB::statement('ALTER TABLE lp_precios_producto ADD CONSTRAINT chk_precio_contado_positivo CHECK (precio_contado >= 0)');
        DB::statement('ALTER TABLE lp_precios_producto ADD CONSTRAINT chk_precio_total_positivo CHECK (precio_total IS NULL OR precio_total >= 0)');
        DB::statement('ALTER TABLE lp_precios_producto ADD CONSTRAINT chk_matricula_positivo CHECK (matricula >= 0)');
        DB::statement('ALTER TABLE lp_precios_producto ADD CONSTRAINT chk_numero_cuotas_positivo CHECK (numero_cuotas IS NULL OR numero_cuotas > 0)');
        DB::statement('ALTER TABLE lp_precios_producto ADD CONSTRAINT chk_valor_cuota_positivo CHECK (valor_cuota IS NULL OR valor_cuota >= 0)');
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla lp_precios_producto si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('lp_precios_producto');
    }
};
