<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega punto_reorden a inv_productos para alertas de bajo stock.
     * Solo aplica a productos tipo=simple; kits y grupos lo ignoran.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('inv_productos', function (Blueprint $table) {
            $table->unsignedInteger('punto_reorden')
                ->default(0)
                ->comment('Alerta de reposición cuando cantidad_disponible ≤ este valor (0 = sin alerta)')
                ->after('producto_padre_id');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::table('inv_productos', function (Blueprint $table) {
            $table->dropColumn('punto_reorden');
        });
    }
};
