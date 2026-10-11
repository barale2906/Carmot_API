<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

/**
 * Valida la edición de un movimiento del libro diario. Mismas reglas que el
 * registro; la edición solo se permite mientras el turno del movimiento esté abierto.
 */
class UpdateLdMovimientoRequest extends StoreLdMovimientoRequest
{
    /**
     * La edición no exige turno abierto de quien edita (lo valida el controlador sobre el turno del registro).
     */
    protected function exigeTurnoAbierto(): bool
    {
        return false;
    }
}
