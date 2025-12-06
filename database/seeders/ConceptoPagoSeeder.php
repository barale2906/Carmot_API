<?php

namespace Database\Seeders;

use App\Models\Financiero\ConceptoPago\ConceptoPago;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeder ConceptoPagoSeeder
 *
 * Seeder para crear los conceptos de pago básicos del sistema financiero.
 * Crea conceptos de pago comunes como matrícula, mensualidades, recargos, etc.
 *
 * @package Database\Seeders
 */
class ConceptoPagoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     * Crea los conceptos de pago básicos del sistema.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Iniciando creación de conceptos de pago...');

        $conceptosPago = [
            [
                'nombre' => 'Matrícula',
                'tipo' => 1, // Financiero (índice 1)
                'valor' => 0.00, // Se establecerá según la lista de precios
            ],
            [
                'nombre' => 'Pago de mensualidad',
                'tipo' => 1, // Financiero (índice 1)
                'valor' => 0.00, // Se establecerá según la lista de precios
            ],
            [
                'nombre' => 'Recargo por pago con tarjeta',
                'tipo' => 1, // Financiero (índice 1)
                'valor' => 5000.00, // Ejemplo: 5,000 pesos
            ],
            [
                'nombre' => 'Cobro adicional por material',
                'tipo' => 2, // Inventario (índice 2)
                'valor' => 0.00, // Variable según el material
            ],
            [
                'nombre' => 'Pago por acuerdo de pago',
                'tipo' => 0, // Cartera (índice 0)
                'valor' => 0.00, // Se establece según el acuerdo
            ],
            [
                'nombre' => 'Recargo por mora',
                'tipo' => 1, // Financiero (índice 1)
                'valor' => 10000.00, // Ejemplo: 10,000 pesos
            ],
            [
                'nombre' => 'Pago de certificado',
                'tipo' => 3, // Otro (índice 3)
                'valor' => 25000.00, // Ejemplo: 25,000 pesos
            ],
            [
                'nombre' => 'Cobro por reposición de clase',
                'tipo' => 1, // Financiero (índice 1)
                'valor' => 50000.00, // Ejemplo: 50,000 pesos
            ],
            [
                'nombre' => 'Pago de uniforme',
                'tipo' => 2, // Inventario (índice 2)
                'valor' => 0.00, // Variable según el uniforme
            ],
            [
                'nombre' => 'Cobro por material didáctico',
                'tipo' => 2, // Inventario (índice 2)
                'valor' => 0.00, // Variable según el material
            ],
        ];

        $creados = 0;
        $errores = 0;

        foreach ($conceptosPago as $concepto) {
            try {
                $conceptoPago = ConceptoPago::firstOrCreate(
                    ['nombre' => $concepto['nombre']],
                    $concepto
                );

                if ($conceptoPago->wasRecentlyCreated) {
                    $creados++;
                    $this->command->info("Concepto de pago creado: {$concepto['nombre']}");
                    Log::info("Concepto de pago creado: {$concepto['nombre']}");
                } else {
                    $this->command->comment("Concepto de pago ya existe: {$concepto['nombre']}");
                }
            } catch (Exception $exception) {
                $errores++;
                $mensajeError = "Error al crear concepto de pago '{$concepto['nombre']}': {$exception->getMessage()}";
                $this->command->error($mensajeError);
                Log::error($mensajeError, [
                    'concepto' => $concepto,
                    'exception' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'line' => $exception->getLine(),
                ]);
            }
        }

        $this->command->info("Conceptos de pago creados: {$creados}");
        if ($errores > 0) {
            $this->command->warn("Errores encontrados: {$errores}");
        }
        $this->command->info('Finalizada la creación de conceptos de pago.');
    }
}

