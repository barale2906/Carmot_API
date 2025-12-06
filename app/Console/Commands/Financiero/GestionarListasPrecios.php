<?php

namespace App\Console\Commands\Financiero;

use App\Models\Financiero\Lp\LpListaPrecio;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Comando GestionarListasPrecios
 *
 * Comando programado que gestiona automáticamente los estados de las listas de precios.
 * Se ejecuta diariamente para:
 * 1. Activar listas aprobadas cuando inicia su período de vigencia
 * 2. Inactivar listas activas que han perdido su vigencia
 *
 * @package App\Console\Commands\Financiero
 */
class GestionarListasPrecios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'financiero:gestionar-listas-precios';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activa listas aprobadas e inactiva listas vencidas automáticamente';

    /**
     * Execute the console command.
     * Gestiona los estados de las listas de precios según su vigencia.
     *
     * @return int Código de salida del comando (0 = éxito, 1 = error)
     */
    public function handle(): int
    {
        try {
            $fechaActual = Carbon::now();
            $this->info("Iniciando gestión de listas de precios - Fecha: {$fechaActual->format('Y-m-d H:i:s')}");

            // 1. Activar listas aprobadas que inician su vigencia
            $activadas = LpListaPrecio::aprobadasParaActivar($fechaActual)->get();

            foreach ($activadas as $lista) {
                $lista->update(['status' => LpListaPrecio::STATUS_ACTIVA]);
                $this->info("Lista de precios '{$lista->nombre}' activada automáticamente.");
            }

            $this->info("Total de listas activadas: " . $activadas->count());

            // 2. Inactivar listas activas que pierden su vigencia
            $inactivadas = LpListaPrecio::activasParaInactivar($fechaActual)->get();

            foreach ($inactivadas as $lista) {
                $lista->update(['status' => LpListaPrecio::STATUS_INACTIVA]);
                $this->info("Lista de precios '{$lista->nombre}' inactivada automáticamente por vencimiento.");
            }

            $this->info("Total de listas inactivadas por vencimiento: " . $inactivadas->count());

            $this->info("Gestión de listas de precios completada exitosamente.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error al gestionar listas de precios: {$e->getMessage()}");
            $this->error("Trace: {$e->getTraceAsString()}");

            return Command::FAILURE;
        }
    }
}

