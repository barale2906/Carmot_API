<?php

namespace Database\Seeders;

use App\Models\Financiero\Lp\LpTipoProducto;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeder LpTipoProductoSeeder
 *
 * Seeder para crear los tipos de productos básicos del sistema de listas de precios.
 * Crea tres tipos de productos: curso, módulo y complementario.
 * Los cursos y módulos son financiables, mientras que los complementarios no.
 *
 * @package Database\Seeders
 */
class LpTipoProductoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     * Crea los tipos de productos básicos: curso, módulo y complementario.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Iniciando creación de tipos de productos...');

        $tiposProducto = [
            [
                'nombre' => 'Curso',
                'codigo' => 'curso',
                'es_financiable' => true,
                'descripcion' => 'Curso completo que puede ser financiado',
                'status' => 1,
            ],
            [
                'nombre' => 'Módulo',
                'codigo' => 'modulo',
                'es_financiable' => true,
                'descripcion' => 'Módulo específico que puede ser financiado',
                'status' => 1,
            ],
            [
                'nombre' => 'Complementario',
                'codigo' => 'complementario',
                'es_financiable' => false,
                'descripcion' => 'Producto complementario que no puede ser financiado (ej: certificado de estudios)',
                'status' => 1,
            ],
        ];

        $creados = 0;
        $errores = 0;

        foreach ($tiposProducto as $tipo) {
            try {
                $tipoProducto = LpTipoProducto::firstOrCreate(
                    ['codigo' => $tipo['codigo']],
                    $tipo
                );

                if ($tipoProducto->wasRecentlyCreated) {
                    $creados++;
                    $this->command->info("Tipo de producto creado: {$tipo['nombre']} ({$tipo['codigo']})");
                    Log::info("Tipo de producto creado: {$tipo['nombre']} ({$tipo['codigo']})");
                } else {
                    $this->command->comment("Tipo de producto ya existe: {$tipo['nombre']} ({$tipo['codigo']})");
                }
            } catch (Exception $exception) {
                $errores++;
                $mensajeError = "Error al crear tipo de producto '{$tipo['nombre']}': {$exception->getMessage()}";
                $this->command->error($mensajeError);
                Log::error($mensajeError, [
                    'tipo' => $tipo,
                    'exception' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'line' => $exception->getLine(),
                ]);
            }
        }

        $this->command->info("Tipos de productos creados: {$creados}");
        if ($errores > 0) {
            $this->command->warn("Errores encontrados: {$errores}");
        }
        $this->command->info('Finalizada la creación de tipos de productos.');
    }
}
