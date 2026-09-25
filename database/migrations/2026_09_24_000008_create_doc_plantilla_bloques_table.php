<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla doc_plantilla_bloques, donde cada fila guarda cómo se imprime
     * un bloque (tabla de consulta) dentro de una versión de plantilla: qué
     * columnas se muestran, en qué orden, con qué títulos y si lleva fila de
     * resumen. Se asocia a la versión y no al tipo de documento para que cambiar
     * las columnas pase por el mismo flujo de aprobación que cambiar el texto.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('doc_plantilla_bloques', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la configuración del bloque');

            $table->foreignId('plantilla_id')
                ->constrained('doc_plantillas')
                ->cascadeOnDelete()
                ->comment('Versión de plantilla que imprime el bloque');
            $table->string('bloque_key', 100)
                ->comment('Clave del bloque en el catálogo (config documentacion.bloques)');

            $table->json('columnas')->comment('Columnas a imprimir, en orden');
            $table->json('titulos')->nullable()->comment('Títulos personalizados por columna');
            $table->boolean('mostrar_resumen')->default(true)->comment('Imprime la fila de totales o promedio del bloque');

            $table->timestamps();

            $table->unique(['plantilla_id', 'bloque_key'], 'uq_doc_plantilla_bloque');
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla doc_plantilla_bloques si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_plantilla_bloques');
    }
};
