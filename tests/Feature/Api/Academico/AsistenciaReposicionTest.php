<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\AsistenciaReposicion;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reposiciones de clase: el estudiante toma una clase en otro grupo de su curso, la misma semana
 * y con el mismo módulo; basta con registrarla y notificar al instituto.
 */
class AsistenciaReposicionTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $profesorDestino;
    private User $estudiante;
    private Grupo $grupoOrigen;
    private Grupo $grupoDestino;
    private Ciclo $cicloOrigen;
    private Ciclo $cicloDestino;
    private AsistenciaClaseProgramada $origen;
    private AsistenciaClaseProgramada $destino;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['aca_reposiciones', 'aca_reposicionCrear', 'aca_reposicionInactivar', 'aca_asistencias', 'aca_asistenciaCrear'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }
        Role::firstOrCreate(['name' => 'superusuario']);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('superusuario');
        $this->staff->givePermissionTo(['aca_reposiciones', 'aca_reposicionCrear', 'aca_reposicionInactivar', 'aca_asistencias', 'aca_asistenciaCrear']);

        $this->profesorDestino = User::factory()->create();
        $this->profesorDestino->assignRole(Role::firstOrCreate(['name' => User::ROL_PROFESOR]));
        $this->profesorDestino->givePermissionTo(['aca_asistencias', 'aca_asistenciaCrear']);

        $this->cicloOrigen = Ciclo::factory()->create(['status' => 1, 'fecha_inicio' => now()->subMonth()->toDateString(), 'fecha_fin' => now()->addMonth()->toDateString()]);
        $this->cicloDestino = Ciclo::factory()->create([
            'status' => 1, 'curso_id' => $this->cicloOrigen->curso_id,
            'fecha_inicio' => now()->subMonth()->toDateString(), 'fecha_fin' => now()->addMonth()->toDateString(),
        ]);

        $this->grupoOrigen = Grupo::factory()->create(['status' => 1]);
        $this->grupoDestino = Grupo::factory()->create(['status' => 1, 'modulo_id' => $this->grupoOrigen->modulo_id, 'profesor_id' => $this->profesorDestino->id]);
        $this->cicloOrigen->grupos()->attach($this->grupoOrigen->id, ['orden' => 1]);
        $this->cicloDestino->grupos()->attach($this->grupoDestino->id, ['orden' => 1]);

        $this->estudiante = User::factory()->create();
        Matricula::factory()->create([
            'ciclo_id' => $this->cicloOrigen->id, 'curso_id' => $this->cicloOrigen->curso_id,
            'estudiante_id' => $this->estudiante->id, 'status' => 1,
        ]);

        $lunes = now()->subWeek()->startOfWeek();
        $this->origen = $this->clase($this->grupoOrigen, $this->cicloOrigen, $lunes->copy()->addDay());
        $this->destino = $this->clase($this->grupoDestino, $this->cicloDestino, $lunes->copy()->addDays(3));
    }

    private function clase(Grupo $grupo, Ciclo $ciclo, $fecha, array $extra = []): AsistenciaClaseProgramada
    {
        return AsistenciaClaseProgramada::factory()->create($extra + [
            'grupo_id' => $grupo->id, 'ciclo_id' => $ciclo->id,
            'fecha_clase' => $fecha->toDateString(), 'estado' => 'programada',
        ]);
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'estudiante_id' => $this->estudiante->id,
            'clase_origen_id' => $this->origen->id,
            'clase_destino_id' => $this->destino->id,
            'motivo' => 'Cita médica',
        ];
    }

    /** @test */
    public function registra_la_reposicion_y_notifica_al_instituto(): void
    {
        $this->actingAs($this->staff)
            ->postJson(route('asistencia-reposiciones.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.clase_destino.grupo.id', $this->grupoDestino->id);

        $this->assertDatabaseHas('asistencia_reposiciones', [
            'estudiante_id' => $this->estudiante->id,
            'clase_origen_id' => $this->origen->id,
            'clase_destino_id' => $this->destino->id,
            'registrada_por_id' => $this->staff->id,
        ]);
        $this->assertCount(1, $this->profesorDestino->notifications);
        $this->assertSame('reposicion_clase', $this->profesorDestino->notifications->first()->data['tipo']);
    }

    /** @test */
    public function rechaza_una_clase_de_otro_modulo(): void
    {
        $this->grupoDestino->update(['modulo_id' => \App\Models\Academico\Modulo::factory()->create()->id]);

        $this->actingAs($this->staff)->postJson(route('asistencia-reposiciones.store'), $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('clase_destino_id');
    }

    /** @test */
    public function rechaza_una_clase_de_otra_semana(): void
    {
        $this->destino->update(['fecha_clase' => now()->subWeeks(2)->toDateString()]);

        $this->actingAs($this->staff)->postJson(route('asistencia-reposiciones.store'), $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('clase_destino_id');
    }

    /** @test */
    public function rechaza_una_clase_de_otro_curso(): void
    {
        $otroCiclo = Ciclo::factory()->create(['status' => 1, 'curso_id' => \App\Models\Academico\Curso::factory()->create()->id]);
        $otroCiclo->grupos()->attach($this->grupoDestino->id, ['orden' => 2]);
        $this->destino->update(['ciclo_id' => $otroCiclo->id]);

        $this->actingAs($this->staff)->postJson(route('asistencia-reposiciones.store'), $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('clase_destino_id');
    }

    /** @test */
    public function rechaza_una_clase_del_mismo_grupo(): void
    {
        $mismoGrupo = $this->clase($this->grupoOrigen, $this->cicloOrigen, $this->origen->fecha_clase->copy()->addDay());

        $this->actingAs($this->staff)->postJson(route('asistencia-reposiciones.store'), $this->payload(['clase_destino_id' => $mismoGrupo->id]))
            ->assertStatus(422)->assertJsonValidationErrors('clase_destino_id');
    }

    /** @test */
    public function rechaza_clases_canceladas(): void
    {
        $this->destino->update(['estado' => 'cancelada']);

        $this->actingAs($this->staff)->postJson(route('asistencia-reposiciones.store'), $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('clase_destino_id');
    }

    /** @test */
    public function rechaza_un_estudiante_sin_matricula_en_el_grupo_de_origen(): void
    {
        $this->actingAs($this->staff)->postJson(route('asistencia-reposiciones.store'), $this->payload(['estudiante_id' => User::factory()->create()->id]))
            ->assertStatus(422)->assertJsonValidationErrors('estudiante_id');
    }

    /** @test */
    public function rechaza_una_reposicion_repetida_para_la_misma_clase(): void
    {
        $this->actingAs($this->staff)->postJson(route('asistencia-reposiciones.store'), $this->payload())->assertCreated();

        $this->actingAs($this->staff)->postJson(route('asistencia-reposiciones.store'), $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('clase_origen_id');
    }

    /** @test */
    public function exige_los_campos_obligatorios(): void
    {
        $this->actingAs($this->staff)->postJson(route('asistencia-reposiciones.store'), [])
            ->assertStatus(422)->assertJsonValidationErrors(['estudiante_id', 'clase_origen_id', 'clase_destino_id']);
    }

    /** @test */
    public function sin_permiso_no_puede_registrar_ni_listar(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('asistencia-reposiciones.store'), $this->payload())->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson(route('asistencia-reposiciones.index'))->assertForbidden();
    }

    /** @test */
    public function el_alumno_registra_solo_su_propia_reposicion(): void
    {
        $this->estudiante->assignRole(Role::firstOrCreate(['name' => User::ROL_ALUMNO]));
        $this->estudiante->givePermissionTo(['aca_reposiciones', 'aca_reposicionCrear']);
        $otro = User::factory()->create();

        // Aunque envíe el id de otro, se toma el suyo
        $this->actingAs($this->estudiante)
            ->postJson(route('asistencia-reposiciones.store'), $this->payload(['estudiante_id' => $otro->id]))
            ->assertCreated()
            ->assertJsonPath('data.estudiante_id', $this->estudiante->id);
    }

    /** @test */
    public function el_alumno_solo_ve_sus_reposiciones(): void
    {
        $propia = AsistenciaReposicion::create(['estudiante_id' => $this->estudiante->id, 'clase_origen_id' => $this->origen->id, 'clase_destino_id' => $this->destino->id]);
        $ajena = AsistenciaReposicion::create(['estudiante_id' => User::factory()->create()->id, 'clase_origen_id' => $this->origen->id, 'clase_destino_id' => $this->destino->id]);

        $this->estudiante->assignRole(Role::firstOrCreate(['name' => User::ROL_ALUMNO]));
        $this->estudiante->givePermissionTo('aca_reposiciones');

        $this->actingAs($this->estudiante)->getJson(route('asistencia-reposiciones.index'))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $propia->id);
        $this->actingAs($this->estudiante)->getJson(route('asistencia-reposiciones.show', $ajena->id))->assertForbidden();
    }

    /** @test */
    public function el_personal_filtra_por_grupo_y_estudiante(): void
    {
        AsistenciaReposicion::create(['estudiante_id' => $this->estudiante->id, 'clase_origen_id' => $this->origen->id, 'clase_destino_id' => $this->destino->id]);

        $this->actingAs($this->staff)->getJson(route('asistencia-reposiciones.index', ['grupo_id' => $this->grupoDestino->id]))
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($this->staff)->getJson(route('asistencia-reposiciones.index', ['estudiante_id' => User::factory()->create()->id]))
            ->assertOk()->assertJsonPath('meta.total', 0);
    }

    /** @test */
    public function cancelar_la_reposicion_la_elimina_y_la_clase_vuelve_a_contar(): void
    {
        $reposicion = AsistenciaReposicion::create(['estudiante_id' => $this->estudiante->id, 'clase_origen_id' => $this->origen->id, 'clase_destino_id' => $this->destino->id]);
        Asistencia::create([
            'estudiante_id' => $this->estudiante->id, 'clase_programada_id' => $this->origen->id,
            'grupo_id' => $this->grupoOrigen->id, 'ciclo_id' => $this->cicloOrigen->id, 'modulo_id' => $this->grupoOrigen->modulo_id,
            'curso_id' => $this->cicloOrigen->curso_id, 'estado' => 'ausente', 'registrado_por_id' => $this->staff->id, 'fecha_registro' => now(),
        ]);
        $clave = "{$this->estudiante->id}:{$this->cicloOrigen->curso_id}";
        $this->assertSame(0, Asistencia::fallasConsecutivasPorCurso([$this->estudiante->id])->get($clave, 0));

        $this->actingAs($this->staff)->deleteJson(route('asistencia-reposiciones.destroy', $reposicion->id))->assertOk();

        $this->assertSoftDeleted('asistencia_reposiciones', ['id' => $reposicion->id]);
        $this->assertSame(1, Asistencia::fallasConsecutivasPorCurso([$this->estudiante->id])->get($clave));
    }

    /** @test */
    public function el_invitado_aparece_en_las_clases_del_dia_y_se_le_toma_asistencia_en_el_destino(): void
    {
        AsistenciaReposicion::create(['estudiante_id' => $this->estudiante->id, 'clase_origen_id' => $this->origen->id, 'clase_destino_id' => $this->destino->id]);

        $this->actingAs($this->profesorDestino)
            ->getJson(route('asistencias.clases-del-dia', ['fecha' => $this->destino->fecha_clase->toDateString()]))
            ->assertOk()
            ->assertJsonPath('data.clases.0.invitados.0.id', $this->estudiante->id);

        $this->actingAs($this->profesorDestino)->postJson(route('asistencias.masivo'), [
            'clase_programada_id' => $this->destino->id,
            'asistencias' => [['estudiante_id' => $this->estudiante->id, 'estado' => 'presente']],
        ])->assertCreated();

        $this->assertDatabaseHas('asistencias', [
            'estudiante_id' => $this->estudiante->id,
            'clase_programada_id' => $this->destino->id,
            'grupo_id' => $this->grupoDestino->id,
        ]);
    }

    /** @test */
    public function sin_reposicion_no_se_puede_tomar_asistencia_a_un_estudiante_de_otro_grupo(): void
    {
        $this->actingAs($this->profesorDestino)->postJson(route('asistencias.masivo'), [
            'clase_programada_id' => $this->destino->id,
            'asistencias' => [['estudiante_id' => $this->estudiante->id, 'estado' => 'presente']],
        ])->assertStatus(422);
    }

    /** @test */
    public function las_opciones_listan_las_clases_del_estudiante_y_sus_destinos_posibles(): void
    {
        // Otro módulo y otra semana no son destinos válidos
        $otroModulo = Grupo::factory()->create(['status' => 1, 'modulo_id' => \App\Models\Academico\Modulo::factory()->create()->id]);
        $this->cicloDestino->grupos()->attach($otroModulo->id, ['orden' => 2]);
        $this->clase($otroModulo, $this->cicloDestino, $this->destino->fecha_clase->copy());
        $this->clase($this->grupoDestino, $this->cicloDestino, $this->destino->fecha_clase->copy()->addWeek());

        $this->actingAs($this->staff)
            ->getJson(route('asistencia-reposiciones.opciones', ['estudiante_id' => $this->estudiante->id, 'fecha' => $this->origen->fecha_clase->toDateString()]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.origen.id', $this->origen->id)
            ->assertJsonPath('data.0.reposicion_id', null)
            ->assertJsonCount(1, 'data.0.destinos')
            ->assertJsonPath('data.0.destinos.0.id', $this->destino->id);
    }

    /** @test */
    public function las_opciones_indican_si_la_clase_ya_tiene_reposicion(): void
    {
        $reposicion = AsistenciaReposicion::create(['estudiante_id' => $this->estudiante->id, 'clase_origen_id' => $this->origen->id, 'clase_destino_id' => $this->destino->id]);

        $this->actingAs($this->staff)
            ->getJson(route('asistencia-reposiciones.opciones', ['estudiante_id' => $this->estudiante->id, 'fecha' => $this->origen->fecha_clase->toDateString()]))
            ->assertJsonPath('data.0.reposicion_id', $reposicion->id);
    }

    /** @test */
    public function las_opciones_exigen_el_estudiante_y_permiso(): void
    {
        $this->actingAs($this->staff)->getJson(route('asistencia-reposiciones.opciones'))->assertStatus(422)->assertJsonValidationErrors('estudiante_id');
        $this->actingAs(User::factory()->create())
            ->getJson(route('asistencia-reposiciones.opciones', ['estudiante_id' => $this->estudiante->id]))->assertForbidden();
    }

    /** @test */
    public function un_alumno_no_consulta_las_opciones_de_otro(): void
    {
        $this->estudiante->assignRole(Role::firstOrCreate(['name' => User::ROL_ALUMNO]));
        $this->estudiante->givePermissionTo('aca_reposicionCrear');

        $this->actingAs($this->estudiante)
            ->getJson(route('asistencia-reposiciones.opciones', ['estudiante_id' => User::factory()->create()->id]))->assertForbidden();
    }
}
