<?php

namespace Database\Seeders;

use App\Models\Financiero\ConceptoPago\ConceptoPago;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeder ConceptoPagoSeeder
 *
 * Siembra los conceptos de pago básicos del sistema.
 * Los conceptos de tipo Cartera (tipo=0) son los que generan y saldan registros
 * en la tabla carteras; los demás son para cobros financieros, de inventario o varios.
 */
class ConceptoPagoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        $this->command->info('Iniciando creación de conceptos de pago...');

        $conceptosPago = [
            // ── Tipo 0: Cartera ───────────────────────────────────────────────
            [
                'nombre' => ConceptoPago::MATRICULA,          // 'Matrícula'
                'tipo'   => 0,
                'valor'  => 0.00,
            ],
            [
                'nombre' => ConceptoPago::MENSUALIDAD,        // 'Pago de mensualidad'
                'tipo'   => 0,
                'valor'  => 0.00,
            ],
            [
                'nombre' => ConceptoPago::INICIAL_ACUERDO,    // 'Inicial Acuerdo'
                'tipo'   => 0,
                'valor'  => 0.00,
            ],
            [
                'nombre' => ConceptoPago::CUOTA_ACUERDO,      // 'Cuota Acuerdo'
                'tipo'   => 0,
                'valor'  => 0.00,
            ],
            [
                'nombre' => ConceptoPago::DESCUENTO,          // 'Descuento pronto pago'
                'tipo'   => 0,
                'valor'  => 0.00,
            ],

            // ── Tipo 1: Financiero ────────────────────────────────────────────
            [
                'nombre' => 'Recargo por pago con tarjeta',
                'tipo'   => 1,
                'valor'  => 5000.00,
            ],
            [
                'nombre' => 'Recargo por mora',
                'tipo'   => 1,
                'valor'  => 10000.00,
            ],
            [
                'nombre' => 'Cobro por reposición de clase',
                'tipo'   => 1,
                'valor'  => 50000.00,
            ],

            // ── Tipo 2: Inventario ────────────────────────────────────────────
            [
                'nombre' => 'Cobro adicional por material',
                'tipo'   => 2,
                'valor'  => 0.00,
            ],
            [
                'nombre' => 'Pago de uniforme',
                'tipo'   => 2,
                'valor'  => 0.00,
            ],
            [
                'nombre' => 'Cobro por material didáctico',
                'tipo'   => 2,
                'valor'  => 0.00,
            ],

            // ── Tipo 3: Otro ──────────────────────────────────────────────────
            [
                'nombre' => 'Pago de certificado',
                'tipo'   => 3,
                'valor'  => 25000.00,
            ],
            [
                'nombre' => 'Diploma',
                'tipo'   => 3,
                'valor'  => 30000.00,
            ],
            [
                'nombre' => 'Sábana de notas',
                'tipo'   => 3,
                'valor'  => 15000.00,
            ],
            [
                'nombre' => 'Exámenes médicos',
                'tipo'   => 3,
                'valor'  => 0.00,
            ],
            [
                'nombre' => 'Recuperación de módulo',
                'tipo'   => 3,
                'valor'  => 0.00,
            ],
        ];

        $creados = 0;
        $errores = 0;

        foreach ($conceptosPago as $concepto) {
            try {
                $registro = ConceptoPago::firstOrCreate(
                    ['nombre' => $concepto['nombre']],
                    $concepto
                );

                // Corregir tipo si ya existía con valor incorrecto
                if (! $registro->wasRecentlyCreated && $registro->tipo !== $concepto['tipo']) {
                    $registro->update(['tipo' => $concepto['tipo']]);
                    $this->command->warn("Tipo corregido para: {$concepto['nombre']}");
                }

                if ($registro->wasRecentlyCreated) {
                    $creados++;
                    $this->command->info("Creado: {$concepto['nombre']}");
                } else {
                    $this->command->comment("Ya existe: {$concepto['nombre']}");
                }
            } catch (Exception $e) {
                $errores++;
                $mensaje = "Error al crear '{$concepto['nombre']}': {$e->getMessage()}";
                $this->command->error($mensaje);
                Log::error($mensaje, ['exception' => $e->getMessage()]);
            }
        }

        $this->command->info("Conceptos creados: {$creados}");

        if ($errores > 0) {
            $this->command->warn("Errores: {$errores}");
        }

        $this->command->info('Finalizado ConceptoPagoSeeder.');
    }
}
