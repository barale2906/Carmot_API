<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coherencia de datos académicos (bugs altos de la prueba integral):
 * B01 profesor con rol, B02 área de la sede, B06/B07 grupos del curso y la sede
 * del ciclo, B08 información de cálculo del ciclo y B14 ciclo de la matrícula.
 * Prueba integral 2: N02 horario dentro de la jornada de la sede y N23 grupos de
 * la programación de su sede y curso; N03 la sede del ciclo sin un estado inexistente.
 */
class CoherenciaAcademicaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private User $profesor;
    private Sede $sede;
    private Sede $otraSede;
    private Area $area;
    private Curso $curso;
    private Modulo $modulo;
    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permisos = ['aca_grupos', 'aca_grupoCrear', 'aca_grupoEditar', 'aca_ciclos', 'aca_cicloCrear', 'aca_cicloEditar', 'aca_matriculaCrear',
            'aca_programaciones', 'aca_programacionCrear', 'aca_programacionEditar'];
        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }
        Role::create(['name' => User::ROL_PROFESOR]);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo($permisos);
        $this->profesor = User::factory()->create();
        $this->profesor->assignRole(User::ROL_PROFESOR);

        $this->sede     = Sede::factory()->create();
        $this->otraSede = Sede::factory()->create();
        $this->area     = Area::factory()->active()->create();
        $this->sede->areas()->attach($this->area->id);

        $this->curso  = Curso::factory()->create();
        $this->modulo = Modulo::create(['nombre' => 'Módulo C', 'duracion' => 10, 'status' => 1]);
        DB::table('modulo_curso')->insert(['modulo_id' => $this->modulo->id, 'curso_id' => $this->curso->id, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->grupo = $this->grupoConHorario($this->sede, $this->modulo);

        $this->actingAs($this->usuario);
    }

    // ── B01 / B02: grupos ─────────────────────────────────────────────────────

    /** @test */
    public function el_profesor_del_grupo_debe_tener_el_rol_profesor(): void
    {
        $this->postJson(route('grupos.store'), $this->datosGrupo(['profesor_id' => User::factory()->create()->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profesor_id');

        $this->postJson(route('grupos.store'), $this->datosGrupo())->assertCreated();
    }

    /** @test */
    public function al_editar_no_se_puede_asignar_un_profesor_sin_el_rol(): void
    {
        $this->putJson(route('grupos.update', $this->grupo), ['profesor_id' => User::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profesor_id');
    }

    /** @test */
    public function el_horario_del_grupo_debe_caber_en_la_jornada_de_la_sede(): void
    {
        $this->sede->update(['hora_inicio' => '07:00:00', 'hora_fin' => '21:00:00']);
        $horario = fn (string $hora, int $horas) => ['horarios' => [['area_id' => $this->area->id, 'dia' => 'domingo', 'hora' => $hora, 'duracion_horas' => $horas]]];

        $this->postJson(route('grupos.store'), $this->datosGrupo($horario('22:00', 2)))
            ->assertUnprocessable()->assertJsonValidationErrors('horarios.0.hora');
        $this->postJson(route('grupos.store'), $this->datosGrupo($horario('20:00', 2)))
            ->assertUnprocessable()->assertJsonValidationErrors('horarios.0.hora');
        $this->postJson(route('grupos.store'), $this->datosGrupo($horario('06:30', 1)))
            ->assertUnprocessable()->assertJsonValidationErrors('horarios.0.hora');
        $this->postJson(route('grupos.store'), $this->datosGrupo($horario('19:00', 2)))->assertCreated();
    }

    /** @test */
    public function el_horario_del_grupo_debe_usar_un_area_de_su_sede(): void
    {
        $areaAjena = Area::factory()->active()->create();
        $this->otraSede->areas()->attach($areaAjena->id);
        $horario = ['dia' => 'lunes', 'hora' => '08:00', 'duracion_horas' => 2];

        $this->postJson(route('grupos.store'), $this->datosGrupo(['horarios' => [$horario + ['area_id' => $areaAjena->id]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('horarios.0.area_id');

        $this->postJson(route('grupos.horarios.store', $this->grupo), ['horarios' => [$horario + ['area_id' => $areaAjena->id]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('horarios.0.area_id');

        $this->postJson(route('grupos.horarios.store', $this->grupo), ['horarios' => [$horario + ['area_id' => $this->area->id]]])
            ->assertCreated();
    }

    // ── B06 / B07 / B08: ciclos ───────────────────────────────────────────────

    /** @test */
    public function el_ciclo_rechaza_grupos_de_otra_sede(): void
    {
        $ajeno = $this->grupoConHorario($this->otraSede, $this->modulo);

        $this->postJson(route('ciclos.store'), $this->datosCiclo([$ajeno->id]))
            ->assertUnprocessable()
            ->assertJsonFragment(["El grupo {$ajeno->nombre} no pertenece a la sede del ciclo."]);
    }

    /** @test */
    public function el_ciclo_rechaza_grupos_de_un_modulo_ajeno_al_curso(): void
    {
        $otroModulo = Modulo::create(['nombre' => 'Módulo ajeno', 'duracion' => 10, 'status' => 1]);
        $ajeno = $this->grupoConHorario($this->sede, $otroModulo);

        $this->postJson(route('ciclos.store'), $this->datosCiclo([$ajeno->id]))
            ->assertUnprocessable()
            ->assertJsonFragment(["El módulo del grupo {$ajeno->nombre} no pertenece al curso del ciclo."]);
    }

    /** @test */
    public function la_previsualizacion_con_sede_solo_lista_grupos_de_esa_sede(): void
    {
        $this->grupoConHorario($this->otraSede, $this->modulo);

        $this->getJson(route('ciclos.previsualizar', [
            'curso_id' => $this->curso->id, 'fecha_inicio' => now()->addWeek()->toDateString(), 'sede_id' => $this->sede->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.modulos.0.grupos')
            ->assertJsonPath('data.modulos.0.grupos.0.grupo_id', $this->grupo->id);
    }

    /** @test */
    public function la_informacion_de_calculo_del_ciclo_responde(): void
    {
        $this->postJson(route('ciclos.store'), $this->datosCiclo([$this->grupo->id]))->assertCreated();
        $ciclo = Ciclo::firstOrFail();

        $this->getJson(route('ciclos.informacion-calculo', $ciclo))
            ->assertOk()
            ->assertJsonPath('data.grupos.0.id', $this->grupo->id)
            ->assertJsonPath('data.grupos.0.con_fechas_activas', false);
    }

    // ── B14: matrículas ───────────────────────────────────────────────────────

    /** @test */
    public function la_matricula_exige_un_ciclo_de_su_sede_y_su_curso(): void
    {
        $ciclo = Ciclo::factory()->create(['sede_id' => $this->otraSede->id, 'curso_id' => Curso::factory()->create()->id]);

        $this->postJson(route('matriculas.store'), [
            'sede_id' => $this->sede->id,
            'curso_id' => $this->curso->id,
            'ciclo_id' => $ciclo->id,
            'estudiante_id' => User::factory()->create()->id,
            'matriculado_por_id' => $this->usuario->id,
            'comercial_id' => $this->usuario->id,
            'fecha_matricula' => now()->toDateString(),
            'fecha_inicio' => now()->addDay()->toDateString(),
            'monto' => 500000,
        ])
            ->assertUnprocessable()
            ->assertJsonFragment(['El ciclo seleccionado no pertenece a la sede de la matrícula.'])
            ->assertJsonFragment(['El ciclo seleccionado no pertenece al curso de la matrícula.']);
    }

    // ── N23: programaciones ───────────────────────────────────────────────────

    /** @test */
    public function la_programacion_solo_acepta_grupos_de_su_sede_y_su_curso(): void
    {
        $grupoAjeno  = $this->grupoConHorario($this->otraSede, $this->modulo);
        $otroModulo  = Modulo::create(['nombre' => 'Módulo ajeno', 'duracion' => 10, 'status' => 1]);
        $grupoModulo = $this->grupoConHorario($this->sede, $otroModulo);
        $datos = [
            'curso_id' => $this->curso->id, 'sede_id' => $this->sede->id, 'nombre' => 'Programación ' . uniqid(),
            'fecha_inicio' => now()->toDateString(), 'fecha_fin' => now()->addMonths(3)->toDateString(), 'jornada' => 0, 'status' => 1,
        ];

        $this->postJson(route('programaciones.store'), $datos + ['grupos' => [['grupo_id' => $grupoAjeno->id]]])
            ->assertUnprocessable()
            ->assertJsonFragment(["El grupo {$grupoAjeno->nombre} no pertenece a la sede de la programación."]);

        $id = $this->postJson(route('programaciones.store'), $datos + ['grupos' => [['grupo_id' => $this->grupo->id]]])
            ->assertCreated()->json('data.id');

        $this->getJson(route('programaciones.show', $id))->assertOk()->assertJsonPath('data.id', $id);

        $this->postJson(route('programaciones.asignar-grupos', $id), ['grupos' => [['grupo_id' => $grupoModulo->id]]])
            ->assertUnprocessable()
            ->assertJsonFragment(["El módulo del grupo {$grupoModulo->nombre} no pertenece al curso de la programación."]);

        $this->putJson(route('programaciones.update', $id), ['grupos' => [['grupo_id' => $grupoAjeno->id]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupos');

        $this->actingAs(User::factory()->create())
            ->postJson(route('programaciones.asignar-grupos', $id), ['grupos' => [['grupo_id' => $this->grupo->id]]])
            ->assertForbidden();
    }

    /** @test */
    public function la_sede_del_ciclo_no_devuelve_un_estado_inexistente(): void
    {
        // N03: la tabla sedes no tiene status; respondía null / "Desconocido" (como B04 en grupos)
        $ciclo = Ciclo::factory()->create(['sede_id' => $this->sede->id, 'curso_id' => $this->curso->id]);

        $sede = $this->getJson(route('ciclos.show', $ciclo))->assertOk()->json('data.sede');
        $this->assertSame($this->sede->id, $sede['id']);
        $this->assertArrayNotHasKey('status', $sede);
        $this->assertArrayNotHasKey('status_text', $sede);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function datosGrupo(array $datos = []): array
    {
        return array_merge([
            'sede_id' => $this->sede->id,
            'modulo_id' => $this->modulo->id,
            'profesor_id' => $this->profesor->id,
            'nombre' => 'Grupo ' . uniqid(),
            'inscritos' => 10,
            'jornada' => 1,
            'status' => 1,
        ], $datos);
    }

    private function datosCiclo(array $grupos): array
    {
        return [
            'sede_id' => $this->sede->id,
            'curso_id' => $this->curso->id,
            'nombre' => 'Ciclo ' . uniqid(),
            'fecha_inicio' => now()->addWeek()->toDateString(),
            'fecha_fin_automatica' => true,
            'status' => 1,
            'grupos' => $grupos,
        ];
    }

    private function grupoConHorario(Sede $sede, Modulo $modulo): Grupo
    {
        $grupo = Grupo::factory()->create(['sede_id' => $sede->id, 'modulo_id' => $modulo->id, 'profesor_id' => $this->profesor->id, 'status' => 1]);
        DB::table('horarios')->insert([
            'sede_id' => $sede->id, 'area_id' => $this->area->id, 'grupo_id' => $grupo->id, 'grupo_nombre' => $grupo->nombre,
            'tipo' => false, 'periodo' => true, 'dia' => 'lunes', 'hora' => '08:00:00', 'duracion_horas' => 2,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $grupo;
    }
}
