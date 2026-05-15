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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // ----------------------------------------------------------------
            // Nombre completo descompuesto
            // ----------------------------------------------------------------
            $table->string('primer_nombre', 80)->comment('primer nombre del usuario (obligatorio)');
            $table->string('segundo_nombre', 80)->nullable()->comment('segundo nombre del usuario (opcional)');
            $table->string('primer_apellido', 80)->comment('primer apellido del usuario (obligatorio)');
            $table->string('segundo_apellido', 80)->nullable()->comment('segundo apellido del usuario (opcional)');

            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('documento')->unique();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
