<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_unidades_medida para definir unidades como Unidad, Par, Caja, etc.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_unidades_medida', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la unidad de medida');
            $table->string('nombre', 100)->comment('Nombre completo (ej. Unidad, Par, Docena)');
            $table->string('abreviatura', 20)->nullable()->comment('Abreviatura (ej. UND, PAR, DOC)');
            $table->tinyInteger('status')->default(1)->comment('Estado: 0=Inactivo, 1=Activo');

            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_inv_um_status');
        });
    }

    /**
     * Elimina la tabla inv_unidades_medida.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_unidades_medida');
    }
};
