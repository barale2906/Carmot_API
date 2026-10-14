<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula la matrícula con el lead del CRM del que proviene.
     */
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->foreignId('referido_id')
                ->nullable()
                ->after('comercial_id')
                ->constrained('referidos')
                ->nullOnDelete()
                ->comment('lead del CRM convertido en esta matrícula');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropForeign(['referido_id']);
            $table->dropColumn('referido_id');
        });
    }
};
