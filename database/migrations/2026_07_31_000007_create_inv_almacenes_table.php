<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea las tablas inv_almacenes (bodegas por sede) e inv_almacen_usuario
     * (pivot para controlar qué cajeros tienen acceso a cada almacén).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inv_almacenes', function (Blueprint $table) {
            $table->id()->comment('Identificador único del almacén');
            $table->string('nombre', 150)->comment('Nombre del almacén o bodega');
            $table->text('descripcion')->nullable()->comment('Descripción o ubicación física');
            $table->tinyInteger('status')->default(1)->comment('Estado: 0=Inactivo, 1=Activo');

            $table->foreignId('sede_id')
                ->constrained('sedes')
                ->onDelete('restrict')
                ->comment('Sede a la que pertenece el almacén');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sede_id', 'status'], 'idx_inv_alm_sede_status');
        });

        Schema::create('inv_almacen_usuario', function (Blueprint $table) {
            $table->foreignId('almacen_id')
                ->constrained('inv_almacenes')
                ->onDelete('cascade')
                ->comment('Almacén al que se da acceso');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Usuario (cajero) con acceso al almacén');

            $table->primary(['almacen_id', 'user_id'], 'pk_inv_alm_usuario');
        });
    }

    /**
     * Elimina las tablas inv_almacen_usuario e inv_almacenes.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_almacen_usuario');
        Schema::dropIfExists('inv_almacenes');
    }
};
