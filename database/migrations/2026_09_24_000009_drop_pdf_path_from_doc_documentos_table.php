<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Elimina `pdf_path` de doc_documentos. Los PDF se arman en el momento en que
     * se solicitan a partir de `contenido_renderizado`, así que guardar el archivo
     * y su ruta era una copia redundante de información que el documento ya tiene.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('doc_documentos', function (Blueprint $table) {
            $table->dropColumn('pdf_path');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Restituye la columna `pdf_path`.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('doc_documentos', function (Blueprint $table) {
            $table->string('pdf_path', 255)->nullable()->after('fecha_referencia')
                ->comment('Ruta del PDF generado en el storage público');
        });
    }
};
