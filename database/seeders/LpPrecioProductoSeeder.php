<?php

namespace Database\Seeders;

use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seeder LpPrecioProductoSeeder
 *
 * Seeder para crear precios de productos en listas de precios.
 * Asigna precios a productos en diferentes listas de precios.
 *
 * @package Database\Seeders
 */
class LpPrecioProductoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     * Crea precios de productos en las listas de precios existentes.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Iniciando creación de precios de productos...');

        // Verificar que existan listas de precios
        $listasPrecios = LpListaPrecio::all();
        if ($listasPrecios->count() === 0) {
            $this->command->warn('No hay listas de precios disponibles. Ejecutando LpListaPrecioSeeder primero...');
            $this->call(LpListaPrecioSeeder::class);
            $listasPrecios = LpListaPrecio::all();
        }

        // Verificar que existan productos
        $productos = LpProducto::all();
        if ($productos->count() === 0) {
            $this->command->warn('No hay productos disponibles. Ejecutando LpProductoSeeder primero...');
            $this->call(LpProductoSeeder::class);
            $productos = LpProducto::all();
        }

        $creados = 0;
        $errores = 0;

        // Para cada lista de precios, asignar precios a productos
        foreach ($listasPrecios as $listaPrecio) {
            $this->command->info("Asignando precios a productos en lista: {$listaPrecio->nombre}");

            // Obtener productos que aún no tienen precio en esta lista
            $productosSinPrecio = $productos->filter(function ($producto) use ($listaPrecio) {
                return !LpPrecioProducto::where('lista_precio_id', $listaPrecio->id)
                    ->where('producto_id', $producto->id)
                    ->exists();
            });

            // Asignar precios a hasta 10 productos por lista
            foreach ($productosSinPrecio->take(10) as $producto) {
                try {
                    // Cargar relación para verificar si es financiable
                    $producto->load('tipoProducto');
                    $esFinanciable = $producto->esFinanciable();

                    if ($esFinanciable) {
                        // Crear precio para producto financiable
                        $precioContado = fake()->randomFloat(2, 500000, 5000000);
                        $precioTotal = fake()->randomFloat(2, $precioContado * 1.1, $precioContado * 1.3);
                        $matricula = fake()->randomFloat(2, $precioTotal * 0.1, $precioTotal * 0.3);
                        $numeroCuotas = fake()->numberBetween(6, 24);

                        $precioProducto = LpPrecioProducto::create([
                            'lista_precio_id' => $listaPrecio->id,
                            'producto_id' => $producto->id,
                            'precio_contado' => $precioContado,
                            'precio_total' => $precioTotal,
                            'matricula' => $matricula,
                            'numero_cuotas' => $numeroCuotas,
                            // valor_cuota se calcula automáticamente en el modelo
                            'observaciones' => fake()->optional(0.2)->sentence(),
                        ]);
                    } else {
                        // Crear precio para producto no financiable (solo contado)
                        $precioContado = fake()->randomFloat(2, 10000, 200000);

                        $precioProducto = LpPrecioProducto::create([
                            'lista_precio_id' => $listaPrecio->id,
                            'producto_id' => $producto->id,
                            'precio_contado' => $precioContado,
                            'precio_total' => null,
                            'matricula' => 0,
                            'numero_cuotas' => null,
                            'observaciones' => fake()->optional(0.2)->sentence(),
                        ]);
                    }

                    $creados++;
                    $this->command->comment("  Precio creado para: {$producto->nombre} - Contado: $" . number_format($precioProducto->precio_contado, 2));
                } catch (Exception $exception) {
                    $errores++;
                    $mensajeError = "Error al crear precio para producto '{$producto->nombre}' en lista '{$listaPrecio->nombre}': {$exception->getMessage()}";
                    $this->command->error($mensajeError);
                    Log::error($mensajeError, [
                        'lista_precio_id' => $listaPrecio->id,
                        'producto_id' => $producto->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        }

        // Crear precios adicionales usando factory para listas específicas
        $this->command->info('Creando precios adicionales con factory...');

        try {
            // Obtener lista vigente para agregar más precios
            $listaVigente = LpListaPrecio::where('status', LpListaPrecio::STATUS_ACTIVA)
                ->orWhere('codigo', 'LP-VIG-2024')
                ->first();

            if ($listaVigente) {
                // Obtener productos que NO tienen precio en esta lista
                $productosConPrecio = LpPrecioProducto::where('lista_precio_id', $listaVigente->id)
                    ->pluck('producto_id')
                    ->toArray();

                $productosDisponibles = LpProducto::whereNotIn('id', $productosConPrecio)->get();

                if ($productosDisponibles->count() > 0) {
                    // Crear precios financiables solo para productos disponibles
                    $productosFinanciables = $productosDisponibles->filter(function ($producto) {
                        $producto->load('tipoProducto');
                        return $producto->esFinanciable();
                    })->take(5);

                    foreach ($productosFinanciables as $producto) {
                        try {
                            LpPrecioProducto::factory()
                                ->financiable()
                                ->state(function (array $attributes) use ($listaVigente, $producto) {
                                    return [
                                        'lista_precio_id' => $listaVigente->id,
                                        'producto_id' => $producto->id,
                                    ];
                                })
                                ->create();
                            $creados++;
                        } catch (Exception $e) {
                            // Ignorar duplicados silenciosamente
                            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                                $errores++;
                                $this->command->error("Error al crear precio: {$e->getMessage()}");
                            }
                        }
                    }

                    // Crear precios no financiables solo para productos disponibles
                    $productosNoFinanciables = $productosDisponibles->filter(function ($producto) {
                        $producto->load('tipoProducto');
                        return !$producto->esFinanciable();
                    })->take(3);

                    foreach ($productosNoFinanciables as $producto) {
                        try {
                            LpPrecioProducto::factory()
                                ->noFinanciable()
                                ->state(function (array $attributes) use ($listaVigente, $producto) {
                                    return [
                                        'lista_precio_id' => $listaVigente->id,
                                        'producto_id' => $producto->id,
                                    ];
                                })
                                ->create();
                            $creados++;
                        } catch (Exception $e) {
                            // Ignorar duplicados silenciosamente
                            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                                $errores++;
                                $this->command->error("Error al crear precio: {$e->getMessage()}");
                            }
                        }
                    }

                    $this->command->info('Precios adicionales creados exitosamente.');
                } else {
                    $this->command->comment('No hay productos disponibles sin precio en la lista vigente.');
                }
            }
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear precios adicionales: {$exception->getMessage()}");
        }

        $this->command->info("Precios de productos creados: {$creados}");
        if ($errores > 0) {
            $this->command->warn("Errores encontrados: {$errores}");
        }
        $this->command->info('Finalizada la creación de precios de productos.');
    }
}
