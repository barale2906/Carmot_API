<?php

namespace App\Console\Commands\Academico;

use App\Models\Academico\Matricula;
use App\Services\Academico\MatriculaEstadoService;
use Illuminate\Console\Command;

/**
 * Pasa a Activo las matrículas "Pendiente por iniciar" cuyo ciclo ya comenzó,
 * dejando constancia en el historial de estados.
 */
class ActivarMatriculasIniciadasCommand extends Command
{
    protected $signature = 'academico:activar-matriculas-iniciadas';

    protected $description = 'Activa las matrículas pendientes por iniciar cuyo ciclo ya inició';

    public function handle(MatriculaEstadoService $servicio): int
    {
        $activadas = 0;

        Matricula::where('estado_academico', Matricula::ESTADO_PENDIENTE_INICIO)
            ->where('status', '!=', 2)
            ->whereHas('ciclo', fn ($q) => $q->whereDate('fecha_inicio', '<=', today()))
            ->with('ciclo')
            ->each(function (Matricula $matricula) use ($servicio, &$activadas) {
                $servicio->cambiar(
                    $matricula,
                    Matricula::ESTADO_ACTIVO,
                    $matricula->ciclo->fecha_inicio->toDateString(),
                    'Inicio del ciclo',
                    null
                );
                $activadas++;
            });

        $this->info("Matrículas activadas: {$activadas}");

        return Command::SUCCESS;
    }
}
