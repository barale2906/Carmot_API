<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        // Comando para gestionar automáticamente los estados de las listas de precios
        // Activa listas aprobadas e inactiva listas vencidas diariamente
        $schedule->command('financiero:gestionar-listas-precios')->daily();

        // Comando para gestionar automáticamente los estados de los descuentos
        // Activa descuentos aprobados e inactiva descuentos vencidos diariamente
        $schedule->command('financiero:gestionar-descuentos')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
