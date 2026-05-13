<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla sede_user que establece la relación
     * muchos a muchos entre sedes y usuarios.
     * Los superusuarios tienen acceso implícito a todas las sedes
     * y no requieren registros en esta tabla.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('sede_user', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la relación sede-usuario');

            $table->unsignedBigInteger('sede_id')->comment('ID de la sede');
            $table->unsignedBigInteger('user_id')->comment('ID del usuario');

            $table->timestamps();

            // Foreign keys
            $table->foreign('sede_id')
                  ->references('id')
                  ->on('sedes')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Evitar duplicados
            $table->unique(['sede_id', 'user_id'], 'idx_sede_user');

            // Índices
            $table->index('sede_id', 'idx_su_sede');
            $table->index('user_id', 'idx_su_user');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('sede_user');
    }
};
