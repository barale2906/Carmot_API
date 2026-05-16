<?php

namespace Database\Seeders;

use App\Models\Academico\Curso;
use App\Models\Academico\Modulo;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\Lp\LpProductoReferencia;
use App\Models\Financiero\Lp\LpTipoProducto;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeder LpProductoSeeder
 *
 * Crea productos del catálogo LP y, para los de tipo 'curso' y 'modulo',
 * genera automáticamente el vínculo en lp_producto_referencias.
 * Los productos complementarios (diplomas, registros, etc.) no llevan referencia.
 *
 * @package Database\Seeders
 */
class LpProductoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     *
     * Crea productos LP para cada entidad académica existente y genera el vínculo
     * correspondiente en lp_producto_referencias. Los productos complementarios
     * (diplomas, certificados, registros) se crean sin referencia académica.
     *
     * Usa firstOrCreate para que el seeder sea idempotente: puede ejecutarse
     * múltiples veces sin generar duplicados.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Iniciando creación de productos LP...');

        $tipoCurso        = LpTipoProducto::where('codigo', 'curso')->first();
        $tipoModulo       = LpTipoProducto::where('codigo', 'modulo')->first();
        $tipoComplementario = LpTipoProducto::where('codigo', 'complementario')->first();

        if (!$tipoCurso || !$tipoModulo || !$tipoComplementario) {
            $this->call(LpTipoProductoSeeder::class);
            $tipoCurso        = LpTipoProducto::where('codigo', 'curso')->first();
            $tipoModulo       = LpTipoProducto::where('codigo', 'modulo')->first();
            $tipoComplementario = LpTipoProducto::where('codigo', 'complementario')->first();
        }

        $creados = $errores = 0;

        // ─── Productos basados en cursos ──────────────────────────────────────
        $cursos = Curso::all();
        if ($cursos->count() > 0) {
            $this->command->info("Creando productos para {$cursos->count()} cursos...");

            foreach ($cursos->take(20) as $curso) {
                try {
                    $producto = LpProducto::firstOrCreate(
                        ['codigo' => 'CURSO-' . str_pad($curso->id, 4, '0', STR_PAD_LEFT)],
                        [
                            'tipo_producto_id' => $tipoCurso->id,
                            'nombre'           => $curso->nombre ?? "Curso {$curso->id}",
                            'descripcion'      => "Producto basado en el curso: {$curso->nombre}",
                            'status'           => 1,
                        ]
                    );

                    // Vincular al curso si aún no está vinculado
                    LpProductoReferencia::firstOrCreate([
                        'lp_producto_id'  => $producto->id,
                        'referencia_id'   => $curso->id,
                        'referencia_tipo' => LpProductoReferencia::TIPO_CURSO,
                    ]);

                    if ($producto->wasRecentlyCreated) {
                        $creados++;
                        $this->command->comment("Producto creado y vinculado: {$producto->nombre}");
                    }
                } catch (Exception $e) {
                    $errores++;
                    $msg = "Error al crear producto para curso {$curso->id}: {$e->getMessage()}";
                    $this->command->error($msg);
                    Log::error($msg, ['curso_id' => $curso->id]);
                }
            }
        } else {
            $this->command->warn('No hay cursos. Creando productos de ejemplo sin referencia...');
            for ($i = 1; $i <= 5; $i++) {
                try {
                    LpProducto::factory()->activo()->curso()->create([
                        'codigo' => 'CURSO-EJ-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    ]);
                    $creados++;
                } catch (Exception $e) {
                    $errores++;
                    $this->command->error("Error al crear ejemplo: {$e->getMessage()}");
                }
            }
        }

        // ─── Productos basados en módulos ─────────────────────────────────────
        $modulos = Modulo::all();
        if ($modulos->count() > 0) {
            $this->command->info("Creando productos para {$modulos->count()} módulos...");

            foreach ($modulos->take(15) as $modulo) {
                try {
                    $producto = LpProducto::firstOrCreate(
                        ['codigo' => 'MOD-' . str_pad($modulo->id, 4, '0', STR_PAD_LEFT)],
                        [
                            'tipo_producto_id' => $tipoModulo->id,
                            'nombre'           => $modulo->nombre ?? "Módulo {$modulo->id}",
                            'descripcion'      => "Producto basado en el módulo: {$modulo->nombre}",
                            'status'           => 1,
                        ]
                    );

                    LpProductoReferencia::firstOrCreate([
                        'lp_producto_id'  => $producto->id,
                        'referencia_id'   => $modulo->id,
                        'referencia_tipo' => LpProductoReferencia::TIPO_MODULO,
                    ]);

                    if ($producto->wasRecentlyCreated) {
                        $creados++;
                        $this->command->comment("Producto creado y vinculado: {$producto->nombre}");
                    }
                } catch (Exception $e) {
                    $errores++;
                    $msg = "Error al crear producto para módulo {$modulo->id}: {$e->getMessage()}";
                    $this->command->error($msg);
                    Log::error($msg, ['modulo_id' => $modulo->id]);
                }
            }
        }

        // ─── Productos complementarios (sin referencia académica) ────────────
        $this->command->info('Creando productos complementarios...');

        $complementarios = [
            ['codigo' => 'CERT-001',     'nombre' => 'Certificado de Estudios',      'descripcion' => 'Certificado oficial de estudios completados'],
            ['codigo' => 'DIPLOMA-001',  'nombre' => 'Diploma de Graduación',        'descripcion' => 'Diploma oficial de grado académico'],
            ['codigo' => 'REG-NOTAS-001','nombre' => 'Registro de Notas',            'descripcion' => 'Documento oficial de notas académicas'],
            ['codigo' => 'MAT-001',      'nombre' => 'Material Didáctico',           'descripcion' => 'Material didáctico complementario del curso'],
            ['codigo' => 'KIT-001',      'nombre' => 'Kit de Herramientas',          'descripcion' => 'Kit de herramientas para desarrollo'],
            ['codigo' => 'CERT-DIG-001', 'nombre' => 'Certificado Digital',          'descripcion' => 'Certificado digital de estudios'],
        ];

        foreach ($complementarios as $data) {
            try {
                $producto = LpProducto::firstOrCreate(
                    ['codigo' => $data['codigo']],
                    [
                        'tipo_producto_id' => $tipoComplementario->id,
                        'nombre'           => $data['nombre'],
                        'descripcion'      => $data['descripcion'],
                        'status'           => 1,
                    ]
                );

                if ($producto->wasRecentlyCreated) {
                    $creados++;
                    $this->command->comment("Complementario creado: {$producto->nombre}");
                }
            } catch (Exception $e) {
                $errores++;
                $this->command->error("Error complementario '{$data['nombre']}': {$e->getMessage()}");
            }
        }

        $this->command->info("Productos creados: {$creados}");
        if ($errores > 0) {
            $this->command->warn("Errores: {$errores}");
        }
        $this->command->info('Seeder de productos LP finalizado.');
    }
}
