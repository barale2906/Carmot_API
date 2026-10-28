<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use App\Models\Academico\RecuperacionNota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Feature\Api\Academico\Concerns\CreaEscenarioNotas;
use Tests\TestCase;

/**
 * Consolidado de notas: resultados por módulo filtrables por estudiante, grupo, ciclo y
 * profesor, con resumen y respetando lo que cada usuario puede ver.
 */
class ConsolidadoNotasTest extends TestCase
{
    use RefreshDatabase, CreaEscenarioNotas;

    private User $reprobado;

    protected function setUp(): void
    {
        parent::setUp();

        config(['academico.nota_aprobatoria' => 3.0]);
        $this->crearEscenarioNotas();

        $this->registrarNotaFinal(4.0);
        $this->reprobado = $this->matricular();
        $this->registrarNotaFinal(2.0, $this->reprobado);
    }

    /** @test */
    public function por_ciclo_devuelve_cada_estudiante_con_su_resultado_y_resumen(): void
    {
        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.consolidado', ['ciclo_id' => $this->ciclo->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.resumen.aprobados', 1)
            ->assertJsonPath('meta.resumen.reprobados', 1)
            ->assertJsonPath('meta.resumen.por_recuperar', 1)
            ->assertJsonPath('meta.resumen.promedio', 3)
            ->assertJsonPath('meta.sin_esquema', []);
    }

    /** @test */
    public function filtra_por_estudiante_grupo_profesor_y_estado(): void
    {
        $consultar = fn (array $filtros) => $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.consolidado', $filtros))
            ->assertOk();

        $consultar(['estudiante_id' => $this->reprobado->id])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estudiante.id', $this->reprobado->id)
            ->assertJsonPath('data.0.estado', 'reprobado')
            ->assertJsonPath('data.0.puede_recuperar', true);

        $consultar(['grupo_id' => $this->grupo->id])->assertJsonCount(2, 'data');
        $consultar(['profesor_id' => $this->profesor->id])->assertJsonCount(2, 'data');
        $consultar(['profesor_id' => User::factory()->create()->id])->assertJsonCount(0, 'data');
        $consultar(['ciclo_id' => $this->ciclo->id, 'estado' => 'aprobado'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estudiante.id', $this->estudiante->id);
        $consultar(['ciclo_id' => $this->ciclo->id, 'por_recuperar' => 1])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estudiante.id', $this->reprobado->id);
    }

    /** @test */
    public function la_recuperacion_se_refleja_en_el_consolidado(): void
    {
        RecuperacionNota::create([
            'estudiante_id'     => $this->reprobado->id,
            'grupo_id'          => $this->grupo->id,
            'modulo_id'         => $this->grupo->modulo_id,
            'nota'              => 3.4,
            'fecha_registro'    => now()->toDateString(),
            'registrado_por_id' => $this->profesor->id,
        ]);

        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.consolidado', ['estudiante_id' => $this->reprobado->id]))
            ->assertOk()
            ->assertJsonPath('data.0.estado', 'aprobado')
            ->assertJsonPath('data.0.nota_final', 2)
            ->assertJsonPath('data.0.nota_definitiva', 3.4)
            ->assertJsonPath('data.0.recuperacion.nota', 3.4);
    }

    /** @test */
    public function una_matricula_cerrada_se_consulta_pero_no_se_ofrece_recuperar(): void
    {
        \App\Models\Academico\Matricula::where('estudiante_id', $this->reprobado->id)
            ->update(['status' => 0, 'estado_academico' => \App\Models\Academico\Matricula::ESTADO_REPROBADO]);

        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.consolidado', ['estudiante_id' => $this->reprobado->id]))
            ->assertOk()
            ->assertJsonPath('data.0.estado', 'reprobado')
            ->assertJsonPath('data.0.puede_recuperar', false)
            ->assertJsonPath('meta.resumen.por_recuperar', 0);
    }

    /** @test */
    public function informa_los_grupos_sin_esquema(): void
    {
        $sinEsquema = Grupo::factory()->create(['sede_id' => $this->sede->id, 'status' => 1]);
        $this->ciclo->grupos()->attach($sinEsquema->id, ['orden' => 2]);

        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.consolidado', ['ciclo_id' => $this->ciclo->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.sin_esquema.0.grupo.id', $sinEsquema->id);
    }

    /** @test */
    public function exige_al_menos_un_filtro_de_alcance(): void
    {
        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.consolidado'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ciclo_id');

        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.consolidado', ['ciclo_id' => $this->ciclo->id, 'estado' => 'otro']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }

    /** @test */
    public function el_personal_solo_ve_los_grupos_de_sus_sedes(): void
    {
        $otraSede = User::factory()->create();
        $otraSede->givePermissionTo('aca_notas');

        $this->actingAs($otraSede)
            ->getJson(route('notas-estudiantes.consolidado', ['ciclo_id' => $this->ciclo->id]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function el_profesor_solo_ve_sus_grupos_y_el_alumno_solo_lo_suyo(): void
    {
        $otroProfesor = User::factory()->create();
        $otroProfesor->assignRole(Role::create(['name' => User::ROL_PROFESOR, 'guard_name' => 'web']));
        $otroProfesor->givePermissionTo('aca_notas');

        $this->actingAs($otroProfesor)
            ->getJson(route('notas-estudiantes.consolidado', ['profesor_id' => $this->profesor->id]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->reprobado->assignRole(Role::create(['name' => User::ROL_ALUMNO, 'guard_name' => 'web']));
        $this->reprobado->givePermissionTo('aca_notas');

        $this->actingAs($this->reprobado)
            ->getJson(route('notas-estudiantes.consolidado', ['ciclo_id' => $this->ciclo->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estudiante.id', $this->reprobado->id);
    }

    /** @test */
    public function el_consolidado_y_los_filtros_exigen_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('notas-estudiantes.consolidado', ['ciclo_id' => $this->ciclo->id]))
            ->assertForbidden();

        $this->actingAs($sinPermiso)
            ->getJson(route('notas-estudiantes.filters'))
            ->assertForbidden();
    }

    /** @test */
    public function los_filtros_traen_ciclos_grupos_y_profesores_visibles(): void
    {
        Ciclo::factory()->create(); // ciclo de otra sede, sin grupos visibles
        $this->cerrarCiclo();

        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.filters'))
            ->assertOk()
            ->assertJsonCount(1, 'data.ciclos')
            ->assertJsonPath('data.ciclos.0.id', $this->ciclo->id)
            ->assertJsonPath('data.ciclos.0.notas_cerradas', true)
            ->assertJsonPath('data.grupos.0.id', $this->grupo->id)
            ->assertJsonPath('data.profesores.0.id', $this->profesor->id)
            ->assertJsonCount(3, 'data.estados');
    }

    /** @test */
    public function el_listado_de_notas_filtra_por_ciclo_y_profesor(): void
    {
        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.index', ['ciclo_id' => $this->ciclo->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->coordinador)
            ->getJson(route('notas-estudiantes.index', ['profesor_id' => User::factory()->create()->id]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
