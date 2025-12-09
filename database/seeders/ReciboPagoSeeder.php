<?php

namespace Database\Seeders;

use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeder ReciboPagoSeeder
 *
 * Seeder para crear recibos de pago de ejemplo.
 * Crea recibos con diferentes estados, orígenes y relaciones.
 *
 * @package Database\Seeders
 */
class ReciboPagoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     * Crea recibos de pago con diferentes configuraciones.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Iniciando creación de recibos de pago...');

        // Verificar que existan datos necesarios
        $sedes = Sede::all();
        if ($sedes->count() === 0) {
            $this->command->warn('No hay sedes disponibles. Se crearán sedes de ejemplo.');
            $sedes = Sede::factory()->count(2)->create();
        }

        // Configurar códigos en sedes si no existen
        foreach ($sedes as $index => $sede) {
            if (!$sede->codigo_academico) {
                $sede->codigo_academico = 'ACAD' . $sede->id;
            }
            if (!$sede->codigo_inventario) {
                $sede->codigo_inventario = 'INV' . $sede->id;
            }
            $sede->save();
        }

        $estudiantes = User::whereHas('roles', function ($q) {
            $q->where('name', 'alumno');
        })->get();

        if ($estudiantes->count() === 0) {
            $this->command->warn('No hay estudiantes disponibles. Se crearán estudiantes de ejemplo.');
            $estudiantes = User::factory()->count(5)->create();
            foreach ($estudiantes as $estudiante) {
                $estudiante->assignRole('alumno');
            }
        }

        $cajeros = User::inRandomOrder()->limit(3)->get();
        if ($cajeros->count() === 0) {
            $cajeros = User::factory()->count(2)->create();
        }

        $conceptosPago = ConceptoPago::all();
        $listasPrecio = LpListaPrecio::all();
        $productos = LpProducto::all();
        $descuentos = Descuento::where('status', Descuento::STATUS_ACTIVO)->get();
        $matriculas = Matricula::all();

        $creados = 0;
        $errores = 0;

        // Crear recibos académicos
        for ($i = 0; $i < 10; $i++) {
            try {
                $sede = $sedes->random();
                $estudiante = $estudiantes->random();
                $cajero = $cajeros->random();
                $matricula = $matriculas->count() > 0 ? $matriculas->random() : null;

                $recibo = ReciboPago::factory()
                    ->academico()
                    ->state([
                        'sede_id' => $sede->id,
                        'estudiante_id' => $estudiante->id,
                        'cajero_id' => $cajero->id,
                        'matricula_id' => $matricula?->id,
                        'fecha_recibo' => Carbon::now()->subDays(rand(1, 90)),
                        'fecha_transaccion' => Carbon::now()->subDays(rand(1, 90)),
                    ])
                    ->create();

                // Agregar conceptos de pago
                if ($conceptosPago->count() > 0) {
                    $conceptosSeleccionados = $conceptosPago->random(rand(1, 3));
                    foreach ($conceptosSeleccionados as $concepto) {
                        $cantidad = rand(1, 3);
                        $unitario = fake()->randomFloat(2, 10000, 500000);
                        $subtotal = $cantidad * $unitario;

                        $recibo->conceptosPago()->attach($concepto->id, [
                            'valor' => $subtotal,
                            'tipo' => $concepto->tipo,
                            'producto' => fake()->words(2, true),
                            'cantidad' => $cantidad,
                            'unitario' => $unitario,
                            'subtotal' => $subtotal,
                            'id_relacional' => $matricula?->id,
                        ]);
                    }
                }

                // Agregar listas de precio
                if ($listasPrecio->count() > 0) {
                    $listasSeleccionadas = $listasPrecio->random(rand(1, 2));
                    $recibo->listasPrecio()->attach($listasSeleccionadas->pluck('id'));
                }

                // Agregar productos
                if ($productos->count() > 0) {
                    $productosSeleccionados = $productos->random(rand(1, 3));
                    foreach ($productosSeleccionados as $producto) {
                        $cantidad = rand(1, 2);
                        $precioUnitario = fake()->randomFloat(2, 50000, 2000000);
                        $subtotal = $cantidad * $precioUnitario;

                        $recibo->productos()->attach($producto->id, [
                            'cantidad' => $cantidad,
                            'precio_unitario' => $precioUnitario,
                            'subtotal' => $subtotal,
                        ]);
                    }
                }

                // Agregar descuentos
                if ($descuentos->count() > 0 && fake()->boolean(40)) {
                    $descuentosSeleccionados = $descuentos->random(rand(1, 2));
                    foreach ($descuentosSeleccionados as $descuento) {
                        $valorOriginal = fake()->randomFloat(2, 100000, 1000000);
                        $valorDescuento = $descuento->tipo === Descuento::TIPO_PORCENTUAL
                            ? ($valorOriginal * $descuento->valor / 100)
                            : $descuento->valor;
                        $valorFinal = $valorOriginal - $valorDescuento;

                        $recibo->descuentos()->attach($descuento->id, [
                            'valor_descuento' => $valorDescuento,
                            'valor_original' => $valorOriginal,
                            'valor_final' => $valorFinal,
                        ]);
                    }
                }

                // Recalcular totales
                $totales = $recibo->calcularTotales();
                $recibo->update($totales);

                // Agregar medios de pago
                $mediosPago = [
                    ['medio' => 'efectivo', 'valor' => $recibo->valor_total * 0.6],
                    ['medio' => 'tarjeta', 'valor' => $recibo->valor_total * 0.4],
                ];

                foreach ($mediosPago as $medio) {
                    ReciboPagoMedioPago::create([
                        'recibo_pago_id' => $recibo->id,
                        'medio_pago' => $medio['medio'],
                        'valor' => $medio['valor'],
                        'referencia' => $medio['medio'] === 'tarjeta' ? fake()->creditCardNumber() : null,
                        'banco' => $medio['medio'] === 'tarjeta' ? fake()->randomElement(['Visa', 'Mastercard']) : null,
                    ]);
                }

                // Cambiar estado según índice
                if ($i % 4 === 0) {
                    $recibo->update(['status' => ReciboPago::STATUS_CREADO]);
                } elseif ($i % 4 === 1) {
                    $recibo->cerrar(rand(1, 10));
                } elseif ($i % 4 === 2) {
                    $recibo->anular();
                }

                $creados++;
            } catch (\Exception $e) {
                $errores++;
                $this->command->error("Error al crear recibo: " . $e->getMessage());
            }
        }

        // Crear recibos de inventario
        for ($i = 0; $i < 5; $i++) {
            try {
                $sede = $sedes->random();
                $cajero = $cajeros->random();

                $recibo = ReciboPago::factory()
                    ->inventario()
                    ->state([
                        'sede_id' => $sede->id,
                        'cajero_id' => $cajero->id,
                        'estudiante_id' => null,
                        'matricula_id' => null,
                        'fecha_recibo' => Carbon::now()->subDays(rand(1, 60)),
                        'fecha_transaccion' => Carbon::now()->subDays(rand(1, 60)),
                    ])
                    ->create();

                // Agregar productos
                if ($productos->count() > 0) {
                    $productosSeleccionados = $productos->random(rand(1, 2));
                    foreach ($productosSeleccionados as $producto) {
                        $cantidad = rand(1, 5);
                        $precioUnitario = fake()->randomFloat(2, 10000, 500000);
                        $subtotal = $cantidad * $precioUnitario;

                        $recibo->productos()->attach($producto->id, [
                            'cantidad' => $cantidad,
                            'precio_unitario' => $precioUnitario,
                            'subtotal' => $subtotal,
                        ]);
                    }
                }

                // Recalcular totales
                $totales = $recibo->calcularTotales();
                $recibo->update($totales);

                // Agregar medio de pago único
                ReciboPagoMedioPago::create([
                    'recibo_pago_id' => $recibo->id,
                    'medio_pago' => 'efectivo',
                    'valor' => $recibo->valor_total,
                ]);

                $recibo->update(['status' => ReciboPago::STATUS_CREADO]);
                $creados++;
            } catch (\Exception $e) {
                $errores++;
                $this->command->error("Error al crear recibo de inventario: " . $e->getMessage());
            }
        }

        $this->command->info("Recibos de pago creados: {$creados}");
        if ($errores > 0) {
            $this->command->warn("Errores encontrados: {$errores}");
        }
    }
}

