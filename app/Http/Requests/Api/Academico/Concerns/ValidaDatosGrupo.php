<?php

namespace App\Http\Requests\Api\Academico\Concerns;

use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use App\Models\User;

/**
 * Validaciones compartidas al crear y editar grupos y sus horarios:
 * el profesor debe tener el rol profesor (B01), las áreas de los horarios
 * deben estar asignadas a la sede del grupo (B02) y cada clase debe empezar
 * y terminar dentro del horario de atención de la sede (N02).
 */
trait ValidaDatosGrupo
{
    /**
     * Agrega un error en `profesor_id` si el usuario no tiene el rol profesor.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param mixed $profesorId
     * @return void
     */
    protected function validarProfesor($validator, $profesorId): void
    {
        if (! $profesorId || $validator->errors()->has('profesor_id')) {
            return;
        }

        $esProfesor = User::whereKey($profesorId)
            ->whereHas('roles', fn ($q) => $q->where('name', User::ROL_PROFESOR))
            ->exists();

        if (! $esProfesor) {
            $validator->errors()->add('profesor_id', 'El usuario seleccionado no tiene el rol de profesor.');
        }
    }

    /**
     * Agrega un error por cada horario cuya área no está asignada a la sede del grupo.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param mixed $sedeId
     * @param mixed $horarios
     * @return void
     */
    protected function validarAreasEnSede($validator, $sedeId, $horarios): void
    {
        if (! $sedeId || ! is_array($horarios)) {
            return;
        }

        $areaIds = collect($horarios)->pluck('area_id')->filter()->unique();
        if ($areaIds->isEmpty()) {
            return;
        }

        $enSede = Area::whereKey($areaIds)
            ->whereHas('sedes', fn ($q) => $q->where('sedes.id', $sedeId))
            ->pluck('id');

        foreach ($horarios as $i => $horario) {
            $areaId = $horario['area_id'] ?? null;
            if ($areaId && ! $enSede->contains((int) $areaId) && ! $validator->errors()->has("horarios.{$i}.area_id")) {
                $validator->errors()->add("horarios.{$i}.area_id", 'El área seleccionada no está asignada a la sede del grupo.');
            }
        }
    }

    /**
     * Agrega un error por cada horario que empieza antes de la apertura de la sede o
     * termina después de su cierre (la duración por defecto es 1 hora, como al guardarlo).
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param mixed $sedeId
     * @param mixed $horarios
     * @return void
     */
    protected function validarHorariosEnJornadaSede($validator, $sedeId, $horarios): void
    {
        $sede = $sedeId ? Sede::find($sedeId) : null;
        if (! $sede || ! $sede->hora_inicio || ! $sede->hora_fin || ! is_array($horarios)) {
            return;
        }

        $apertura = $sede->hora_inicio->format('H:i');
        $cierre   = $sede->hora_fin->format('H:i');

        foreach ($horarios as $i => $horario) {
            $hora = $horario['hora'] ?? null;
            if (! is_string($hora) || $validator->errors()->has("horarios.{$i}.hora")) {
                continue;
            }

            $inicioMin = (int) substr($hora, 0, 2) * 60 + (int) substr($hora, 3, 2);
            $finMin    = $inicioMin + 60 * (int) ($horario['duracion_horas'] ?? 1);
            $fin       = sprintf('%02d:%02d', intdiv($finMin, 60), $finMin % 60);

            if (substr($hora, 0, 5) < $apertura || $finMin > 24 * 60 || $fin > $cierre) {
                $validator->errors()->add(
                    "horarios.{$i}.hora",
                    "La clase ({$hora} a {$fin}) debe quedar dentro del horario de la sede ({$apertura} a {$cierre})."
                );
            }
        }
    }
}
