<?php

namespace App\Traits;

use App\Models\Crm\Agenda;
use Illuminate\Validation\Rule;

/**
 * Validación de `proxima_accion`: la acción que se deja programada en la agenda al registrar un
 * lead o un seguimiento (llamar de nuevo, visita, mensaje...). Se usa en FormRequests del CRM;
 * programarla exige el permiso de crear agendas.
 */
trait HasProximaAccionValidation
{
    /**
     * Reglas de la próxima acción.
     *
     * @return array<string, array<mixed>>
     */
    protected function proximaAccionRules(): array
    {
        return [
            'proxima_accion' => ['nullable', 'array', Rule::prohibitedIf(fn () => ! $this->user()->can('crm_agendaCrear'))],
            'proxima_accion.tipo' => ['required_with:proxima_accion', 'string', Rule::in(array_keys(Agenda::TIPOS))],
            'proxima_accion.fecha' => ['required_with:proxima_accion', 'date', 'after_or_equal:today'],
            'proxima_accion.hora' => ['required_with:proxima_accion', 'date_format:H:i'],
            'proxima_accion.nota' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Mensajes de validación de la próxima acción.
     *
     * @return array<string, string>
     */
    protected function proximaAccionMessages(): array
    {
        return [
            'proxima_accion.prohibited' => 'No tiene permiso para programar acciones en la agenda.',
            'proxima_accion.tipo.in' => 'El tipo de acción debe ser: ' . implode(', ', Agenda::TIPOS) . '.',
            'proxima_accion.fecha.after_or_equal' => 'La próxima acción no puede programarse en una fecha pasada.',
            'proxima_accion.hora.date_format' => 'La hora debe tener el formato HH:mm.',
        ];
    }

    /**
     * Nombres de los campos de la próxima acción para los mensajes de validación.
     *
     * @return array<string, string>
     */
    protected function proximaAccionAttributes(): array
    {
        return [
            'proxima_accion' => 'próxima acción',
            'proxima_accion.tipo' => 'tipo de la próxima acción',
            'proxima_accion.fecha' => 'fecha de la próxima acción',
            'proxima_accion.hora' => 'hora de la próxima acción',
            'proxima_accion.nota' => 'nota de la próxima acción',
        ];
    }
}
