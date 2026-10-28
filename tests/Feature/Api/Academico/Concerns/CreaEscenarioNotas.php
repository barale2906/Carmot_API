<?php

namespace Tests\Feature\Api\Academico\Concerns;

use App\Models\Academico\Ciclo;
use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Academico\NotaEstudiante;
use App\Models\Academico\TipoNotaEsquema;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Escenario común de las pruebas de recuperación, cierre por ciclo y consolidado de
 * notas: una sede con un ciclo, un grupo con su profesor, un estudiante matriculado y un
 * esquema de un solo tipo de nota (peso 100 %, escala 0–5). La coordinación tiene la
 * sede asignada y todos los permisos de notas.
 */
trait CreaEscenarioNotas
{
    protected const PERMISOS_NOTAS = ['aca_notas', 'aca_notaCrear', 'aca_notaEditar', 'aca_notaInactivar', 'aca_notaCerrar', 'aca_notaReabrir'];

    protected Sede $sede;
    protected Ciclo $ciclo;
    protected Grupo $grupo;
    protected User $profesor;
    protected User $coordinador;
    protected User $estudiante;
    protected EsquemaCalificacion $esquema;
    protected TipoNotaEsquema $tipo;

    /**
     * Crea los permisos y el escenario base.
     *
     * @return void
     */
    protected function crearEscenarioNotas(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (self::PERMISOS_NOTAS as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->sede     = Sede::factory()->create();
        $this->profesor = User::factory()->create();
        $this->ciclo    = Ciclo::factory()->create(['sede_id' => $this->sede->id, 'status' => 1]);
        $this->grupo    = Grupo::factory()->create(['sede_id' => $this->sede->id, 'profesor_id' => $this->profesor->id, 'status' => 1]);
        $this->ciclo->grupos()->attach($this->grupo->id, ['orden' => 1]);

        $this->estudiante = $this->matricular();

        $this->coordinador = User::factory()->create();
        $this->coordinador->givePermissionTo(self::PERMISOS_NOTAS);
        $this->coordinador->sedes()->attach($this->sede->id);

        $this->esquema = EsquemaCalificacion::create([
            'modulo_id'      => $this->grupo->modulo_id,
            'grupo_id'       => $this->grupo->id,
            'profesor_id'    => $this->profesor->id,
            'nombre_esquema' => 'Esquema único',
            'status'         => 1,
        ]);
        $this->tipo = TipoNotaEsquema::create([
            'esquema_calificacion_id' => $this->esquema->id,
            'nombre_tipo'             => 'Examen final',
            'peso'                    => 100,
            'orden'                   => 1,
            'nota_minima'             => 0,
            'nota_maxima'             => 5,
        ]);
    }

    /**
     * Matricula un estudiante (nuevo si no se indica) en el ciclo del escenario.
     *
     * @param User|null  $estudiante
     * @param Ciclo|null $ciclo
     * @return User
     */
    protected function matricular(?User $estudiante = null, ?Ciclo $ciclo = null): User
    {
        $estudiante ??= User::factory()->create();
        $ciclo      ??= $this->ciclo;

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id'      => $ciclo->id,
            'curso_id'      => $ciclo->curso_id,
            'sede_id'       => $ciclo->sede_id,
            'status'        => 1,
        ]);

        return $estudiante;
    }

    /**
     * Registra la nota del único tipo del esquema: con peso 100 % es también la nota final.
     *
     * @param float     $nota
     * @param User|null $estudiante
     * @return NotaEstudiante
     */
    protected function registrarNotaFinal(float $nota, ?User $estudiante = null): NotaEstudiante
    {
        return NotaEstudiante::create([
            'estudiante_id'           => ($estudiante ?? $this->estudiante)->id,
            'grupo_id'                => $this->grupo->id,
            'modulo_id'               => $this->grupo->modulo_id,
            'esquema_calificacion_id' => $this->esquema->id,
            'tipo_nota_esquema_id'    => $this->tipo->id,
            'nota'                    => $nota,
            'nota_ponderada'          => $nota,
            'fecha_registro'          => now()->toDateString(),
            'registrado_por_id'       => $this->profesor->id,
            'status'                  => 1,
        ]);
    }

    /**
     * Marca las notas del ciclo como cerradas sin pasar por el endpoint.
     *
     * @return void
     */
    protected function cerrarCiclo(): void
    {
        $this->ciclo->update(['notas_cerradas_at' => now(), 'notas_cerradas_por_id' => $this->coordinador->id]);
    }
}
