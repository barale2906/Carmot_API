<?php

namespace Database\Seeders;

use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpProducto;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeder DescuentoSeeder
 *
 * Seeder para crear descuentos de ejemplo.
 * Crea descuentos con diferentes tipos, aplicaciones, activaciones y estados.
 *
 * @package Database\Seeders
 */
class DescuentoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     * Crea descuentos con diferentes configuraciones.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Iniciando creación de descuentos...');

        // Verificar que existan listas de precios
        $listasPrecios = LpListaPrecio::all();
        if ($listasPrecios->count() === 0) {
            $this->command->warn('No hay listas de precios disponibles. Los descuentos se crearán sin listas asignadas.');
        }

        // Verificar que existan productos
        $productos = LpProducto::all();
        if ($productos->count() === 0) {
            $this->command->warn('No hay productos disponibles. Los descuentos se crearán sin productos asignados.');
        }

        // Verificar que existan sedes
        $sedes = Sede::all();
        if ($sedes->count() === 0) {
            $this->command->warn('No hay sedes disponibles. Los descuentos se crearán sin sedes asignadas.');
        }

        // Verificar que existan poblaciones
        $poblaciones = Poblacion::all();
        if ($poblaciones->count() === 0) {
            $this->command->warn('No hay poblaciones disponibles. Los descuentos se crearán sin poblaciones asignadas.');
        }

        $creados = 0;
        $errores = 0;

        // 1. Descuento por pago anticipado - Porcentual - Valor Total - Acumulable - Activo
        try {
            $fechaInicio = Carbon::now()->subMonths(1);
            $fechaFin = Carbon::now()->addMonths(11);

            $descuento = Descuento::firstOrCreate(
                ['nombre' => 'Descuento 5% Pago Anticipado'],
                [
                    'nombre' => 'Descuento 5% Pago Anticipado',
                    'codigo_descuento' => null,
                    'descripcion' => 'Descuento del 5% por pago 15 días antes de la fecha programada',
                    'tipo' => Descuento::TIPO_PORCENTUAL,
                    'valor' => 5.00,
                    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
                    'tipo_activacion' => Descuento::ACTIVACION_PAGO_ANTICIPADO,
                    'dias_anticipacion' => 15,
                    'permite_acumulacion' => true,
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $fechaFin->format('Y-m-d'),
                    'status' => Descuento::STATUS_ACTIVO,
                ]
            );

            if ($descuento->wasRecentlyCreated) {
                // Asignar listas de precios
                if ($listasPrecios->count() > 0) {
                    $listaIds = $listasPrecios->take(2)->pluck('id')->toArray();
                    $descuento->listasPrecios()->attach($listaIds);
                }
                $creados++;
                $this->command->info("Descuento creado: {$descuento->nombre}");
            }
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear descuento: {$exception->getMessage()}");
            Log::error("Error al crear descuento", ['exception' => $exception->getMessage()]);
        }

        // 2. Promoción de Matrícula - Porcentual - Matrícula - No Acumulable - Aprobado
        try {
            $fechaInicio = Carbon::now()->addDays(10);
            $fechaFin = Carbon::now()->addMonths(1);

            $descuento = Descuento::firstOrCreate(
                ['nombre' => 'Promoción Matrícula Enero 2025'],
                [
                    'nombre' => 'Promoción Matrícula Enero 2025',
                    'codigo_descuento' => null,
                    'descripcion' => '10% de descuento en matrículas realizadas durante enero 2025',
                    'tipo' => Descuento::TIPO_PORCENTUAL,
                    'valor' => 10.00,
                    'aplicacion' => Descuento::APLICACION_MATRICULA,
                    'tipo_activacion' => Descuento::ACTIVACION_PROMOCION_MATRICULA,
                    'dias_anticipacion' => null,
                    'permite_acumulacion' => false,
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $fechaFin->format('Y-m-d'),
                    'status' => Descuento::STATUS_APROBADO,
                ]
            );

            if ($descuento->wasRecentlyCreated) {
                if ($listasPrecios->count() > 0) {
                    $listaIds = $listasPrecios->take(1)->pluck('id')->toArray();
                    $descuento->listasPrecios()->attach($listaIds);
                }
                if ($poblaciones->count() > 0) {
                    $poblacionIds = $poblaciones->take(2)->pluck('id')->toArray();
                    $descuento->poblaciones()->attach($poblacionIds);
                }
                $creados++;
                $this->command->info("Descuento creado: {$descuento->nombre}");
            }
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear descuento: {$exception->getMessage()}");
        }

        // 3. Código Promocional - Valor Fijo - Cuota - Acumulable - Activo
        try {
            $fechaInicio = Carbon::now()->subDays(7);
            $fechaFin = Carbon::now()->addMonths(6);

            $descuento = Descuento::firstOrCreate(
                ['codigo_descuento' => 'PROMO2025'],
                [
                    'nombre' => 'Promoción Especial 2025',
                    'codigo_descuento' => 'PROMO2025',
                    'descripcion' => 'Descuento de $50,000 en cuotas con código promocional',
                    'tipo' => Descuento::TIPO_VALOR_FIJO,
                    'valor' => 50000.00,
                    'aplicacion' => Descuento::APLICACION_CUOTA,
                    'tipo_activacion' => Descuento::ACTIVACION_CODIGO_PROMOCIONAL,
                    'dias_anticipacion' => null,
                    'permite_acumulacion' => true,
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $fechaFin->format('Y-m-d'),
                    'status' => Descuento::STATUS_ACTIVO,
                ]
            );

            if ($descuento->wasRecentlyCreated) {
                if ($listasPrecios->count() > 0) {
                    $listaIds = $listasPrecios->pluck('id')->toArray();
                    $descuento->listasPrecios()->attach($listaIds);
                }
                if ($productos->count() > 0) {
                    $productoIds = $productos->take(3)->pluck('id')->toArray();
                    $descuento->productos()->attach($productoIds);
                }
                $creados++;
                $this->command->info("Descuento creado: {$descuento->nombre}");
            }
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear descuento: {$exception->getMessage()}");
        }

        // 4. Descuento por Pago Anticipado - Valor Fijo - Matrícula - No Acumulable - En Proceso
        try {
            $fechaInicio = Carbon::now()->addMonths(1);
            $fechaFin = Carbon::now()->addMonths(7);

            $descuento = Descuento::firstOrCreate(
                ['nombre' => 'Descuento Matrícula Pago Anticipado'],
                [
                    'nombre' => 'Descuento Matrícula Pago Anticipado',
                    'codigo_descuento' => null,
                    'descripcion' => 'Descuento de $30,000 en matrícula por pago 20 días antes',
                    'tipo' => Descuento::TIPO_VALOR_FIJO,
                    'valor' => 30000.00,
                    'aplicacion' => Descuento::APLICACION_MATRICULA,
                    'tipo_activacion' => Descuento::ACTIVACION_PAGO_ANTICIPADO,
                    'dias_anticipacion' => 20,
                    'permite_acumulacion' => false,
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $fechaFin->format('Y-m-d'),
                    'status' => Descuento::STATUS_EN_PROCESO,
                ]
            );

            if ($descuento->wasRecentlyCreated) {
                if ($listasPrecios->count() > 0) {
                    $listaIds = $listasPrecios->take(1)->pluck('id')->toArray();
                    $descuento->listasPrecios()->attach($listaIds);
                }
                if ($sedes->count() > 0) {
                    $sedeIds = $sedes->take(2)->pluck('id')->toArray();
                    $descuento->sedes()->attach($sedeIds);
                }
                $creados++;
                $this->command->info("Descuento creado: {$descuento->nombre}");
            }
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear descuento: {$exception->getMessage()}");
        }

        // 5. Descuento Vencido - Inactivo
        try {
            $fechaInicio = Carbon::now()->subMonths(6);
            $fechaFin = Carbon::now()->subMonths(1);

            $descuento = Descuento::firstOrCreate(
                ['nombre' => 'Descuento Vencido 2024'],
                [
                    'nombre' => 'Descuento Vencido 2024',
                    'codigo_descuento' => null,
                    'descripcion' => 'Descuento que ya venció',
                    'tipo' => Descuento::TIPO_PORCENTUAL,
                    'valor' => 8.00,
                    'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
                    'tipo_activacion' => Descuento::ACTIVACION_PROMOCION_MATRICULA,
                    'dias_anticipacion' => null,
                    'permite_acumulacion' => true,
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $fechaFin->format('Y-m-d'),
                    'status' => Descuento::STATUS_INACTIVO,
                ]
            );

            if ($descuento->wasRecentlyCreated) {
                if ($listasPrecios->count() > 0) {
                    $listaIds = $listasPrecios->take(1)->pluck('id')->toArray();
                    $descuento->listasPrecios()->attach($listaIds);
                }
                $creados++;
                $this->command->info("Descuento creado: {$descuento->nombre}");
            }
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear descuento: {$exception->getMessage()}");
        }

        // Crear descuentos adicionales usando factory
        $this->command->info('Creando descuentos adicionales con factory...');

        try {
            // Descuentos activos y vigentes
            Descuento::factory(3)
                ->activo()
                ->vigente()
                ->porcentual()
                ->aplicaValorTotal()
                ->acumulable()
                ->create()
                ->each(function ($descuento) use ($listasPrecios) {
                    if ($listasPrecios->count() > 0) {
                        $listaIds = $listasPrecios->random(min(2, $listasPrecios->count()))->pluck('id')->toArray();
                        $descuento->listasPrecios()->attach($listaIds);
                    }
                });

            // Descuentos aprobados (se activarán automáticamente)
            Descuento::factory(2)
                ->aprobado()
                ->porcentual()
                ->aplicaMatricula()
                ->noAcumulable()
                ->create()
                ->each(function ($descuento) use ($listasPrecios, $poblaciones) {
                    if ($listasPrecios->count() > 0) {
                        $listaIds = $listasPrecios->random(min(1, $listasPrecios->count()))->pluck('id')->toArray();
                        $descuento->listasPrecios()->attach($listaIds);
                    }
                    if ($poblaciones->count() > 0) {
                        $poblacionIds = $poblaciones->random(min(2, $poblaciones->count()))->pluck('id')->toArray();
                        $descuento->poblaciones()->attach($poblacionIds);
                    }
                });

            // Descuentos con código promocional
            Descuento::factory(2)
                ->activo()
                ->vigente()
                ->codigoPromocional()
                ->valorFijo()
                ->aplicaCuota()
                ->acumulable()
                ->create()
                ->each(function ($descuento) use ($listasPrecios, $productos) {
                    if ($listasPrecios->count() > 0) {
                        $listaIds = $listasPrecios->random(min(2, $listasPrecios->count()))->pluck('id')->toArray();
                        $descuento->listasPrecios()->attach($listaIds);
                    }
                    if ($productos->count() > 0) {
                        $productoIds = $productos->random(min(3, $productos->count()))->pluck('id')->toArray();
                        $descuento->productos()->attach($productoIds);
                    }
                });

            // Descuentos en proceso
            Descuento::factory(2)
                ->enProceso()
                ->pagoAnticipado()
                ->porcentual()
                ->aplicaValorTotal()
                ->noAcumulable()
                ->create()
                ->each(function ($descuento) use ($listasPrecios, $sedes) {
                    if ($listasPrecios->count() > 0) {
                        $listaIds = $listasPrecios->random(min(1, $listasPrecios->count()))->pluck('id')->toArray();
                        $descuento->listasPrecios()->attach($listaIds);
                    }
                    if ($sedes->count() > 0) {
                        $sedeIds = $sedes->random(min(2, $sedes->count()))->pluck('id')->toArray();
                        $descuento->sedes()->attach($sedeIds);
                    }
                });

            $creados += 9;
            $this->command->info('Descuentos adicionales creados exitosamente.');
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear descuentos adicionales: {$exception->getMessage()}");
            Log::error("Error al crear descuentos adicionales", [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        $this->command->info("Descuentos creados: {$creados}");
        if ($errores > 0) {
            $this->command->warn("Errores encontrados: {$errores}");
        }
        $this->command->info('Finalizada la creación de descuentos.');
    }
}

