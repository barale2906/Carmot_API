<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\AsistenciaConfiguracion;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Academico\NotaEstudiante;
use App\Models\Academico\TipoNotaEsquema;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Evaluación de cierre de la matrícula y reglas para graduar o reprobar (N19, N20, N21
 * de la prueba integral 2): ciclo terminado, notas aprobadas, regla de asistencia y sede.
 */
class EvaluacionCierreTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinador;
    private Matricula $matricula;
    private Ciclo $ciclo;
    private Grupo $grupo;
    private TipoNotaEsquema $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['aca_matriculas', 'aca_matriculaEstado', 'aca_asistencias'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $sede  = Sede::factory()->create();
        $curso = Curso::factory()->create();
        $this->ciclo = Ciclo::factory()->create([
            'sede_id'      => $sede->id,
            'curso_id'     => $curso->id,
            'status'       => 1,
            'fecha_inicio' => now()->subWeeks(4)->toDateString(),
            'fecha_fin'    => now()->subDay()->toDateString(),
        ]);
        $this->grupo = Grupo::factory()->create(['sede_id' => $sede->id, 'status' => 1]);
        $this->ciclo->grupos()->attach($this->grupo->id, ['orden' => 1]);

        $this->matricula = Matricula::factory()->create([
            'sede_id'         => $sede->id,
            'curso_id'        => $curso->id,
            'ciclo_id'        => $this->ciclo->id,
            'status'          => 1,
            'fecha_matricula' => now()->subWeeks(5)->toDateString(),
        ]);

        $this->coordinador = User::factory()->create();
        $this->coordinador->givePermissionTo(['aca_matriculas', 'aca_matriculaEstado', 'aca_asistencias']);
        $this->coordinador->sedes()->attach($sede->id);

        $esquema = EsquemaCalificacion::create([
            'modulo_id'      => $this->grupo->modulo_id,
            'grupo_id'       => $this->grupo->id,
            'profesor_id'    => $this->grupo->profesor_id,
            'nombre_esquema' => 'Esquema único',
            'status'         => 1,
        ]);
        $this->tipo = TipoNotaEsquema::create([
            'esquema_calificacion_id' => $esquema->id,
            'nombre_tipo'             => 'Examen final',
            'peso'                    => 100,
            'orden'                   => 1,
            'nota_minima'             => 0,
            'nota_maxima'             => 5,
        ]);

        AsistenciaConfiguracion::create([
            'curso_id'                => $curso->id,
            'porcentaje_minimo'       => 75,
            'aplicar_justificaciones' => false,
            'perder_por_fallas'       => true,
            'fecha_inicio_vigencia'   => now()->subYear()->toDateString(),
        ]);
    }

    /**
     * Registra la nota final del estudiante en el único tipo de nota del esquema.
     */
    private function ponerNota(float $nota): void
    {
        NotaEstudiante::create([
            'estudiante_id'           => $this->matricula->estudiante_id,
            'grupo_id'                => $this->grupo->id,
            'modulo_id'               => $this->grupo->modulo_id,
            'esquema_calificacion_id' => $this->tipo->esquema_calificacion_id,
            'tipo_nota_esquema_id'    => $this->tipo->id,
            'nota'                    => $nota,
            'nota_ponderada'          => $nota,
            'fecha_registro'          => now()->subDays(2)->toDateString(),
            'registrado_por_id'       => $this->coordinador->id,
            'status'                  => 1,
        ]);
    }

    /**
     * Registra una clase de 3 h por cada estado indicado.
     *
     * @param array<int, string> $estados
     */
    private function registrarAsistencias(array $estados): void
    {
        foreach ($estados as $i => $estado) {
            $clase = AsistenciaClaseProgramada::factory()->create([
                'grupo_id'       => $this->grupo->id,
                'ciclo_id'       => $this->ciclo->id,
                'fecha_clase'    => now()->subDays(10 + $i)->toDateString(),
                'duracion_horas' => 3,
                'estado'         => 'dictada',
            ]);
            Asistencia::factory()->create([
                'estudiante_id'       => $this->matricula->estudiante_id,
                'clase_programada_id' => $clase->id,
                'grupo_id'            => $this->grupo->id,
                'ciclo_id'            => $this->ciclo->id,
                'modulo_id'           => $this->grupo->modulo_id,
                'curso_id'            => $this->ciclo->curso_id,
                'estado'              => $estado,
            ]);
        }
    }

    private function cambiar(int $estado, ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->coordinador)->postJson(
            route('matriculas.estado-academico', $this->matricula),
            ['estado_academico' => $estado, 'fecha' => now()->toDateString(), 'motivo' => 'Cierre del ciclo']
        );
    }

    /** @test */
    public function gradua_cuando_aprueba_notas_y_asistencia(): void
    {
        $this->ponerNota(4.2);
        $this->registrarAsistencias(['presente', 'presente', 'tardanza', 'ausente']);

        $this->actingAs($this->coordinador)
            ->getJson(route('matriculas.evaluacion-cierre', $this->matricula))
            ->assertOk()
            ->assertJsonPath('data.aprueba', true)
            ->assertJsonPath('data.ciclo_terminado', true)
            ->assertJsonPath('data.modulos.0.aprobado', true)
            ->assertJsonPath('data.asistencia.porcentaje', 75)
            ->assertJsonPath('data.motivos', []);

        $this->cambiar(Matricula::ESTADO_GRADUADO)
            ->assertOk()
            ->assertJsonPath('data.estado_academico_texto', 'Graduado')
            ->assertJsonPath('data.status', 0);
    }

    /** @test */
    public function no_gradua_con_nota_reprobada_ni_con_notas_pendientes(): void
    {
        $this->registrarAsistencias(['presente', 'presente']);

        $this->cambiar(Matricula::ESTADO_GRADUADO)
            ->assertUnprocessable()
            ->assertJsonFragment(['No se puede graduar: El módulo ' . $this->grupo->modulo->nombre . ' tiene notas pendientes.']);

        $this->ponerNota(2.5);

        $this->cambiar(Matricula::ESTADO_GRADUADO)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado_academico');
    }

    /** @test */
    public function no_gradua_si_pierde_por_fallas_y_las_justificadas_no_cuentan_si_la_regla_lo_dice(): void
    {
        $this->ponerNota(4.5);
        $this->registrarAsistencias(['presente', 'justificado', 'justificado', 'ausente']);

        $this->actingAs($this->coordinador)
            ->getJson(route('matriculas.evaluacion-cierre', $this->matricula))
            ->assertOk()
            ->assertJsonPath('data.aprueba', false)
            ->assertJsonPath('data.asistencia.porcentaje', 25)
            ->assertJsonPath('data.asistencia.pierde_por_fallas', true);

        $this->cambiar(Matricula::ESTADO_GRADUADO)->assertUnprocessable();
    }

    /** @test */
    public function no_gradua_ni_reprueba_antes_de_que_termine_el_ciclo(): void
    {
        $this->ciclo->update(['fecha_fin' => now()->addMonth()->toDateString()]);
        $this->ponerNota(4.5);
        $this->registrarAsistencias(['presente']);

        $this->cambiar(Matricula::ESTADO_GRADUADO)->assertUnprocessable()->assertJsonValidationErrors('estado_academico');
        $this->cambiar(Matricula::ESTADO_REPROBADO)->assertUnprocessable()->assertJsonValidationErrors('estado_academico');
    }

    /** @test */
    public function reprueba_antes_de_terminar_el_ciclo_si_ya_perdio_por_fallas(): void
    {
        // N24: el módulo tiene 12 h y el mínimo es 75 %: se pueden faltar 3 h como máximo
        $this->ciclo->update(['fecha_fin' => now()->addMonth()->toDateString()]);
        $this->grupo->modulo->update(['duracion' => 12]);

        $this->registrarAsistencias(['ausente']);
        $this->actingAs($this->coordinador)->getJson(route('matriculas.evaluacion-cierre', $this->matricula))
            ->assertOk()->assertJsonPath('data.asistencia.perdida_definitiva', false);
        $this->cambiar(Matricula::ESTADO_REPROBADO)->assertUnprocessable();

        // 6 h faltadas: aunque asista al resto, llega como máximo al 50 %
        Asistencia::query()->forceDelete();
        AsistenciaClaseProgramada::query()->forceDelete();
        $this->registrarAsistencias(['ausente', 'ausente']);
        $this->actingAs($this->coordinador)->getJson(route('matriculas.evaluacion-cierre', $this->matricula))
            ->assertOk()->assertJsonPath('data.asistencia.perdida_definitiva', true);

        $this->cambiar(Matricula::ESTADO_REPROBADO)
            ->assertOk()
            ->assertJsonPath('data.estado_academico_texto', 'Reprobado')
            ->assertJsonPath('data.status', 0);

        // La matrícula finalizó: ya no cuenta como vigente y el estudiante puede volver a matricularse
        $this->assertFalse(Matricula::byEstudiante($this->matricula->estudiante_id)->byCurso($this->matricula->curso_id)->vigentes()->exists());
    }

    /** @test */
    public function reprueba_al_terminar_el_ciclo_solo_si_no_aprueba(): void
    {
        $this->ponerNota(4.5);
        $this->registrarAsistencias(['presente']);

        $this->cambiar(Matricula::ESTADO_REPROBADO)->assertUnprocessable();

        NotaEstudiante::query()->update(['nota' => 1.5, 'nota_ponderada' => 1.5]);

        $this->cambiar(Matricula::ESTADO_REPROBADO)
            ->assertOk()
            ->assertJsonPath('data.estado_academico_texto', 'Reprobado')
            ->assertJsonPath('data.status', 0);
    }

    /** @test */
    public function de_graduado_solo_se_vuelve_a_activo(): void
    {
        $this->ponerNota(4.0);
        $this->registrarAsistencias(['presente']);
        $this->cambiar(Matricula::ESTADO_GRADUADO)->assertOk();
        $this->matricula->refresh();

        $this->cambiar(Matricula::ESTADO_RETIRADO)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado_academico');

        $this->cambiar(Matricula::ESTADO_ACTIVO)->assertOk()->assertJsonPath('data.status', 1);
    }

    /** @test */
    public function solo_se_gradua_desde_activo(): void
    {
        $this->ponerNota(4.0);
        $this->registrarAsistencias(['presente']);
        $this->cambiar(Matricula::ESTADO_RETIRADO)->assertOk();
        $this->matricula->refresh();

        $this->cambiar(Matricula::ESTADO_GRADUADO)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado_academico');
    }

    /** @test */
    public function no_cambia_el_estado_de_una_matricula_de_otra_sede(): void
    {
        $otraSede = User::factory()->create();
        $otraSede->givePermissionTo(['aca_matriculas', 'aca_matriculaEstado']);
        $otraSede->sedes()->attach(Sede::factory()->create()->id);

        $this->cambiar(Matricula::ESTADO_RETIRADO, $otraSede)->assertForbidden();
        $this->assertSame(Matricula::ESTADO_ACTIVO, $this->matricula->fresh()->estado_academico);
    }

    /** @test */
    public function la_evaluacion_exige_permiso_de_matriculas(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('matriculas.evaluacion-cierre', $this->matricula))
            ->assertForbidden();
    }

    /** @test */
    public function el_reporte_de_grupo_indica_quien_pierde_por_fallas(): void
    {
        $this->registrarAsistencias(['presente', 'ausente', 'ausente', 'ausente']);

        $this->actingAs($this->coordinador)
            ->getJson(route('asistencias.reporte-grupo', $this->grupo->id))
            ->assertOk()
            ->assertJsonPath('data.estudiantes.0.porcentaje_asistencia', 25)
            ->assertJsonPath('data.estudiantes.0.porcentaje_minimo', 75)
            ->assertJsonPath('data.estudiantes.0.cumple_minimo', false)
            ->assertJsonPath('data.estudiantes.0.pierde_por_fallas', true);
    }
}
