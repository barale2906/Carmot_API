<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_kit_componentes que define qué productos
     * (o grupos de variantes) componen un kit y en qué cantidad.
     *
     * Nota: grupo_producto_id apunta a un producto de tipo 'grupo' o 'simple'.
     * La variante concreta se selecciona al momento de la entrega.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_kit_componentes', function (Blueprint $table) {
            $table->id()->comment('Identificador único del componente de kit');

            $table->foreignId('kit_id')
                ->constrained('inv_productos')
                ->onDelete('cascade')
                ->comment('Kit al que pertenece este componente');

            $table->foreignId('grupo_producto_id')
                ->constrained('inv_productos')
                ->onDelete('restrict')
                ->comment('Producto (simple) o grupo (variantes) que compone el kit');

            $table->unsignedSmallInteger('cantidad')
                ->default(1)
                ->comment('Cantidad del componente por unidad de kit');

            $table->unsignedSmallInteger('orden')
                ->default(0)
                ->comment('Orden de presentación dentro del kit');

            $table->timestamps();

            $table->unique(['kit_id', 'grupo_producto_id'], 'uq_kit_componente');
            $table->index('kit_id', 'idx_inv_kc_kit');
        });
    }

    /**
     * Elimina la tabla inv_kit_componentes.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_kit_componentes');
    }
};
