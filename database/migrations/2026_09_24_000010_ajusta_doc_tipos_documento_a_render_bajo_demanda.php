<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Renombra `se_ata_fecha` a `conforma_matricula`, que expresa lo que la bandera
     * realmente significa: el documento es legal o forma parte de la matrícula, así
     * que se imprime con la plantilla vigente a la fecha de esa matrícula y no con
     * la vigente hoy.
     *
     * Elimina `prefijo_numero`: los documentos ya no llevan consecutivo propio, su
     * número es el de la matrícula a la que corresponden.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('doc_tipos_documento', function (Blueprint $table) {
            $table->renameColumn('se_ata_fecha', 'conforma_matricula');
        });

        Schema::table('doc_tipos_documento', function (Blueprint $table) {
            $table->dropUnique('uq_doc_tipos_prefijo');
            $table->dropColumn('prefijo_numero');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('doc_tipos_documento', function (Blueprint $table) {
            $table->renameColumn('conforma_matricula', 'se_ata_fecha');
            $table->string('prefijo_numero', 10)->default('DOC')
                ->comment('Prefijo del consecutivo de los documentos generados');
        });

        Schema::table('doc_tipos_documento', function (Blueprint $table) {
            $table->unique('prefijo_numero', 'uq_doc_tipos_prefijo');
        });
    }
};
