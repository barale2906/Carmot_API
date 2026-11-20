<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea matricula_descuento: descuentos que se activaron al matricular, congelados
     * con el tipo y valor vigentes en ese momento para que editar o inactivar la
     * promoción después no cambie lo pactado con el estudiante.
     */
    public function up(): void
    {
        Schema::create('matricula_descuento', function (Blueprint $table) {
            $table->id();

            $table->foreignId('matricula_id')->constrained('matriculas')->onDelete('cascade');
            $table->foreignId('descuento_id')->constrained('descuentos')->onDelete('restrict');

            $table->string('aplicacion', 30)->comment('Rubro al que aplica: matricula | cuota | valor_total');
            $table->string('tipo', 20)->comment('porcentual | valor_fijo (congelado)');
            $table->decimal('valor', 15, 2)->comment('Porcentaje o monto fijo congelado');
            $table->boolean('permite_acumulacion')->default(false);

            $table->timestamps();

            $table->unique(['matricula_id', 'descuento_id'], 'uq_matricula_descuento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matricula_descuento');
    }
};
