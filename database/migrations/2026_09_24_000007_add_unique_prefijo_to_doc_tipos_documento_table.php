<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Obliga a que cada tipo de documento tenga su propio prefijo de numeración.
     * El consecutivo de `doc_documentos` se calcula por tipo, pero
     * `numero_documento` es único en toda la tabla: dos tipos que compartieran
     * prefijo generarían el mismo número y la inserción fallaría.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('doc_tipos_documento', function (Blueprint $table) {
            $table->unique('prefijo_numero', 'uq_doc_tipos_prefijo');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina el índice único del prefijo de numeración.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('doc_tipos_documento', function (Blueprint $table) {
            $table->dropUnique('uq_doc_tipos_prefijo');
        });
    }
};
