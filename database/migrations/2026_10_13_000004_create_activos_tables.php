<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo de activos fijos: categorías, activos ubicados por sede y área, y el
     * historial de ingresos, traslados y cambios de estado de cada activo.
     */
    public function up(): void
    {
        Schema::create('act_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->comment('ej: herramienta, equipo de cómputo, mobiliario');
            $table->text('descripcion')->nullable();
            $table->integer('status')->default(1)->comment('0 inactivo, 1 activo');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('act_activos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique()->comment('código interno / placa del activo');
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->foreignId('categoria_id')->constrained('act_categorias')->restrictOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete()->comment('sede donde está el activo');
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete()->comment('área o taller dentro de la sede');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete()->comment('persona a cargo del activo');
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('serial', 100)->nullable();
            $table->date('fecha_ingreso')->comment('fecha de adquisición o ingreso al inventario');
            $table->decimal('valor', 15, 2)->default(0)->comment('valor de adquisición');
            $table->enum('estado', ['operativo', 'mantenimiento', 'disponible', 'baja'])->default('operativo');
            $table->text('observaciones')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['sede_id', 'area_id'], 'idx_act_activos_sede_area');
            $table->index('estado', 'idx_act_activos_estado');
        });

        Schema::create('act_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('act_activos')->cascadeOnDelete();
            $table->enum('tipo', ['ingreso', 'traslado', 'cambio_estado']);
            $table->foreignId('sede_origen_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('area_origen_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('sede_destino_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('area_destino_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20)->nullable();
            $table->date('fecha');
            $table->text('motivo')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['activo_id', 'fecha'], 'idx_act_movimientos_activo_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('act_movimientos');
        Schema::dropIfExists('act_activos');
        Schema::dropIfExists('act_categorias');
    }
};
