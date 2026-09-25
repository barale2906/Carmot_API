<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla doc_tipos_documento, catálogo de los tipos de documento
     * que el instituto puede generar (contrato, pagaré, certificado, carta…).
     * Cada tipo define a qué entidad del sistema se asocia y si su contenido
     * queda atado a una fecha de referencia para resolver la versión aplicable.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('doc_tipos_documento', function (Blueprint $table) {
            $table->id()->comment('Identificador único del tipo de documento');

            $table->string('codigo', 50)->unique()->comment('Código único del tipo (CONTRATO, PAGARE, HOJA_MATRICULA…)');
            $table->string('nombre', 255)->comment('Nombre del tipo de documento');
            $table->text('descripcion')->nullable()->comment('Descripción del tipo de documento');

            $table->string('entidad_type', 255)->nullable()
                ->comment('Clase Eloquent de la entidad asociada (config documentacion.entidades); null = documento sin entidad');
            $table->boolean('se_ata_fecha')->default(false)
                ->comment('true: la versión de plantilla se resuelve por la fecha de referencia; false: siempre usa la versión activa');
            $table->string('campo_fecha_referencia', 100)->nullable()
                ->comment('Atributo fecha de la entidad usado como referencia; null = fecha de generación del documento');

            $table->string('prefijo_numero', 10)->comment('Prefijo del consecutivo de los documentos generados (CONT, PAG…)');
            $table->tinyInteger('status')->default(1)->comment('0: Inactivo, 1: Activo');

            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_doc_tipos_status');
            $table->index('entidad_type', 'idx_doc_tipos_entidad');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla doc_tipos_documento si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_tipos_documento');
    }
};
