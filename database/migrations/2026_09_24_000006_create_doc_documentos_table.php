<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla doc_documentos, que guarda tanto los documentos generados
     * desde una plantilla como los archivos externos que se suben al
     * repositorio (cédulas, diplomas). En los generados, `contenido_renderizado`
     * es el HTML con las variables ya resueltas al momento de generar: queda
     * inmutable, de modo que el documento conserva su contenido exacto aunque
     * después cambien la plantilla o los datos de la entidad.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('doc_documentos', function (Blueprint $table) {
            $table->id()->comment('Identificador único del documento');

            $table->foreignId('tipo_documento_id')
                ->constrained('doc_tipos_documento')
                ->restrictOnDelete()
                ->comment('Tipo de documento');
            $table->foreignId('plantilla_id')->nullable()
                ->constrained('doc_plantillas')
                ->restrictOnDelete()
                ->comment('Versión de plantilla usada; null en documentos subidos');

            $table->nullableMorphs('entidad');
            $table->string('numero_documento', 50)->unique()->comment('Consecutivo del documento (PREFIJO-AÑO-NNNNNN)');
            $table->tinyInteger('origen')->default(0)->comment('0: Generado desde plantilla, 1: Archivo subido');

            $table->longText('contenido_renderizado')->nullable()
                ->comment('HTML con las variables ya resueltas; inmutable una vez generado');
            $table->json('variables_aplicadas')->nullable()
                ->comment('Valores de las variables al momento de generar, para auditoría');
            $table->date('fecha_referencia')->nullable()
                ->comment('Fecha usada para resolver la versión de plantilla aplicable');

            $table->string('pdf_path', 255)->nullable()->comment('Ruta del PDF generado en el storage público');
            $table->string('google_drive_file_id', 255)->nullable()->comment('Identificador del archivo en Google Drive');
            $table->string('google_drive_url', 500)->nullable()->comment('Enlace del archivo en Google Drive');

            $table->string('nombre_original', 255)->nullable()->comment('Nombre original del archivo subido');
            $table->string('mime_type', 150)->nullable()->comment('Tipo MIME del archivo subido');
            $table->unsignedBigInteger('tamano_bytes')->nullable()->comment('Tamaño del archivo subido en bytes');

            $table->tinyInteger('status')->default(1)->comment('1: Vigente, 2: Anulado');
            $table->text('motivo_anulacion')->nullable()->comment('Motivo por el que se anuló el documento');

            $table->foreignId('generado_por')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Usuario que generó o subió el documento');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipo_documento_id', 'status'], 'idx_doc_documentos_tipo_status');
            $table->index('origen', 'idx_doc_documentos_origen');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla doc_documentos si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_documentos');
    }
};
