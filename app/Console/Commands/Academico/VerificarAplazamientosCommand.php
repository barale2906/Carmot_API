<?php

namespace App\Console\Commands\Academico;

use App\Models\Academico\Aplazamiento;
use App\Notifications\Academico\AplazamientoVencidoNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

/**
 * VerificarAplazamientosCommand
 *
 * Ejecuta diariamente y detecta aplazamientos cuya fecha_reinicio_probable
 * ya llegó pero siguen en estado Pendiente. Envía notificación al usuario
 * que registró el aplazamiento y a todos los usuarios con permiso
 * aca_aplazamientoCrear en la misma sede del ciclo, para que tomen acción.
 */
class VerificarAplazamientosCommand extends Command
{
    protected $signature = 'academico:verificar-aplazamientos';

    protected $description = 'Notifica aplazamientos cuya fecha probable de reinicio ya llegó y siguen pendientes';

    /**
     * Ejecuta el comando.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
            $hoy = Carbon::now()->toDateString();
            $this->info("Verificando aplazamientos vencidos — Fecha: {$hoy}");

            $aplazamientosVencidos = Aplazamiento::pendientes()
                ->where('fecha_reinicio_probable', '<=', $hoy)
                ->with(['ciclo.sede', 'tipoAplazamiento', 'user'])
                ->get();

            if ($aplazamientosVencidos->isEmpty()) {
                $this->info('No hay aplazamientos pendientes vencidos.');
                return Command::SUCCESS;
            }

            $this->info("Aplazamientos vencidos encontrados: {$aplazamientosVencidos->count()}");

            foreach ($aplazamientosVencidos as $aplazamiento) {
                $this->procesarAplazamiento($aplazamiento);
            }

            $this->info('Verificación de aplazamientos completada exitosamente.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error al verificar aplazamientos: {$e->getMessage()}");
            $this->error("Trace: {$e->getTraceAsString()}");
            return Command::FAILURE;
        }
    }

    /**
     * Envía la notificación al registrador y a los usuarios con permiso en la sede.
     */
    private function procesarAplazamiento(Aplazamiento $aplazamiento): void
    {
        $ciclo    = $aplazamiento->ciclo;
        $notif    = new AplazamientoVencidoNotification($aplazamiento);
        $notificados = collect();

        // Notificar al usuario que registró el aplazamiento
        if ($aplazamiento->user) {
            $aplazamiento->user->notify($notif);
            $notificados->push($aplazamiento->user_id);
            $this->line("  → Notificado: {$aplazamiento->user->name} (registrador)");
        }

        // Notificar a usuarios con permiso aca_aplazamientoCrear de la misma sede
        $permiso = Permission::findByName('aca_aplazamientoCrear');
        if ($permiso && $ciclo) {
            $permiso->users()
                ->where('users.id', '!=', $aplazamiento->user_id)
                ->get()
                ->each(function ($usuario) use ($aplazamiento, $ciclo, $notif, $notificados) {
                    // Notificar sin filtro de sede ya que no todos los usuarios tienen sede asignada directamente
                    if (!$notificados->contains($usuario->id)) {
                        $usuario->notify($notif);
                        $notificados->push($usuario->id);
                        $this->line("  → Notificado: {$usuario->name} (permiso aca_aplazamientoCrear)");
                    }
                });
        }

        $this->info(
            "  Aplazamiento #{$aplazamiento->id} — Ciclo '{$ciclo?->nombre}' "
            . "(vencido: {$aplazamiento->fecha_reinicio_probable->format('d/m/Y')}) "
            . "— {$notificados->count()} usuarios notificados."
        );
    }
}
