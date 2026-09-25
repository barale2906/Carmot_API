<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Convierte doc_documentos en una bitácora liviana: cada fila registra que se
     * imprimió un documento —de qué tipo, para qué matrícula, con qué versión de
     * plantilla, quién y cuándo— sin guardar su contenido.
     *
     * El contenido se vuelve a resolver en cada impresión a partir de la plantilla
     * aplicable y de los datos del estudiante, así que almacenar el HTML solo
     * inflaba la base de datos. Por lo mismo desaparecen el consecutivo propio (el
     * número del documento es el de la matrícula) y el estado de anulación, que
     * corresponde a la matrícula y no a cada impresión.
     *
     * Las columnas de los archivos subidos al repositorio se conservan intactas.
     *
     * @return void
     */
    public function up(): void
    {
        // El índice nuevo se crea antes de soltar el anterior: la llave foránea de
        // tipo_documento_id se apoya en él y MySQL no permite quedarse sin ninguno.
        Schema::table('doc_documentos', function (Blueprint $table) {
            $table->index(['tipo_documento_id', 'origen'], 'idx_doc_documentos_tipo_origen');
        });

        Schema::table('doc_documentos', function (Blueprint $table) {
            $table->dropIndex('idx_doc_documentos_tipo_status');
        });

        Schema::table('doc_documentos', function (Blueprint $table) {
            $table->dropColumn([
                'numero_documento',
                'contenido_renderizado',
                'variables_aplicadas',
                'status',
                'motivo_anulacion',
            ]);
        });
    }

    /**
     * Revierte las migraciones.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('doc_documentos', function (Blueprint $table) {
            $table->string('numero_documento', 50)->nullable()->after('entidad_id');
            $table->longText('contenido_renderizado')->nullable()->after('origen');
            $table->json('variables_aplicadas')->nullable()->after('contenido_renderizado');
            $table->tinyInteger('status')->default(1)->after('tamano_bytes');
            $table->text('motivo_anulacion')->nullable()->after('status');
        });

        Schema::table('doc_documentos', function (Blueprint $table) {
            $table->index(['tipo_documento_id', 'status'], 'idx_doc_documentos_tipo_status');
        });

        Schema::table('doc_documentos', function (Blueprint $table) {
            $table->dropIndex('idx_doc_documentos_tipo_origen');
        });
    }
};
