<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega las jornadas a las que se restringe un descuento (null = todas).
     * Mismos códigos que programacions.jornada (0 a 4).
     */
    public function up(): void
    {
        Schema::table('descuentos', function (Blueprint $table) {
            $table->json('jornadas')
                ->nullable()
                ->after('marca_tarjeta')
                ->comment('Jornadas a las que aplica (0 mañana, 1 tarde, 2 noche, 3 fin de semana mañana, 4 fin de semana tarde); null = todas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('descuentos', function (Blueprint $table) {
            $table->dropColumn('jornadas');
        });
    }
};
