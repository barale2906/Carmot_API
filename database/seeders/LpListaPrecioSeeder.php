<?php

namespace Database\Seeders;

use App\Models\Configuracion\Poblacion;
use App\Models\Financiero\Lp\LpListaPrecio;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeder LpListaPrecioSeeder
 *
 * Seeder para crear listas de precios de ejemplo.
 * Crea listas de precios con diferentes estados y fechas de vigencia.
 *
 * @package Database\Seeders
 */
class LpListaPrecioSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     * Crea listas de precios con diferentes configuraciones.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Iniciando creación de listas de precios...');

        // Verificar que existan poblaciones
        $poblaciones = Poblacion::all();
        if ($poblaciones->count() === 0) {
            $this->command->warn('No hay poblaciones disponibles. Las listas se crearán sin poblaciones asignadas.');
        }

        $creados = 0;
        $errores = 0;

        // Crear lista vigente (activa y con fechas actuales)
        try {
            $fechaInicio = Carbon::now()->subMonths(1);
            $fechaFin = Carbon::now()->addMonths(11);

            $listaVigente = LpListaPrecio::firstOrCreate(
                ['codigo' => 'LP-VIG-2024'],
                [
                    'nombre' => 'Lista de Precios Vigente 2024',
                    'codigo' => 'LP-VIG-2024',
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $fechaFin->format('Y-m-d'),
                    'descripcion' => 'Lista de precios vigente para el año 2024',
                    'status' => LpListaPrecio::STATUS_ACTIVA,
                ]
            );

            if ($listaVigente->wasRecentlyCreated) {
                // Asignar poblaciones si existen
                if ($poblaciones->count() > 0) {
                    $poblacionesIds = $poblaciones->take(3)->pluck('id')->toArray();
                    $listaVigente->poblaciones()->attach($poblacionesIds);
                }
                $creados++;
                $this->command->info("Lista vigente creada: {$listaVigente->nombre}");
            }
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear lista vigente: {$exception->getMessage()}");
            Log::error("Error al crear lista vigente", [
                'exception' => $exception->getMessage(),
            ]);
        }

        // Crear lista en proceso
        try {
            $fechaInicio = Carbon::now()->addMonths(2);
            $fechaFin = Carbon::now()->addMonths(14);

            $listaEnProceso = LpListaPrecio::firstOrCreate(
                ['codigo' => 'LP-PROC-2025'],
                [
                    'nombre' => 'Lista de Precios 2025 - En Proceso',
                    'codigo' => 'LP-PROC-2025',
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $fechaFin->format('Y-m-d'),
                    'descripcion' => 'Lista de precios en proceso para el año 2025',
                    'status' => LpListaPrecio::STATUS_EN_PROCESO,
                ]
            );

            if ($listaEnProceso->wasRecentlyCreated) {
                if ($poblaciones->count() > 0) {
                    $poblacionesIds = $poblaciones->take(2)->pluck('id')->toArray();
                    $listaEnProceso->poblaciones()->attach($poblacionesIds);
                }
                $creados++;
                $this->command->info("Lista en proceso creada: {$listaEnProceso->nombre}");
            }
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear lista en proceso: {$exception->getMessage()}");
        }

        // Crear lista aprobada (se activará automáticamente cuando llegue la fecha)
        try {
            $fechaInicio = Carbon::now()->addDays(15);
            $fechaFin = Carbon::now()->addMonths(12);

            $listaAprobada = LpListaPrecio::firstOrCreate(
                ['codigo' => 'LP-APR-2025'],
                [
                    'nombre' => 'Lista de Precios 2025 - Aprobada',
                    'codigo' => 'LP-APR-2025',
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $fechaFin->format('Y-m-d'),
                    'descripcion' => 'Lista de precios aprobada para el año 2025',
                    'status' => LpListaPrecio::STATUS_APROBADA,
                ]
            );

            if ($listaAprobada->wasRecentlyCreated) {
                if ($poblaciones->count() > 0) {
                    $poblacionesIds = $poblaciones->pluck('id')->toArray();
                    $listaAprobada->poblaciones()->attach($poblacionesIds);
                }
                $creados++;
                $this->command->info("Lista aprobada creada: {$listaAprobada->nombre}");
            }
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear lista aprobada: {$exception->getMessage()}");
        }

        // Crear lista vencida (inactiva)
        try {
            $fechaInicio = Carbon::now()->subMonths(12);
            $fechaFin = Carbon::now()->subMonths(1);

            $listaVencida = LpListaPrecio::firstOrCreate(
                ['codigo' => 'LP-VEN-2023'],
                [
                    'nombre' => 'Lista de Precios 2023 - Vencida',
                    'codigo' => 'LP-VEN-2023',
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_fin' => $fechaFin->format('Y-m-d'),
                    'descripcion' => 'Lista de precios vencida del año 2023',
                    'status' => LpListaPrecio::STATUS_INACTIVA,
                ]
            );

            if ($listaVencida->wasRecentlyCreated) {
                if ($poblaciones->count() > 0) {
                    $poblacionesIds = $poblaciones->take(2)->pluck('id')->toArray();
                    $listaVencida->poblaciones()->attach($poblacionesIds);
                }
                $creados++;
                $this->command->info("Lista vencida creada: {$listaVencida->nombre}");
            }
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear lista vencida: {$exception->getMessage()}");
        }

        // Crear listas adicionales usando factory
        $this->command->info('Creando listas adicionales con factory...');

        try {
            // Listas en proceso
            LpListaPrecio::factory(3)
                ->enProceso()
                ->futura()
                ->conPoblacionesAleatorias(2)
                ->create();

            // Listas aprobadas
            LpListaPrecio::factory(2)
                ->aprobada()
                ->futura()
                ->conPoblacionesAleatorias(3)
                ->create();

            // Listas vencidas
            LpListaPrecio::factory(2)
                ->inactiva()
                ->vencida()
                ->conPoblacionesAleatorias(2)
                ->create();

            $creados += 7;
            $this->command->info('Listas adicionales creadas exitosamente.');
        } catch (Exception $exception) {
            $errores++;
            $this->command->error("Error al crear listas adicionales: {$exception->getMessage()}");
        }

        $this->command->info("Listas de precios creadas: {$creados}");
        if ($errores > 0) {
            $this->command->warn("Errores encontrados: {$errores}");
        }
        $this->command->info('Finalizada la creación de listas de precios.');
    }
}
