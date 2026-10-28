<?php

namespace App\Services\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\CicloCierreNota;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Servicio CierreNotasCicloService
 *
 * Cierra y reabre el registro de notas de un ciclo. Mientras está cerrado no se pueden
 * registrar, editar ni eliminar notas ni recuperaciones de sus estudiantes; la validación
 * vive en {@see \App\Http\Requests\Api\Academico\Concerns\ValidaNotasAbiertas} y en
 * {@see \App\Models\Academico\Grupo::notasCerradasPara()}.
 *
 * Cada acción queda en la bitácora `ciclo_cierre_notas`.
 *
 * @package App\Services\Academico
 */
class CierreNotasCicloService
{
    /**
     * Cierra las notas del ciclo.
     *
     * @param Ciclo $ciclo
     * @param User  $usuario
     * @return Ciclo
     */
    public function cerrar(Ciclo $ciclo, User $usuario): Ciclo
    {
        return $this->registrar($ciclo, $usuario, CicloCierreNota::ACCION_CIERRE, null, [
            'notas_cerradas_at'     => now(),
            'notas_cerradas_por_id' => $usuario->id,
        ]);
    }

    /**
     * Reabre las notas del ciclo dejando el motivo en la bitácora.
     *
     * @param Ciclo  $ciclo
     * @param User   $usuario
     * @param string $motivo
     * @return Ciclo
     */
    public function reabrir(Ciclo $ciclo, User $usuario, string $motivo): Ciclo
    {
        return $this->registrar($ciclo, $usuario, CicloCierreNota::ACCION_REAPERTURA, $motivo, [
            'notas_cerradas_at'     => null,
            'notas_cerradas_por_id' => null,
        ]);
    }

    /**
     * Aplica el cambio de estado y lo anota en la bitácora en una misma transacción.
     *
     * @param Ciclo                $ciclo
     * @param User                 $usuario
     * @param string               $accion
     * @param string|null          $motivo
     * @param array<string, mixed> $cambios
     * @return Ciclo
     */
    private function registrar(Ciclo $ciclo, User $usuario, string $accion, ?string $motivo, array $cambios): Ciclo
    {
        DB::transaction(function () use ($ciclo, $usuario, $accion, $motivo, $cambios) {
            $ciclo->update($cambios);
            $ciclo->cierresNotas()->create([
                'accion'     => $accion,
                'usuario_id' => $usuario->id,
                'motivo'     => $motivo,
            ]);
        });

        return $ciclo->load(['notasCerradasPor', 'cierresNotas.usuario']);
    }
}
