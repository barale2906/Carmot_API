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
        Schema::table('matriculas', function (Blueprint $table) {
            $table->foreignId('eps_id')
                ->nullable()
                ->after('regimen_salud')
                ->constrained('eps')
                ->nullOnDelete()
                ->comment('EPS a la que pertenece el estudiante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropForeign(['eps_id']);
            $table->dropColumn('eps_id');
        });
    }
};
