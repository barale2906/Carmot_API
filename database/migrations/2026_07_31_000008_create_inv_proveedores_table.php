<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla inv_proveedores (estructura base — CRUD completo en Sprint 4).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_proveedores', function (Blueprint $table) {
            $table->id()->comment('Identificador único del proveedor');
            $table->string('razon_social', 255)->comment('Razón social o nombre del proveedor');
            $table->string('nit', 50)->nullable()->unique()->comment('NIT o identificación tributaria');
            $table->string('contacto', 255)->nullable()->comment('Nombre de la persona de contacto');
            $table->string('telefono', 50)->nullable()->comment('Teléfono principal');
            $table->string('email', 255)->nullable()->comment('Correo electrónico');
            $table->text('direccion')->nullable()->comment('Dirección física');
            $table->text('notas')->nullable()->comment('Observaciones internas');
            $table->tinyInteger('status')->default(1)->comment('Estado: 0=Inactivo, 1=Activo');

            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_inv_prov_status');
            $table->index('razon_social', 'idx_inv_prov_razon');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_proveedores');
    }
};
