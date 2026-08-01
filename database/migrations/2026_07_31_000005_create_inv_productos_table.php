<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_productos con soporte para tres tipos:
     *  - simple: producto individual con stock propio.
     *  - kit: compuesto de otros productos (simples o grupos); stock calculado.
     *  - grupo: agrupa variantes de un producto (ej. "Camisa"); no tiene stock ni precio propio.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_productos', function (Blueprint $table) {
            $table->id()->comment('Identificador único del producto');
            $table->string('codigo', 100)->unique()->comment('Código único interno o de barras');
            $table->string('nombre', 255)->comment('Nombre del producto');
            $table->text('descripcion')->nullable()->comment('Descripción opcional');
            $table->enum('tipo', ['simple', 'kit', 'grupo'])
                ->default('simple')
                ->comment('simple=con stock, kit=compuesto, grupo=ancla de variantes');
            $table->string('imagen', 500)->nullable()->comment('Ruta relativa de la imagen');
            $table->tinyInteger('status')->default(1)->comment('Estado: 0=Inactivo, 1=Activo');

            $table->foreignId('categoria_id')
                ->nullable()
                ->constrained('inv_categorias')
                ->onDelete('restrict')
                ->comment('Categoría del producto');

            $table->foreignId('unidad_medida_id')
                ->nullable()
                ->constrained('inv_unidades_medida')
                ->onDelete('restrict')
                ->comment('Unidad de medida del producto');

            // FK auto-referencial: variantes apuntan a su grupo padre (tipo='grupo')
            $table->unsignedBigInteger('producto_padre_id')
                ->nullable()
                ->comment('FK al grupo padre cuando este producto es una variante');

            $table->foreign('producto_padre_id')
                ->references('id')
                ->on('inv_productos')
                ->onDelete('restrict');

            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo', 'idx_inv_prod_tipo');
            $table->index('status', 'idx_inv_prod_status');
            $table->index('categoria_id', 'idx_inv_prod_categoria');
            $table->index('producto_padre_id', 'idx_inv_prod_padre');
        });
    }

    /**
     * Elimina la tabla inv_productos.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_productos');
    }
};
