<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdConsignacion;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;

/**
 * Valida la edición de una consignación. El efectivo disponible se calcula
 * sobre el turno de la consignación, devolviéndole su valor actual.
 */
class UpdateLdConsignacionRequest extends StoreLdConsignacionRequest
{
    /**
     * Turno de la consignación que se edita.
     */
    protected function turnoDelRegistro(): ?LdTurnoCaja
    {
        return $this->consignacion()->turnoCaja;
    }

    /**
     * Valor que la consignación ya descuenta del efectivo del turno.
     */
    protected function valorActual(): float
    {
        return (float) $this->consignacion()->valor;
    }

    private function consignacion(): LdConsignacion
    {
        return $this->route('ldConsignacion');
    }
}
