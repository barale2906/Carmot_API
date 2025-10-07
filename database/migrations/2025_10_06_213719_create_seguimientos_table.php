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
        Schema::create('seguimientos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('referido_id');
            $table->foreign('referido_id')->references('id')->on('referidos');

            $table->unsignedBigInteger('seguidor_id');
            $table->foreign('seguidor_id')->references('id')->on('users');

            $table->date('fecha')->comment('FEcha en que se genera el registro');
            $table->longText('seguimiento')->comment('Observaciones de la llamada o seguimiento respectivos.');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seguimientos');
    }
};
