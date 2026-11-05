<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla cuentas_bancarias: cuentas del instituto donde se recibe el dinero
     * de los pagos por transferencia, cada una asociada a una sede y a un banco.
     */
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete()
                ->comment('Sede que recibe los pagos en esta cuenta');
            $table->foreignId('banco_id')->constrained('bancos')->restrictOnDelete()
                ->comment('Entidad bancaria de la cuenta');
            $table->string('tipo_cuenta', 30)->comment('ahorros, corriente o billetera');
            $table->string('numero_cuenta', 50)->comment('Número de la cuenta o celular de la billetera');
            $table->string('titular', 200)->nullable()->comment('Titular de la cuenta');
            $table->string('descripcion', 255)->nullable()->comment('Alias o nota para identificarla');
            $table->integer('status')->default(1)->comment('0=Inactivo, 1=Activo');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['sede_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
    }
};
