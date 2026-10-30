<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea el catálogo de terceros del libro diario, enlaza los movimientos con él
     * y crea un tercero por cada identificación ya usada en movimientos (con el
     * nombre del movimiento más reciente). Los movimientos conservan su copia del
     * tercero (tipo, identificación y nombre) como registro histórico.
     */
    public function up(): void
    {
        Schema::create('ld_terceros', function (Blueprint $table) {
            $table->id()->comment('Identificador del tercero');
            $table->string('tipo_identificacion', 10)->comment('CC, NIT, CE, PA o TI');
            $table->string('identificacion', 30)->comment('Número de identificación');
            $table->string('nombre', 200)->comment('Nombre o razón social');
            $table->string('telefono', 30)->nullable()->comment('Teléfono de contacto');
            $table->string('email', 150)->nullable()->comment('Correo electrónico');
            $table->string('direccion', 255)->nullable()->comment('Dirección');
            $table->tinyInteger('status')->default(1)->comment('Estado: 0=Inactivo, 1=Activo');
            $table->timestamps();

            $table->unique(['tipo_identificacion', 'identificacion'], 'uq_ld_terceros_identificacion');
            $table->index('nombre', 'idx_ld_terceros_nombre');
        });

        Schema::table('ld_movimientos', function (Blueprint $table) {
            $table->foreignId('tercero_id')->nullable()->after('fecha')
                ->comment('Tercero del catálogo; tercero_* guardan la copia histórica')
                ->constrained('ld_terceros')->restrictOnDelete();
        });

        $this->crearTercerosExistentes();
    }

    /**
     * Un tercero por cada (tipo, identificación) de los movimientos existentes.
     */
    private function crearTercerosExistentes(): void
    {
        $ahora = now();
        DB::table('ld_movimientos')
            ->select('tercero_tipo_identificacion', 'tercero_identificacion')
            ->groupBy('tercero_tipo_identificacion', 'tercero_identificacion')
            ->orderBy('tercero_tipo_identificacion')
            ->get()
            ->each(function ($grupo) use ($ahora) {
                $movimientos = DB::table('ld_movimientos')
                    ->where('tercero_tipo_identificacion', $grupo->tercero_tipo_identificacion)
                    ->where('tercero_identificacion', $grupo->tercero_identificacion);

                $terceroId = DB::table('ld_terceros')->insertGetId([
                    'tipo_identificacion' => $grupo->tercero_tipo_identificacion,
                    'identificacion' => $grupo->tercero_identificacion,
                    'nombre' => (clone $movimientos)->latest('id')->value('tercero_nombre'),
                    'status' => 1,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                $movimientos->update(['tercero_id' => $terceroId]);
            });
    }

    /**
     * Quita el enlace de los movimientos y el catálogo de terceros.
     */
    public function down(): void
    {
        Schema::table('ld_movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tercero_id');
        });

        Schema::dropIfExists('ld_terceros');
    }
};
