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
        Schema::create('topicos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->comment('nombre del topico');
            $table->text('descripcion')->comment('descripcion del topico');
            $table->double('duracion')->comment('duracion del topico en horas');
            $table->integer('status')->default(1)->comment('0 inactivo, 1 Activo');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topicos');
    }
};
