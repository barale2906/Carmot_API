<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla doc_tipo_documento_variables, que registra cuáles variables
     * del catálogo (config/documentacion.php) quedan habilitadas para insertarse
     * en las plantillas de cada tipo de documento.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('doc_tipo_documento_variables', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la variable habilitada');

            $table->foreignId('tipo_documento_id')
                ->constrained('doc_tipos_documento')
                ->cascadeOnDelete()
                ->comment('Tipo de documento al que se habilita la variable');
            $table->string('variable_key', 150)
                ->comment('Clave de la variable en el catálogo (ej. estudiante.documento)');

            $table->timestamps();

            $table->unique(['tipo_documento_id', 'variable_key'], 'uq_doc_tipo_variable');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla doc_tipo_documento_variables si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_tipo_documento_variables');
    }
};
