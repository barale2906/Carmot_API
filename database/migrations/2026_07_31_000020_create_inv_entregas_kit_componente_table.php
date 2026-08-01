<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_entregas_kit_componente — estado de entrega por componente de kit.
     *
     * Una fila por componente × entrega de kit.
     * Si el componente es tipo=grupo, el cajero elige la variante (producto_entregado_id).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_entregas_kit_componente', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entrega_kit_id')
                ->constrained('inv_entregas_kit')
                ->onDelete('cascade');

            $table->foreignId('kit_componente_id')
                ->constrained('inv_kit_componentes')
                ->onDelete('restrict');

            $table->foreignId('producto_entregado_id')
                ->nullable()
                ->constrained('inv_productos')
                ->onDelete('restrict')
                ->comment('Variante concreta entregada. Null = pendiente o componente es simple');

            $table->unsignedInteger('cantidad_solicitada')
                ->comment('cantidad_por_kit × cantidad_kits del pedido');

            $table->unsignedInteger('cantidad_entregada')->default(0);

            $table->enum('status', ['pendiente', 'parcial', 'entregado'])->default('pendiente');

            $table->timestamp('fecha_entrega')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_entregas_kit_componente');
    }
};
