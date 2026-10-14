<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Toma de asistencia por clase: grupos disponibles, lista del grupo y registro masivo.
 */
class AsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $profesor;
    private Grupo $grupo;
    private Ciclo $ciclo;
    private AsistenciaClaseProgramada $clase;
    private User $estudianteA;
    private User $estudianteB;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['aca_asistencias', 'aca_asistenciaCrear'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->profesor = User::factory()->create();
        $this->profesor->givePermissionTo(['aca_asistencias', 'aca_asistenciaCrear']);

        $this->ciclo = Ciclo::factory()->create([
            'status'       => 1,
            'fecha_inicio' => now()->subWeek()->toDateString(),
            'fecha_fin'    => now()->addMonth()->toDateString(),
        ]);
        $this->grupo = Grupo::factory()->create(['profesor_id' => $this->profesor->id, 'status' => 1]);
        $this->ciclo->grupos()->attach($this->grupo->id, ['orden' => 1]);

        $this->estudianteA = User::factory()->create();
        $this->estudianteB = User::factory()->create();
        foreach ([$this->estudianteA, $this->estudianteB] as $estudiante) {
            Matricula::factory()->create([
                'ciclo_id'      => $this->ciclo->id,
                'curso_id'      => $this->ciclo->curso_id,
                'estudiante_id' => $estudiante->id,
                'status'        => 1,
            ]);
        }

        $this->clase = AsistenciaClaseProgramada::factory()->create([
            'grupo_id'    => $this->grupo->id,
            'ciclo_id'    => $this->ciclo->id,
            'fecha_clase' => now()->toDateString(),
            'estado'      => 'programada',
        ]);
    }

    private function registrar(array $estados)
    {
        return $this->actingAs($this->profesor)->postJson(route('asistencias.masivo'), [
            'clase_programada_id' => $this->clase->id,
            'asistencias'         => collect($estados)
                ->map(fn ($estado, $id) => ['estudiante_id' => $id, 'estado' => $estado])
                ->values()
                ->all(),
        ]);
    }

    /** @test */
    public function lista_asistencia_trae_estudiantes_y_clases_del_grupo(): void
    {
        $this->actingAs($this->profesor)
            ->getJson(route('asistencias.lista-asistencia', $this->grupo->id))
            ->assertOk()
            ->assertJsonPath('data.total_estudiantes', 2)
            ->assertJsonPath('data.total_clases', 1);
    }

    /** @test */
    public function lista_asistencia_excluye_estudiantes_aplazados(): void
    {
        Matricula::where('estudiante_id', $this->estudianteB->id)->update(['estado_academico' => Matricula::ESTADO_APLAZADO]);

        $this->actingAs($this->profesor)
            ->getJson(route('asistencias.lista-asistencia', $this->grupo->id))
            ->assertJsonPath('data.total_estudiantes', 1)
            ->assertJsonPath('data.estudiantes.0.id', $this->estudianteA->id);
    }

    /** @test */
    public function registro_masivo_crea_asistencias_y_marca_la_clase_como_dictada(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'ausente'])
            ->assertCreated()
            ->assertJsonPath('meta.creadas', 2)
            ->assertJsonPath('meta.actualizadas', 0);

        $this->assertDatabaseHas('asistencias', [
            'estudiante_id' => $this->estudianteB->id,
            'clase_programada_id' => $this->clase->id,
            'estado' => 'ausente',
            'modulo_id' => $this->grupo->modulo_id,
            'curso_id' => $this->ciclo->curso_id,
            'registrado_por_id' => $this->profesor->id,
        ]);
        $this->assertSame('dictada', $this->clase->fresh()->estado);
    }

    /** @test */
    public function volver_a_guardar_la_lista_corrige_sin_duplicar(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'ausente']);

        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'justificado'])
            ->assertCreated()
            ->assertJsonPath('meta.creadas', 0)
            ->assertJsonPath('meta.actualizadas', 2);

        $this->assertSame(2, Asistencia::where('clase_programada_id', $this->clase->id)->count());
        $this->assertDatabaseHas('asistencias', ['estudiante_id' => $this->estudianteB->id, 'estado' => 'justificado']);
    }

    /** @test */
    public function una_asistencia_eliminada_se_restaura_al_volver_a_registrarla(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente']);
        Asistencia::where('estudiante_id', $this->estudianteA->id)->first()->delete();

        $this->registrar([$this->estudianteA->id => 'tardanza'])
            ->assertCreated()
            ->assertJsonPath('meta.actualizadas', 1);

        $this->assertDatabaseHas('asistencias', ['estudiante_id' => $this->estudianteA->id, 'estado' => 'tardanza', 'deleted_at' => null]);
    }

    /** @test */
    public function rechaza_clase_cancelada_estudiante_repetido_y_estado_invalido(): void
    {
        $this->clase->update(['estado' => 'cancelada']);

        $this->actingAs($this->profesor)->postJson(route('asistencias.masivo'), [
            'clase_programada_id' => $this->clase->id,
            'asistencias' => [
                ['estudiante_id' => $this->estudianteA->id, 'estado' => 'presente'],
                ['estudiante_id' => $this->estudianteA->id, 'estado' => 'volando'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clase_programada_id', 'asistencias.0.estudiante_id', 'asistencias.1.estado']);
    }

    /** @test */
    public function no_se_toma_asistencia_de_una_clase_futura(): void
    {
        $this->clase->update(['fecha_clase' => now()->addDay()->toDateString()]);

        $this->registrar([$this->estudianteA->id => 'presente'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('clase_programada_id');
    }

    /** @test */
    public function reporte_de_grupo_calcula_porcentaje_por_horas(): void
    {
        $this->registrar([$this->estudianteA->id => 'presente', $this->estudianteB->id => 'ausente']);

        $estudiantes = collect($this->actingAs($this->profesor)
            ->getJson(route('asistencias.reporte-grupo', $this->grupo->id))
            ->assertOk()
            ->json('data.estudiantes'))->keyBy('estudiante.id');

        $this->assertEquals(100, $estudiantes[$this->estudianteA->id]['porcentaje_asistencia']);
        $this->assertEquals(0, $estudiantes[$this->estudianteB->id]['porcentaje_asistencia']);
    }

    /** @test */
    public function sin_permiso_de_crear_no_registra(): void
    {
        $soloLectura = User::factory()->create();
        $soloLectura->givePermissionTo('aca_asistencias');

        $this->actingAs($soloLectura)->postJson(route('asistencias.masivo'), [])->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson(route('asistencias.lista-asistencia', $this->grupo->id))->assertForbidden();
    }
}
