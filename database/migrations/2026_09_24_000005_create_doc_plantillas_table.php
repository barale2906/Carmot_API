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
     * Crea la tabla doc_plantillas, donde cada fila es una versión del
     * contenido de un tipo de documento. La vigencia de cada versión se define
     * con fecha_inicio/fecha_fin: al activar una versión nueva se cierra la
     * anterior, de modo que las ventanas no se solapan y siempre se puede
     * resolver qué contenido aplicaba en una fecha determinada.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('doc_plantillas', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la versión de plantilla');

            $table->foreignId('tipo_documento_id')
                ->constrained('doc_tipos_documento')
                ->restrictOnDelete()
                ->comment('Tipo de documento al que pertenece la plantilla');

            $table->string('nombre', 255)->comment('Nombre descriptivo de la versión');
            $table->unsignedInteger('version')->comment('Número de versión consecutivo dentro del tipo de documento');
            $table->longText('contenido_html')->comment('Contenido enriquecido con los marcadores {{variable}}');

            $table->tinyInteger('status')->default(1)->comment('0: Inactiva, 1: En Proceso, 2: Aprobada, 3: Activa');
            $table->date('fecha_inicio')->nullable()->comment('Inicio de vigencia; se asigna al activar la versión');
            $table->date('fecha_fin')->nullable()->comment('Fin de vigencia; null mientras la versión sigue vigente');

            $table->foreignId('version_anterior_id')->nullable()
                ->constrained('doc_plantillas')
                ->nullOnDelete()
                ->comment('Versión de la que se clonó esta plantilla');
            $table->foreignId('creado_por')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Usuario que creó la versión');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tipo_documento_id', 'version'], 'uq_doc_plantilla_version');
            $table->index(['tipo_documento_id', 'status'], 'idx_doc_plantillas_tipo_status');
            $table->index(['fecha_inicio', 'fecha_fin'], 'idx_doc_plantillas_vigencia');
        });

        DB::statement('ALTER TABLE doc_plantillas ADD CONSTRAINT chk_doc_plantilla_vigencia CHECK (fecha_fin IS NULL OR fecha_inicio IS NULL OR fecha_fin >= fecha_inicio)');
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla doc_plantillas si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_plantillas');
    }
};
