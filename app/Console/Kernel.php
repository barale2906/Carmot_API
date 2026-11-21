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

        // Notifica aplazamientos pendientes cuya fecha probable de reinicio ya llegó
        $schedule->command('academico:verificar-aplazamientos')->daily();

        // Pasa a Activo las matrículas Pendientes por iniciar cuyo ciclo ya comenzó
        $schedule->command('academico:activar-matriculas-iniciadas')->dailyAt('00:10');

        // Aplica mora diaria a las carteras vencidas con saldo pendiente
        $schedule->command('financiero:aplicar-mora-diaria')->dailyAt('00:30');

        // Cierra los plazos nuevos de pago vencidos y avisa de los incumplidos
        $schedule->command('financiero:verificar-prorrogas-pago')->dailyAt('00:40');
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
