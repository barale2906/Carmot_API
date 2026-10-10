<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Prepara doc_documentos para guardar los documentos firmados y escaneados de
     * una matrícula. Cada escaneado corresponde a un tipo de documento (contrato,
     * pagaré) o, cuando no hay tipo —la hoja de matrícula, que no es una plantilla,
     * u otro soporte—, a una descripción libre; por eso `tipo_documento_id` pasa
     * a ser opcional. El archivo se guarda en el disco privado del servidor
     * mientras no esté lista la integración con Google Drive.
     *
     * @return void
     */
    public function up(): void
    {
        // Sin doctrine/dbal, `->change()` no está disponible: se modifica con SQL.
        DB::statement('ALTER TABLE doc_documentos MODIFY tipo_documento_id BIGINT UNSIGNED NULL');

        Schema::table('doc_documentos', function (Blueprint $table) {
            $table->string('descripcion', 150)->nullable()->after('origen')
                ->comment('Qué documento es un archivo subido sin tipo (p. ej. Hoja de matrícula)');
            $table->string('archivo_path', 255)->nullable()->after('tamano_bytes')
                ->comment('Ruta del archivo subido en el disco privado (storage/app)');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Las filas sin tipo solo pueden ser archivos subidos con descripción libre, y
     * no caben en el esquema anterior: se eliminan antes de volver a exigir el tipo.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('doc_documentos')->whereNull('tipo_documento_id')->delete();

        Schema::table('doc_documentos', function (Blueprint $table) {
            $table->dropColumn(['descripcion', 'archivo_path']);
        });

        DB::statement('ALTER TABLE doc_documentos MODIFY tipo_documento_id BIGINT UNSIGNED NOT NULL');
    }
};
