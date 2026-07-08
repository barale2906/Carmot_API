<?php

namespace App\Console\Commands\Financiero;

use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Descuento\DescuentoAplicado;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando AplicarMoraDiaria
 *
 * Aplica mora a las carteras vencidas que aún tienen saldo pendiente.
 * Se ejecuta diariamente. Usa fecha_ultimo_cobro_mora para evitar doble cobro en el mismo día.
 *
 * Flujo por cartera vencida:
 *  1. Busca el sobrecargo de mora activo (tipo_activacion='mora_automatica').
 *  2. Calcula: valor_mora = saldo * porcentaje / 100.
 *  3. Incrementa cartera.mora_acumulada.
 *  4. Registra en descuento_aplicado (tipo_movimiento='sobrecargo').
 *  5. Actualiza fecha_ultimo_cobro_mora = hoy.
 *
 * @package App\Console\Commands\Financiero
 */
class AplicarMoraDiaria extends Command
{
    protected $signature = 'financiero:aplicar-mora-diaria';

    protected $description = 'Aplica mora diaria a las carteras vencidas con saldo pendiente';

    /**
     * Ejecuta el comando.
     *
     * @return int Código de salida
     */
    public function handle(): int
    {
        $hoy = Carbon::today();

        $this->info("Iniciando aplicación de mora diaria ({$hoy->toDateString()})...");

        // Buscar sobrecargos de mora activos y vigentes
        $sobrecargos = Descuento::moraAutomatica()->get();

        if ($sobrecargos->isEmpty()) {
            $this->line('No hay sobrecargos de mora activos configurados.');
            return Command::SUCCESS;
        }

        // Carteras vencidas con saldo pendiente y sin mora cobrada hoy
        $carteras = Cartera::where('fecha_vencimiento', '<', $hoy)
            ->where('saldo', '>', 0)
            ->whereIn('status', [Cartera::getStatusKey('Activa'), Cartera::getStatusKey('Abonada')])
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_ultimo_cobro_mora')
                  ->orWhereDate('fecha_ultimo_cobro_mora', '<', $hoy);
            })
            ->get();

        if ($carteras->isEmpty()) {
            $this->line('No hay carteras vencidas pendientes de cobro de mora.');
            return Command::SUCCESS;
        }

        $totalMora    = 0.0;
        $totalCartera = 0;

        DB::transaction(function () use ($carteras, $sobrecargos, $hoy, &$totalMora, &$totalCartera) {
            // Si hay múltiples sobrecargos de mora, aplicar todos (ej. mora fija + mora compuesta)
            foreach ($carteras as $cartera) {
                $moraPorCartera = 0.0;

                foreach ($sobrecargos as $sobrecargo) {
                    $saldo      = (float) $cartera->saldo;
                    $valorMora  = $sobrecargo->calcularSobrecargo($saldo);

                    if ($valorMora <= 0) {
                        continue;
                    }

                    // Registrar en historial de ajustes aplicados
                    DescuentoAplicado::create([
                        'descuento_id'   => $sobrecargo->id,
                        'tipo_movimiento' => Descuento::MOVIMIENTO_SOBRECARGO,
                        'concepto_tipo'  => 'cartera',
                        'concepto_id'    => $cartera->id,
                        'valor_original' => $saldo,
                        'valor_descuento' => $valorMora,
                        'valor_final'    => $saldo + $valorMora,
                        'sede_id'        => $cartera->sede_id,
                        'observaciones'  => "Mora diaria {$hoy->toDateString()} — {$sobrecargo->nombre}",
                    ]);

                    $moraPorCartera += $valorMora;
                }

                if ($moraPorCartera > 0) {
                    $cartera->increment('mora_acumulada', $moraPorCartera);
                    $cartera->update(['fecha_ultimo_cobro_mora' => $hoy]);
                    $totalMora    += $moraPorCartera;
                    $totalCartera++;
                }
            }
        });

        $this->info("Mora aplicada a {$totalCartera} cartera(s). Total mora: $" . number_format($totalMora, 2));

        return Command::SUCCESS;
    }
}
