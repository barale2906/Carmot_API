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
use Tests\TestCase;

/**
 * Deserción automática: 2 fallas seguidas en el curso marcan la matrícula como Desertado y
 * registrar una asistencia la devuelve a Activo. Las clases canceladas o reprogramadas y la clase
 * de origen de una reposición no cuentan como falla.
 */
class DesercionAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    private User $profesor;
    private Grupo $grupo;
    private Ciclo $ciclo;
    private User $estudiante;
    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['aca_asistencias', 'aca_asistenciaCrear', 'aca_asistenciaEditar'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->profesor = User::factory()->create();
        $this->profesor->givePermissionTo(['aca_asistencias', 'aca_asistenciaCrear', 'aca_asistenciaEditar']);

        $this->ciclo = Ciclo::factory()->create([
            'status'       => 1,
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin'    => now()->addMonth()->toDateString(),
        ]);
        $this->grupo = Grupo::factory()->create(['profesor_id' => $this->profesor->id, 'status' => 1]);
        $this->ciclo->grupos()->attach($this->grupo->id, ['orden' => 1]);

        $this->estudiante = User::factory()->create();
        $this->matricula = Matricula::factory()->create([
            'ciclo_id'      => $this->ciclo->id,
            'curso_id'      => $this->ciclo->curso_id,
            'estudiante_id' => $this->estudiante->id,
            'status'        => 1,
        ]);
    }

    private function clase(int $diasAtras, array $extra = []): AsistenciaClaseProgramada
    {
        return AsistenciaClaseProgramada::factory()->create($extra + [
            'grupo_id'    => $this->grupo->id,
            'ciclo_id'    => $this->ciclo->id,
            'fecha_clase' => now()->subDays($diasAtras)->toDateString(),
            'estado'      => 'programada',
        ]);
    }

    private function registrar(AsistenciaClaseProgramada $clase, string $estado, array $extra = [])
    {
        return $this->actingAs($this->profesor)->postJson(route('asistencias.masivo'), [
            'clase_programada_id' => $clase->id,
            'asistencias'         => [['estudiante_id' => $this->estudiante->id, 'estado' => $estado] + $extra],
        ])->assertCreated();
    }

    /** @test */
    public function dos_ausencias_seguidas_marcan_la_matricula_como_desertada(): void
    {
        $this->registrar($this->clase(3), 'ausente');
        $this->assertSame(Matricula::ESTADO_ACTIVO, $this->matricula->fresh()->estado_academico);

        $this->registrar($this->clase(2), 'ausente');

        $matricula = $this->matricula->fresh();
        $this->assertSame(Matricula::ESTADO_DESERTADO, $matricula->estado_academico);
        $this->assertTrue($matricula->desercion_automatica);
        $this->assertSame(0, $matricula->status);
        $this->assertDatabaseHas('matricula_estados', [
            'matricula_id'  => $matricula->id,
            'estado_nuevo'  => Matricula::ESTADO_DESERTADO,
            'usuario_id'    => null,
        ]);
    }

    /** @test */
    public function una_asistencia_entre_las_ausencias_corta_la_racha(): void
    {
        $this->registrar($this->clase(3), 'ausente');
        $this->registrar($this->clase(2), 'presente');
        $this->registrar($this->clase(1), 'ausente');

        $this->assertSame(Matricula::ESTADO_ACTIVO, $this->matricula->fresh()->estado_academico);
    }

    /** @test */
    public function la_falla_justificada_cuenta_como_falla(): void
    {
        $this->registrar($this->clase(3), 'ausente', ['justificada' => true, 'justificacion' => 'Cita médica']);
        $this->registrar($this->clase(2), 'ausente');

        $this->assertSame(Matricula::ESTADO_DESERTADO, $this->matricula->fresh()->estado_academico);
    }

    /** @test */
    public function registrar_asistencia_reactiva_al_desertado_automatico(): void
    {
        $this->registrar($this->clase(3), 'ausente');
        $this->registrar($this->clase(2), 'ausente');
        $this->assertSame(Matricula::ESTADO_DESERTADO, $this->matricula->fresh()->estado_academico);

        // Sigue en la planilla para poder marcarlo presente
        $this->actingAs($this->profesor)
            ->getJson(route('asistencias.lista-asistencia', $this->grupo->id))
            ->assertJsonPath('data.total_estudiantes', 1)
            ->assertJsonPath('data.estudiantes.0.estado_estudiante', 'Desertado');

        $this->registrar($this->clase(1), 'presente');

        $matricula = $this->matricula->fresh();
        $this->assertSame(Matricula::ESTADO_ACTIVO, $matricula->estado_academico);
        $this->assertFalse($matricula->desercion_automatica);
        $this->assertSame(1, $matricula->status);
    }

    /** @test */
    public function una_ausencia_mas_no_reactiva_al_desertado(): void
    {
        $this->registrar($this->clase(3), 'ausente');
        $this->registrar($this->clase(2), 'ausente');
        $this->registrar($this->clase(1), 'ausente');

        $this->assertSame(Matricula::ESTADO_DESERTADO, $this->matricula->fresh()->estado_academico);
    }

    /** @test */
    public function el_desertado_manual_no_se_reactiva_al_asistir(): void
    {
        $this->matricula->update(['estado_academico' => Matricula::ESTADO_DESERTADO, 'status' => 0]);

        $this->actingAs($this->profesor)->postJson(route('asistencias.masivo'), [
            'clase_programada_id' => $this->clase(1)->id,
            'asistencias'         => [['estudiante_id' => $this->estudiante->id, 'estado' => 'presente']],
        ])->assertStatus(422);

        $this->assertSame(Matricula::ESTADO_DESERTADO, $this->matricula->fresh()->estado_academico);
    }

    /** @test */
    public function corregir_una_asistencia_a_ausente_puede_desertar(): void
    {
        $this->registrar($this->clase(3), 'ausente');
        $segunda = $this->clase(2);
        $this->registrar($segunda, 'presente');
        $this->assertSame(Matricula::ESTADO_ACTIVO, $this->matricula->fresh()->estado_academico);

        $asistencia = Asistencia::where('clase_programada_id', $segunda->id)->firstOrFail();
        $this->actingAs($this->profesor)
            ->putJson(route('asistencias.update', $asistencia->id), ['estado' => 'ausente'])
            ->assertOk();

        $this->assertSame(Matricula::ESTADO_DESERTADO, $this->matricula->fresh()->estado_academico);
    }

    /** @test */
    public function las_clases_canceladas_o_reprogramadas_no_cuentan_como_falla(): void
    {
        $cancelada = $this->clase(3);
        $this->registrar($cancelada, 'ausente');
        $cancelada->update(['estado' => 'cancelada']);
        $this->registrar($this->clase(2), 'ausente');

        $fallas = Asistencia::fallasConsecutivasPorCurso([$this->estudiante->id]);
        $this->assertSame(1, $fallas->get("{$this->estudiante->id}:{$this->ciclo->curso_id}"));
    }

    /** @test */
    public function la_clase_de_origen_de_una_reposicion_no_cuenta_como_falla(): void
    {
        $primera = $this->clase(3);
        $segunda = $this->clase(2);
        $this->registrar($primera, 'ausente');

        $destino = AsistenciaClaseProgramada::factory()->create([
            'grupo_id'    => Grupo::factory()->create(['status' => 1])->id,
            'ciclo_id'    => $this->ciclo->id,
            'fecha_clase' => now()->subDays(2)->toDateString(),
            'estado'      => 'programada',
        ]);
        AsistenciaReposicion::create([
            'estudiante_id'    => $this->estudiante->id,
            'clase_origen_id'  => $segunda->id,
            'clase_destino_id' => $destino->id,
        ]);

        $this->registrar($segunda, 'ausente');

        $this->assertSame(Matricula::ESTADO_ACTIVO, $this->matricula->fresh()->estado_academico);
        $this->assertSame(1, Asistencia::fallasConsecutivasPorCurso([$this->estudiante->id])->get("{$this->estudiante->id}:{$this->ciclo->curso_id}"));
    }
}
