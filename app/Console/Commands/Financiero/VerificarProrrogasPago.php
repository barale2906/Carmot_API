<?php

namespace App\Console\Commands\Financiero;

use App\Models\Financiero\Cartera\ProrrogaPago;
use App\Models\User;
use App\Notifications\Financiero\ProrrogaPagoVencidaNotification;
use Illuminate\Console\Command;

/**
 * VerificarProrrogasPago
 *
 * Revisa a diario los plazos nuevos de pago cuya fecha compromiso ya pasó: si las cuotas que
 * cubrían quedaron pagadas los marca `cumplido`; si no, `incumplido`, y avisa al estudiante y al
 * personal de la sede con permiso `fin_prorrogaPagoCancelar` (control de los acuerdos).
 */
class VerificarProrrogasPago extends Command
{
    protected $signature = 'financiero:verificar-prorrogas-pago';

    protected $description = 'Cierra los plazos nuevos de pago vencidos y avisa al estudiante y a la administración de los incumplidos';

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $cumplidas = 0;
        $incumplidas = 0;

        ProrrogaPago::porResolver()->with(['estudiante', 'sede'])->get()->each(function (ProrrogaPago $prorroga) use (&$cumplidas, &$incumplidas) {
            if ($prorroga->cuotasPagadas()) {
                $prorroga->update(['estado' => ProrrogaPago::CUMPLIDO]);
                $cumplidas++;

                return;
            }

            $prorroga->update(['estado' => ProrrogaPago::INCUMPLIDO]);
            $incumplidas++;

            $notificacion = new ProrrogaPagoVencidaNotification($prorroga);
            $this->destinatarios($prorroga)->each->notify($notificacion);
        });

        $this->info("Plazos de pago cerrados: {$cumplidas} cumplidos, {$incumplidas} incumplidos.");

        return Command::SUCCESS;
    }

    /**
     * El estudiante y el personal de la sede de la prórroga que gestiona los acuerdos.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function destinatarios(ProrrogaPago $prorroga): \Illuminate\Support\Collection
    {
        return User::permission('fin_prorrogaPagoCancelar')->get()
            ->filter(fn (User $u) => $prorroga->sede_id && $u->tieneAccesoSede((int) $prorroga->sede_id))
            ->push($prorroga->estudiante)
            ->unique('id');
    }
}
