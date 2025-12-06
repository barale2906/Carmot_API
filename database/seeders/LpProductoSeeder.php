<?php

namespace Database\Seeders;

use App\Models\Academico\Curso;
use App\Models\Academico\Modulo;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\Lp\LpTipoProducto;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeder LpProductoSeeder
 *
 * Seeder para crear productos de listas de precios.
 * Crea productos basados en cursos y módulos existentes, además de productos complementarios.
 *
 * @package Database\Seeders
 */
class LpProductoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     * Crea productos basados en cursos, módulos y productos complementarios.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Iniciando creación de productos de listas de precios...');

        // Verificar que existan tipos de productos
        $tipoCurso = LpTipoProducto::where('codigo', 'curso')->first();
        $tipoModulo = LpTipoProducto::where('codigo', 'modulo')->first();
        $tipoComplementario = LpTipoProducto::where('codigo', 'complementario')->first();

        if (!$tipoCurso || !$tipoModulo || !$tipoComplementario) {
            $this->command->warn('No se encontraron tipos de productos. Ejecutando LpTipoProductoSeeder primero...');
            $this->call(LpTipoProductoSeeder::class);

            $tipoCurso = LpTipoProducto::where('codigo', 'curso')->first();
            $tipoModulo = LpTipoProducto::where('codigo', 'modulo')->first();
            $tipoComplementario = LpTipoProducto::where('codigo', 'complementario')->first();
        }

        $creados = 0;
        $errores = 0;

        // Crear productos basados en cursos existentes
        $cursos = Curso::all();
        if ($cursos->count() > 0) {
            $this->command->info("Creando productos basados en {$cursos->count()} cursos...");

            foreach ($cursos->take(20) as $curso) {
                try {
                    $producto = LpProducto::firstOrCreate(
                        [
                            'referencia_id' => $curso->id,
                            'referencia_tipo' => 'curso',
                        ],
                        [
                            'tipo_producto_id' => $tipoCurso->id,
                            'nombre' => $curso->nombre ?? "Curso {$curso->id}",
                            'codigo' => 'CURSO-' . str_pad($curso->id, 4, '0', STR_PAD_LEFT),
                            'descripcion' => "Producto basado en el curso: {$curso->nombre}",
                            'referencia_id' => $curso->id,
                            'referencia_tipo' => 'curso',
                            'status' => 1,
                        ]
                    );

                    if ($producto->wasRecentlyCreated) {
                        $creados++;
                        $this->command->comment("Producto creado: {$producto->nombre}");
                    }
                } catch (Exception $exception) {
                    $errores++;
                    $mensajeError = "Error al crear producto para curso '{$curso->id}': {$exception->getMessage()}";
                    $this->command->error($mensajeError);
                    Log::error($mensajeError, [
                        'curso_id' => $curso->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        } else {
            $this->command->warn('No hay cursos disponibles. Creando productos de ejemplo...');

            // Crear algunos productos de ejemplo de tipo curso
            for ($i = 1; $i <= 5; $i++) {
                try {
                    $producto = LpProducto::factory()
                        ->activo()
                        ->curso()
                        ->create([
                            'codigo' => 'CURSO-EJ-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                        ]);

                    $creados++;
                    $this->command->comment("Producto de ejemplo creado: {$producto->nombre}");
                } catch (Exception $exception) {
                    $errores++;
                    $this->command->error("Error al crear producto de ejemplo: {$exception->getMessage()}");
                }
            }
        }

        // Crear productos basados en módulos existentes
        $modulos = Modulo::all();
        if ($modulos->count() > 0) {
            $this->command->info("Creando productos basados en {$modulos->count()} módulos...");

            foreach ($modulos->take(15) as $modulo) {
                try {
                    $producto = LpProducto::firstOrCreate(
                        [
                            'referencia_id' => $modulo->id,
                            'referencia_tipo' => 'modulo',
                        ],
                        [
                            'tipo_producto_id' => $tipoModulo->id,
                            'nombre' => $modulo->nombre ?? "Módulo {$modulo->id}",
                            'codigo' => 'MOD-' . str_pad($modulo->id, 4, '0', STR_PAD_LEFT),
                            'descripcion' => "Producto basado en el módulo: {$modulo->nombre}",
                            'referencia_id' => $modulo->id,
                            'referencia_tipo' => 'modulo',
                            'status' => 1,
                        ]
                    );

                    if ($producto->wasRecentlyCreated) {
                        $creados++;
                        $this->command->comment("Producto creado: {$producto->nombre}");
                    }
                } catch (Exception $exception) {
                    $errores++;
                    $mensajeError = "Error al crear producto para módulo '{$modulo->id}': {$exception->getMessage()}";
                    $this->command->error($mensajeError);
                    Log::error($mensajeError, [
                        'modulo_id' => $modulo->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        } else {
            $this->command->warn('No hay módulos disponibles. Creando productos de ejemplo...');

            // Crear algunos productos de ejemplo de tipo módulo
            for ($i = 1; $i <= 5; $i++) {
                try {
                    $producto = LpProducto::factory()
                        ->activo()
                        ->modulo()
                        ->create([
                            'codigo' => 'MOD-EJ-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                        ]);

                    $creados++;
                    $this->command->comment("Producto de ejemplo creado: {$producto->nombre}");
                } catch (Exception $exception) {
                    $errores++;
                    $this->command->error("Error al crear producto de ejemplo: {$exception->getMessage()}");
                }
            }
        }

        // Crear productos complementarios
        $this->command->info('Creando productos complementarios...');

        $productosComplementarios = [
            [
                'nombre' => 'Certificado de Estudios',
                'codigo' => 'CERT-001',
                'descripcion' => 'Certificado oficial de estudios completados',
            ],
            [
                'nombre' => 'Material Didáctico',
                'codigo' => 'MAT-001',
                'descripcion' => 'Material didáctico complementario del curso',
            ],
            [
                'nombre' => 'Kit de Herramientas',
                'codigo' => 'KIT-001',
                'descripcion' => 'Kit de herramientas para desarrollo',
            ],
            [
                'nombre' => 'Certificado Digital',
                'codigo' => 'CERT-DIG-001',
                'descripcion' => 'Certificado digital de estudios',
            ],
            [
                'nombre' => 'Libro de Texto',
                'codigo' => 'LIB-001',
                'descripcion' => 'Libro de texto oficial del curso',
            ],
        ];

        foreach ($productosComplementarios as $productoData) {
            try {
                $producto = LpProducto::firstOrCreate(
                    ['codigo' => $productoData['codigo']],
                    [
                        'tipo_producto_id' => $tipoComplementario->id,
                        'nombre' => $productoData['nombre'],
                        'codigo' => $productoData['codigo'],
                        'descripcion' => $productoData['descripcion'],
                        'referencia_id' => null,
                        'referencia_tipo' => null,
                        'status' => 1,
                    ]
                );

                if ($producto->wasRecentlyCreated) {
                    $creados++;
                    $this->command->comment("Producto complementario creado: {$producto->nombre}");
                }
            } catch (Exception $exception) {
                $errores++;
                $mensajeError = "Error al crear producto complementario '{$productoData['nombre']}': {$exception->getMessage()}";
                $this->command->error($mensajeError);
                Log::error($mensajeError, [
                    'producto' => $productoData,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->command->info("Productos creados: {$creados}");
        if ($errores > 0) {
            $this->command->warn("Errores encontrados: {$errores}");
        }
        $this->command->info('Finalizada la creación de productos de listas de precios.');
    }
}
