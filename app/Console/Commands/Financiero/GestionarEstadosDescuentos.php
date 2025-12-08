<?php

namespace App\Console\Commands\Financiero;

use App\Models\Financiero\Descuento\Descuento;
use Illuminate\Console\Command;

/**
 * Comando GestionarEstadosDescuentos
 *
 * Gestiona automáticamente los estados de los descuentos.
 * Activa los descuentos aprobados cuando inicia su vigencia
 * e inactiva los descuentos activos que han perdido su vigencia.
 *
 * Este comando debe ejecutarse diariamente mediante el scheduler de Laravel.
 *
 * @package App\Console\Commands\Financiero
 */
class GestionarEstadosDescuentos extends Command
{
    /**
     * La firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'financiero:gestionar-descuentos';

    /**
     * La descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Gestiona automáticamente los estados de los descuentos (activa aprobados e inactiva vencidos)';

    /**
     * Ejecuta el comando de consola.
     *
     * @return int Código de salida (0 = éxito, 1 = error)
     */
    public function handle(): int
    {
        $this->info('Iniciando gestión automática de estados de descuentos...');

        try {
            // Activar descuentos aprobados que deben activarse
            $descuentosParaActivar = Descuento::aprobadosParaActivar()->count();
            
            if ($descuentosParaActivar > 0) {
                Descuento::activarDescuentosAprobados();
                $this->info("✓ Se activaron {$descuentosParaActivar} descuento(s) aprobado(s).");
            } else {
                $this->line('  No hay descuentos aprobados para activar.');
            }

            // Inactivar descuentos activos que han perdido vigencia
            $descuentosParaInactivar = Descuento::activosParaInactivar()->count();
            
            if ($descuentosParaInactivar > 0) {
                Descuento::inactivarDescuentosVencidos();
                $this->info("✓ Se inactivaron {$descuentosParaInactivar} descuento(s) vencido(s).");
            } else {
                $this->line('  No hay descuentos activos para inactivar.');
            }

            $this->info('Gestión automática de estados de descuentos completada exitosamente.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error al gestionar los estados de los descuentos: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}

