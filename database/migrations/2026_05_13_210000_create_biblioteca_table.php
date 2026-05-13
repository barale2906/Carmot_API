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
        Schema::create('biblioteca', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('fecha_carga');
            $table->date('fecha_obsolescencia')->nullable();
            $table->string('ruta');
            $table->string('tipo_archivo', 50)->nullable()->comment('Extensión o MIME type del archivo');
            $table->unsignedBigInteger('tamanio')->nullable()->comment('Tamaño del archivo en bytes');
            $table->tinyInteger('status')->default(1)->comment('1=activo, 0=inactivo');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biblioteca');
    }
};
