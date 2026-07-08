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

            // tipo_movimiento distingue si es un descuento (reduce precio) o sobrecargo (incrementa precio).
            // Los sobrecargos son siempre porcentuales; los descuentos pueden ser porcentuales o valor fijo.
            $table->enum('tipo_movimiento', ['descuento', 'sobrecargo'])->default('descuento')
                ->comment('Dirección del ajuste: descuento reduce el valor, sobrecargo lo incrementa');

            $table->enum('tipo', ['porcentual', 'valor_fijo'])->comment('Tipo de cálculo: porcentual o valor fijo (valor_fijo solo para descuentos)');
            $table->decimal('valor', 15, 2)->comment('Valor del ajuste (porcentaje 0-100 o monto fijo)');

            // aplicacion: donde se aplica el ajuste.
            // valor_recibo y saldo_cartera son exclusivos de sobrecargos.
            $table->enum('aplicacion', ['valor_total', 'matricula', 'cuota', 'valor_recibo', 'saldo_cartera'])
                ->comment('Donde aplica el ajuste (valor_recibo/saldo_cartera solo para sobrecargos)');

            // tipo_activacion: qué condición dispara el ajuste.
            // medio_pago y mora_automatica son exclusivos de sobrecargos.
            $table->enum('tipo_activacion', ['pago_anticipado', 'promocion_matricula', 'codigo_promocional', 'medio_pago', 'mora_automatica'])
                ->comment('Condición que activa el ajuste');

            $table->unsignedInteger('dias_anticipacion')->nullable()->comment('Días de anticipación requeridos (solo pago_anticipado)');
            $table->boolean('permite_acumulacion')->default(false)->comment('Indica si puede acumularse con otros (siempre false para sobrecargos)');

            // medios_pago: medios de pago que activan este sobrecargo. Ej: ["tarjeta_credito","tarjeta_debito"].
            $table->json('medios_pago')->nullable()
                ->comment('Medios de pago que activan el sobrecargo (solo tipo_activacion=medio_pago)');

            // marca_tarjeta: marcas específicas dentro de los medios de pago. Ej: ["visa","mastercard"].
            // Null = aplica a todas las marcas del medio_pago definido. Valor libre y configurable por el admin.
            $table->json('marca_tarjeta')->nullable()
                ->comment('Marcas de tarjeta específicas; null = cualquier marca del medio_pago configurado');

            $table->date('fecha_inicio')->comment('Fecha de inicio de vigencia');
            $table->date('fecha_fin')->comment('Fecha de fin de vigencia');
            $table->tinyInteger('status')->default(1)->comment('0: Inactivo, 1: En Proceso, 2: Aprobado, 3: Activo');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('tipo_movimiento', 'idx_tipo_movimiento');
            $table->index('codigo_descuento', 'idx_codigo_descuento');
            $table->index('status', 'idx_status');
            $table->index('fecha_inicio', 'idx_fecha_inicio');
            $table->index('fecha_fin', 'idx_fecha_fin');
            $table->index('tipo_activacion', 'idx_tipo_activacion');
        });

        // CHECK constraints
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_valor_positivo CHECK (valor >= 0)');
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_fecha_fin_mayor_igual_inicio CHECK (fecha_fin >= fecha_inicio)');
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_pago_anticipado_dias CHECK (tipo_activacion != \'pago_anticipado\' OR dias_anticipacion IS NOT NULL)');
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_codigo_promocional CHECK (tipo_activacion != \'codigo_promocional\' OR codigo_descuento IS NOT NULL)');
        // Sobrecargos: solo porcentual, no acumulables, medios_pago obligatorio cuando tipo_activacion=medio_pago
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_sobrecargo_tipo CHECK (tipo_movimiento != \'sobrecargo\' OR tipo = \'porcentual\')');
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_sobrecargo_acumulacion CHECK (tipo_movimiento != \'sobrecargo\' OR permite_acumulacion = 0)');
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_medio_pago_medios CHECK (tipo_activacion != \'medio_pago\' OR medios_pago IS NOT NULL)');
        // Validar que la aplicacion sea coherente con tipo_movimiento
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_descuento_aplicacion CHECK (tipo_movimiento != \'descuento\' OR aplicacion IN (\'valor_total\',\'matricula\',\'cuota\'))');
        DB::statement('ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_sobrecargo_aplicacion CHECK (tipo_movimiento != \'sobrecargo\' OR aplicacion IN (\'valor_recibo\',\'saldo_cartera\'))');
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

