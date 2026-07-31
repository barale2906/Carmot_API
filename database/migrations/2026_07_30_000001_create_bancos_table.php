<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla bancos para el catálogo de entidades bancarias.
     * Usada para identificar el banco de origen en pagos por transferencia.
     */
    public function up(): void
    {
        Schema::create('bancos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200)->comment('Nombre completo del banco');
            $table->string('codigo', 20)->nullable()->comment('Código o NIT del banco');
            $table->integer('status')->default(1)->comment('0=Inactivo, 1=Activo');
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bancos');
    }
};
