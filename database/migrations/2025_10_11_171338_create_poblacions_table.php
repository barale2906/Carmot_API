<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('poblacions', function (Blueprint $table) {
            $table->id();

            $table->string('pais')->comment('país al que pertenece la población');
            $table->string('provincia')->nullable()->comment('provincia al que pertenece la población');
            $table->string('nombre')->comment('nombre de la población');
            $table->double('latitud')->comment('latitud de la población');
            $table->double('longitud')->comment('longitud de la población');
            $table->integer('status')->default(0)->comment('0 inactivo, 1 Activo');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poblacions');
    }
};
