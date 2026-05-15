<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();

            // ----------------------------------------------------------------
            // Datos académicos / administrativos
            // ----------------------------------------------------------------
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->foreignId('ciclo_id')->constrained('ciclos')->onDelete('cascade');
            $table->foreignId('estudiante_id')->constrained('users')->onDelete('cascade')->comment('estudiante matriculado');
            $table->foreignId('matriculado_por_id')->constrained('users')->onDelete('cascade')->comment('usuario que matriculó al estudiante');
            $table->foreignId('comercial_id')->constrained('users')->onDelete('cascade')->comment('usuario que gestionó la venta');
            $table->date('fecha_matricula')->comment('fecha de la matrícula');
            $table->date('fecha_inicio')->comment('fecha de inicio de las clases');
            $table->double('monto')->comment('monto total de la matrícula');
            $table->decimal('valor_cuota', 12, 2)->nullable()->comment('valor de la cuota de pago');
            $table->longText('observaciones')->nullable()->comment('observaciones de la matrícula');

            // ----------------------------------------------------------------
            // Datos de identificación
            // ----------------------------------------------------------------
            $table->string('tipo_identificacion', 2)->nullable()
                ->comment('CC: Cédula de Ciudadanía, CE: Cédula de Extranjería, TI: Tarjeta de Identidad, RC: Registro Civil, PA: Pasaporte, OT: Otro');
            $table->string('departamento_expedicion')->nullable()
                ->comment('Departamento/Provincia de expedición del documento (provincia de la tabla poblaciones)');
            $table->string('ciudad_expedicion')->nullable()
                ->comment('Ciudad de expedición del documento (nombre de la tabla poblaciones)');

            // ----------------------------------------------------------------
            // Datos personales
            // ----------------------------------------------------------------
            $table->date('fecha_nacimiento')->nullable();
            $table->string('genero', 1)->nullable()
                ->comment('M: Masculino, F: Femenino, O: Otro');
            $table->string('estado_civil', 2)->nullable()
                ->comment('SO: Soltero, CA: Casado, UL: Unión libre, DI: Divorciado, VI: Viudo, SE: Separado');
            $table->string('grupo_sanguineo', 2)->nullable()
                ->comment('A, B, AB, O');
            $table->string('rh', 1)->nullable()
                ->comment('P: Positivo, N: Negativo');
            $table->string('direccion')->nullable()->comment('dirección de residencia');
            $table->foreignId('lugar_origen_id')->nullable()->constrained('poblacions')->nullOnDelete()
                ->comment('ID de la tabla poblaciones — lugar de origen del estudiante');
            $table->string('celular', 20)->nullable();
            $table->string('telefono', 20)->nullable();

            // ----------------------------------------------------------------
            // Datos socioeconómicos
            // ----------------------------------------------------------------
            $table->string('nivel_educacion', 2)->nullable()
                ->comment('PR: Primaria, SE: Secundaria, TC: Técnico, TG: Tecnólogo, PF: Profesional, ES: Especialización, MA: Maestría, DO: Doctorado, OT: Otro');
            $table->string('ocupacion')->nullable();
            $table->string('empresa')->nullable()->comment('empresa donde trabaja');
            $table->unsignedTinyInteger('estrato')->nullable()->comment('estrato socioeconómico 1-6');
            $table->string('regimen_salud', 2)->nullable()
                ->comment('CO: Contributivo, SU: Subsidiado, ES: Especial, EX: Excepción');

            // ----------------------------------------------------------------
            // Datos de salud y condición
            // ----------------------------------------------------------------
            $table->boolean('enfermedad_prioritaria')->nullable()
                ->comment('sufre alguna enfermedad de atención prioritaria');
            $table->boolean('discapacidad')->nullable()
                ->comment('tiene alguna discapacidad');

            // ----------------------------------------------------------------
            // Datos del proceso de venta / inscripción
            // ----------------------------------------------------------------
            $table->boolean('conocimiento_curso')->nullable()
                ->comment('tiene conocimiento del curso que va a realizar');
            $table->string('como_entero_curso')->nullable()
                ->comment('cómo se enteró del curso');

            // ----------------------------------------------------------------
            // Dotación
            // ----------------------------------------------------------------
            $table->string('talla_overol', 10)->nullable();
            $table->string('talla_botas', 10)->nullable();

            // ----------------------------------------------------------------
            // Contacto de emergencia
            // ----------------------------------------------------------------
            $table->string('nombre_contacto')->nullable()->comment('nombre de la persona de contacto');
            $table->string('telefono_contacto', 20)->nullable()->comment('teléfono de la persona de contacto');
            $table->string('correo_contacto')->nullable()->comment('correo electrónico de la persona de contacto');

            // ----------------------------------------------------------------
            // Consentimientos e identidad cultural
            // ----------------------------------------------------------------
            $table->boolean('aprueba_uso_imagen')->default(false)->comment('aprueba el uso de imagen');
            $table->string('multiculturalidad')->nullable()
                ->comment('identidad cultural: indígena, afrodescendiente, raizal, palenquero, rom, mestizo, otro');
            $table->string('foto')->nullable()->comment('ruta de la foto del estudiante');

            $table->integer('status')->default(1)->comment('0: inactivo, 1: activo, 2: anulado');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};
