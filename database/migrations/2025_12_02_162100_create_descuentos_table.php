<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Crea la tabla descuentos que define los descuentos disponibles
     * con su tipo, aplicación, condiciones de activación y vigencia.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('descuentos', function (Blueprint $table) {
            $table->id()->comment('Identificador único del descuento');

            $table->string('nombre', 255)->comment('Nombre descriptivo del descuento');
            $table->string('codigo_descuento', 50)->nullable()->unique()->comment('Código promocional alfanumérico único');
            $table->text('descripcion')->nullable()->comment('Descripción del descuento');
            $table->enum('tipo', ['porcentual', 'valor_fijo'])->comment('Tipo de descuento: porcentual o valor fijo');
            $table->decimal('valor', 15, 2)->comment('Valor del descuento (porcentaje o monto fijo)');
            $table->enum('aplicacion', ['valor_total', 'matricula', 'cuota'])->comment('Aplicación del descuento: valor_total, matricula o cuota');
            $table->enum('tipo_activacion', ['pago_anticipado', 'promocion_matricula', 'codigo_promocional'])->comment('Tipo de activación del descuento');
            $table->unsignedInteger('dias_anticipacion')->nullable()->comment('Días de anticipación requeridos para pago anticipado');
            $table->boolean('permite_acumulacion')->default(false)->comment('Indica si el descuento puede acumularse con otros');
            $table->date('fecha_inicio')->comment('Fecha de inicio de vigencia del descuento');
            $table->date('fecha_fin')->comment('Fecha de fin de vigencia del descuento');
            $table->tinyInteger('status')->default(1)->comment('0: Inactivo, 1: En Proceso, 2: Aprobado, 3: Activo');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('codigo_descuento', 'idx_codigo_descuento');
            $table->index('status', 'idx_status');
            $table->index('fecha_inicio', 'idx_fecha_inicio');
            $table->index('fecha_fin', 'idx_fecha_fin');
            $table->index('tipo_activacion', 'idx_tipo_activacion');
        });

        // Agregar CHECK constraints
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_valor_positivo CHECK (valor >= 0)');
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_fecha_fin_mayor_igual_inicio CHECK (fecha_fin >= fecha_inicio)');
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_pago_anticipado_dias CHECK (tipo_activacion != \'pago_anticipado\' OR dias_anticipacion IS NOT NULL)');
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_codigo_promocional CHECK (tipo_activacion != \'codigo_promocional\' OR codigo_descuento IS NOT NULL)');
    }

    /**
     * Revierte las migraciones.
     *
     * Elimina la tabla descuentos si existe.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('descuentos');
    }
};

