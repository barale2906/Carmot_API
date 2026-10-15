<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Cambio de estado académico de la matrícula y su historial.
 */
class MatriculaEstadoAcademicoTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinador;
    private Matricula $matricula;
    private Ciclo $ciclo;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['aca_matriculas', 'aca_matriculaEstado'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->coordinador = User::factory()->create();
        $this->coordinador->givePermissionTo(['aca_matriculas', 'aca_matriculaEstado']);

        $curso = Curso::factory()->create();
        $this->ciclo = Ciclo::factory()->create(['curso_id' => $curso->id, 'inscritos' => 0]);
        // Se crea por el modelo para que los eventos sumen el cupo del ciclo.
        $this->matricula = Matricula::factory()->create([
            'curso_id'        => $curso->id,
            'ciclo_id'        => $this->ciclo->id,
            'status'          => 1,
            'fecha_matricula' => now()->subMonths(2)->toDateString(),
        ]);
        $this->coordinador->sedes()->attach($this->matricula->sede_id);
    }

    private function cambiar(int $estado, array $extra = [])
    {
        return $this->actingAs($this->coordinador)->postJson(
            route('matriculas.estado-academico', $this->matricula),
            array_merge(['estado_academico' => $estado, 'fecha' => now()->toDateString(), 'motivo' => 'Motivo de prueba'], $extra)
        );
    }

    /** @test */
    public function una_matricula_nueva_queda_activa_academicamente(): void
    {
        $this->actingAs($this->coordinador)
            ->getJson(route('matriculas.show', $this->matricula))
            ->assertOk()
            ->assertJsonPath('data.estado_academico', Matricula::ESTADO_ACTIVO)
            ->assertJsonPath('data.estado_academico_texto', 'Activo');
    }

    /** @test */
    public function aplazar_conserva_la_matricula_vigente_y_registra_historial(): void
    {
        $this->cambiar(Matricula::ESTADO_APLAZADO)
            ->assertOk()
            ->assertJsonPath('data.estado_academico_texto', 'Aplazado')
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas('matricula_estados', [
            'matricula_id'    => $this->matricula->id,
            'estado_anterior' => Matricula::ESTADO_ACTIVO,
            'estado_nuevo'    => Matricula::ESTADO_APLAZADO,
            'usuario_id'      => $this->coordinador->id,
        ]);
        $this->assertSame(1, $this->ciclo->fresh()->inscritos);
    }

    /** @test */
    public function desertar_inactiva_la_matricula_y_libera_el_cupo(): void
    {
        $this->cambiar(Matricula::ESTADO_DESERTADO, ['motivo' => 'Dejó de asistir y no responde'])
            ->assertOk()
            ->assertJsonPath('data.status', 0)
            ->assertJsonPath('data.estado_academico_texto', 'Desertado');

        $this->assertSame(0, $this->ciclo->fresh()->inscritos);
    }

    /** @test */
    public function reactivar_una_matricula_desertada_la_vuelve_vigente(): void
    {
        $this->cambiar(Matricula::ESTADO_DESERTADO);
        $this->matricula->refresh();

        $this->cambiar(Matricula::ESTADO_ACTIVO, ['motivo' => 'Regresó a clases'])
            ->assertOk()
            ->assertJsonPath('data.status', 1);

        $this->assertSame(1, $this->ciclo->fresh()->inscritos);

        $this->actingAs($this->coordinador)
            ->getJson(route('matriculas.historial-estados', $this->matricula))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.estado_nuevo_texto', 'Activo')
            ->assertJsonPath('data.0.usuario.id', $this->coordinador->id);
    }

    /** @test */
    public function no_reactiva_si_el_estudiante_ya_tiene_otra_matricula_vigente_en_el_curso(): void
    {
        $this->cambiar(Matricula::ESTADO_RETIRADO);
        $this->matricula->refresh();

        Matricula::factory()->create([
            'estudiante_id' => $this->matricula->estudiante_id,
            'curso_id'      => $this->matricula->curso_id,
            'status'        => 1,
        ]);

        $this->cambiar(Matricula::ESTADO_ACTIVO)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado_academico');
    }

    /** @test */
    public function valida_estado_repetido_fecha_y_motivo(): void
    {
        $this->cambiar(Matricula::ESTADO_ACTIVO)->assertUnprocessable()->assertJsonValidationErrors('estado_academico');

        $this->cambiar(Matricula::ESTADO_GRADUADO, ['fecha' => now()->addDay()->toDateString(), 'motivo' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha', 'motivo']);

        $this->cambiar(Matricula::ESTADO_GRADUADO, ['fecha' => now()->subYear()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha');
    }

    /** @test */
    public function no_cambia_el_estado_de_una_matricula_anulada(): void
    {
        $this->matricula->update(['status' => 2]);

        $this->cambiar(Matricula::ESTADO_GRADUADO)->assertUnprocessable()->assertJsonValidationErrors('estado_academico');
    }

    /** @test */
    public function filtra_el_listado_por_estado_academico(): void
    {
        $this->cambiar(Matricula::ESTADO_APLAZADO);
        Matricula::factory()->create(['status' => 1]);

        $this->actingAs($this->coordinador)
            ->getJson(route('matriculas.index', ['estado_academico' => Matricula::ESTADO_APLAZADO]))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->matricula->id);
    }

    /** @test */
    public function sin_permiso_no_cambia_el_estado(): void
    {
        $soloLectura = User::factory()->create();
        $soloLectura->givePermissionTo('aca_matriculas');

        $this->actingAs($soloLectura)
            ->postJson(route('matriculas.estado-academico', $this->matricula), [])
            ->assertForbidden();
    }

    /** @test */
    public function las_estadisticas_separan_activas_de_no_eliminadas(): void
    {
        // N22 (prueba integral 2): totales.activas contaba todas las no eliminadas
        Matricula::factory()->create(['status' => 2]);
        Matricula::factory()->create(['status' => 1])->delete();

        $data = $this->actingAs($this->coordinador)->getJson(route('matriculas.statistics'))->assertOk()->json('data');

        $this->assertArrayNotHasKey('activas', $data['totales']);
        $this->assertSame(Matricula::count(), $data['totales']['total']);
        $this->assertSame(1, $data['totales']['eliminadas']);
        $this->assertSame(1, $data['por_status']['anuladas']);
        $this->assertSame(Matricula::where('status', 1)->count(), $data['por_status']['activas']);
    }
}
