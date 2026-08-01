<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_categorias para clasificar los productos del inventario.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_categorias', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la categoría');
            $table->string('nombre', 150)->comment('Nombre descriptivo de la categoría');
            $table->text('descripcion')->nullable()->comment('Descripción opcional');
            $table->tinyInteger('status')->default(1)->comment('Estado: 0=Inactivo, 1=Activo');

            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_inv_cat_status');
            $table->index('nombre', 'idx_inv_cat_nombre');
        });
    }

    /**
     * Elimina la tabla inv_categorias.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_categorias');
    }
};
