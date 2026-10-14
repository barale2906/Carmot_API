<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega las tallas de dotación al lead para trasladarlas a la matrícula al convertirlo.
     */
    public function up(): void
    {
        Schema::table('referidos', function (Blueprint $table) {
            $table->string('talla_overol', 10)->nullable()->after('ciudad')->comment('talla de overol informada por el aspirante');
            $table->string('talla_botas', 10)->nullable()->after('talla_overol')->comment('talla de botas informada por el aspirante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referidos', function (Blueprint $table) {
            $table->dropColumn(['talla_overol', 'talla_botas']);
        });
    }
};
