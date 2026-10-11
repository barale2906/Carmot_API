<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registros manuales del libro diario: movimientos (egresos y otros ingresos)
     * con sus impuestos, consignaciones de efectivo y archivos de soporte.
     */
    public function up(): void
    {
        Schema::create('ld_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_caja_id')->constrained('ld_turnos_caja');
            $table->foreignId('sede_id')->constrained('sedes');
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('tipo_movimiento_id')->constrained('ld_tipos_movimiento');
            $table->unsignedTinyInteger('clase')->comment('1=Egreso, 2=Otro ingreso (copiado del tipo)');
            $table->date('fecha');
            $table->string('tercero_tipo_identificacion', 10);
            $table->string('tercero_identificacion', 30);
            $table->string('tercero_nombre', 200);
            $table->string('documento_tipo', 30);
            $table->string('documento_numero', 60)->nullable();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('total_impuestos', 14, 2)->default(0);
            $table->decimal('total_retenciones', 14, 2)->default(0);
            $table->decimal('valor_total', 14, 2);
            $table->string('medio_pago', 30);
            $table->foreignId('banco_id')->nullable()->constrained('bancos')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('1=Registrado, 2=Anulado');
            $table->foreignId('anulado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['sede_id', 'fecha']);
            $table->index(['turno_caja_id', 'status']);
        });

        Schema::create('ld_movimiento_impuesto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_id')->constrained('ld_movimientos')->cascadeOnDelete();
            $table->foreignId('impuesto_id')->constrained('ld_impuestos');
            $table->string('nombre', 120)->comment('nombre del impuesto al registrar');
            $table->unsignedTinyInteger('efecto')->comment('1=Suma, 2=Resta');
            $table->decimal('porcentaje', 6, 3);
            $table->decimal('valor', 14, 2);
            $table->timestamps();
        });

        Schema::create('ld_consignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_caja_id')->constrained('ld_turnos_caja');
            $table->foreignId('sede_id')->constrained('sedes');
            $table->foreignId('usuario_id')->constrained('users');
            $table->date('fecha');
            $table->foreignId('banco_id')->constrained('bancos');
            $table->decimal('valor', 14, 2);
            $table->string('titular', 200)->comment('a nombre de quién se consigna');
            $table->string('numero_cuenta', 40)->nullable();
            $table->string('numero_comprobante', 60)->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('1=Registrado, 2=Anulado');
            $table->foreignId('anulado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['sede_id', 'fecha']);
            $table->index(['turno_caja_id', 'status']);
        });

        Schema::create('ld_soportes', function (Blueprint $table) {
            $table->id();
            $table->morphs('soportable');
            $table->string('tipo', 30)->comment('factura, recibo, comprobante, otro');
            $table->string('nombre_original', 255);
            $table->string('archivo_path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('ld_soportes');
        Schema::dropIfExists('ld_consignaciones');
        Schema::dropIfExists('ld_movimiento_impuesto');
        Schema::dropIfExists('ld_movimientos');
    }
};
