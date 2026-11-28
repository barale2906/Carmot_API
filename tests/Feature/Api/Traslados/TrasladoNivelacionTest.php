<?php

namespace Tests\Feature\Api\Traslados;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\Academico\Tema;
use App\Models\Academico\Topico;
use App\Models\Traslados\MatriculaTraslado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\Traslados\Concerns\CreaEscenarioTraslado;
use Tests\TestCase;

/**
 * Nivelación del traslado: cruza la planeación de temas del ciclo de origen con la del ciclo de
 * destino a la fecha del traslado. Es solo informativa: nunca impide trasladar.
 *
 * Currículo: un módulo de 4 h con un tópico de dos temas de 2 h. El grupo dicta los lunes, 2 h por
 * sesión, así que cada ciclo dicta el Tema 1 en su primera sesión y el Tema 2 en la segunda.
 */
class TrasladoNivelacionTest extends TestCase
{
    use CreaEscenarioTraslado, RefreshDatabase;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEscenarioTraslado();
        $this->crearCurriculo();
    }

    private function crearCurriculo(): void
    {
        $modulo = Modulo::create(['nombre' => 'Módulo 1', 'duracion' => 4, 'status' => 1]);
        $topico = Topico::create(['nombre' => 'Tópico 1', 'descripcion' => 'Tópico', 'duracion' => 4, 'status' => 1]);
        DB::table('topico_modulo')->insert(['topico_id' => $topico->id, 'modulo_id' => $modulo->id, 'created_at' => now(), 'updated_at' => now()]);

        foreach (['Tema 1', 'Tema 2'] as $nombre) {
            $tema = Tema::create(['nombre' => $nombre, 'descripcion' => $nombre, 'duracion' => 2, 'status' => 1]);
            DB::table('tema_topico')->insert(['tema_id' => $tema->id, 'topico_id' => $topico->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->grupo = Grupo::factory()->create(['sede_id' => $this->sedeA->id, 'modulo_id' => $modulo->id, 'status' => 1]);

        $areaId = DB::table('areas')->insertGetId(['nombre' => 'Área Test', 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('horarios')->insert([
            'sede_id' => $this->sedeA->id, 'area_id' => $areaId, 'grupo_id' => $this->grupo->id,
            'grupo_nombre' => $this->grupo->nombre, 'tipo' => false, 'periodo' => true, 'dia' => 'lunes',
            'hora' => '08:00:00', 'duracion_horas' => 2, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * El grupo dicta el módulo en el ciclo entre las dos fechas (lunes de 2 h).
     */
    private function programar(Ciclo $ciclo, string $inicio, string $fin): void
    {
        $ciclo->grupos()->attach($this->grupo->id, ['orden' => 1, 'fecha_inicio_grupo' => $inicio, 'fecha_fin_grupo' => $fin, 'horas_planeadas' => 4]);
    }

    private function mismoCurso(array $extra = []): array
    {
        return array_merge(['sede_id' => $this->sedeA->id, 'ciclo_id' => $this->cicloA2->id, 'lp_precio_producto_id' => null], $extra);
    }

    // ─── Resultado del cruce ──────────────────────────────────────────────────

    /** @test */
    public function coincide_cuando_el_estudiante_quedo_en_el_mismo_tema_del_ciclo_de_destino(): void
    {
        $this->programar($this->cicloA1, '2027-02-01', '2027-02-08');
        $this->programar($this->cicloA2, '2027-02-01', '2027-02-08');

        $this->simular($this->mismoCurso(['fecha' => '2027-02-05']))
            ->assertOk()
            ->assertJsonPath('data.nivelacion.aplica', true)
            ->assertJsonPath('data.nivelacion.coincide', true)
            ->assertJsonPath('data.nivelacion.requiere_nivelacion', false)
            ->assertJsonPath('data.nivelacion.tema_actual_origen.tema', 'Tema 2')
            ->assertJsonPath('data.nivelacion.tema_actual_destino.tema', 'Tema 2')
            ->assertJsonPath('data.nivelacion.faltante_por_dictar', [])
            ->assertJsonPath('data.nivelacion.repite', []);
    }

    /** @test */
    public function informa_lo_que_falta_por_dictar_cuando_el_destino_va_adelantado(): void
    {
        $this->programar($this->cicloA1, '2027-03-01', '2027-03-08');
        $this->programar($this->cicloA2, '2027-02-01', '2027-02-08');

        $nivelacion = $this->simular($this->mismoCurso(['fecha' => '2027-02-20']))
            ->assertOk()
            ->assertJsonPath('data.nivelacion.coincide', false)
            ->assertJsonPath('data.nivelacion.requiere_nivelacion', true)
            ->assertJsonPath('data.nivelacion.horas_faltantes', 4)
            ->assertJsonPath('data.nivelacion.faltante_por_dictar.0.modulo', 'Módulo 1')
            ->assertJsonPath('data.nivelacion.faltante_por_dictar.0.horas', 4)
            ->assertJsonCount(2, 'data.nivelacion.faltante_por_dictar.0.temas')
            ->assertJsonPath('data.nivelacion.faltante_por_dictar.0.temas.0.tema', 'Tema 1')
            ->json('data.nivelacion');

        $this->assertStringContainsString('faltan por dictar 2 tema(s)', $nivelacion['mensaje']);
        $this->assertStringContainsString('defina la nivelación', $nivelacion['mensaje']);
    }

    /** @test */
    public function informa_lo_que_repetira_cuando_el_destino_va_atrasado(): void
    {
        $this->programar($this->cicloA1, '2027-02-01', '2027-02-08');
        $this->programar($this->cicloA2, '2027-02-15', '2027-02-22');

        $this->simular($this->mismoCurso(['fecha' => '2027-02-20']))
            ->assertOk()
            ->assertJsonPath('data.nivelacion.coincide', false)
            ->assertJsonPath('data.nivelacion.requiere_nivelacion', false)
            ->assertJsonPath('data.nivelacion.faltante_por_dictar', [])
            ->assertJsonPath('data.nivelacion.horas_repite', 2)
            ->assertJsonPath('data.nivelacion.repite.0.temas.0.tema', 'Tema 2');
    }

    /** @test */
    public function sin_planeacion_en_los_ciclos_no_hay_nada_que_nivelar(): void
    {
        $this->simular($this->mismoCurso())
            ->assertOk()
            ->assertJsonPath('data.nivelacion.coincide', true)
            ->assertJsonPath('data.nivelacion.tema_actual_origen', null)
            ->assertJsonPath('data.nivelacion.requiere_nivelacion', false);
    }

    /** @test */
    public function aplica_tambien_al_cambio_de_sede_con_el_mismo_curso(): void
    {
        $this->programar($this->cicloA1, '2027-03-01', '2027-03-08');
        $this->programar($this->cicloB1, '2027-02-01', '2027-02-08');

        $this->simular(['fecha' => '2027-02-20'])
            ->assertOk()
            ->assertJsonPath('data.tipo', 'sede')
            ->assertJsonPath('data.nivelacion.requiere_nivelacion', true);
    }

    /** @test */
    public function no_aplica_al_cambiar_de_curso(): void
    {
        $this->simular([
            'sede_id' => $this->sedeA->id, 'curso_id' => $this->cursoY->id, 'ciclo_id' => $this->cicloAY->id, 'lp_precio_producto_id' => $this->lpAY->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.nivelacion.aplica', false)
            ->assertJsonPath('data.nivelacion.requiere_nivelacion', false);
    }

    // ─── Es informativa y deja seguimiento ────────────────────────────────────

    /** @test */
    public function la_nivelacion_pendiente_no_impide_el_traslado_y_queda_registrada(): void
    {
        $this->programar($this->cicloA1, '2027-03-01', '2027-03-08');
        $this->programar($this->cicloA2, '2027-02-01', '2027-02-08');

        $this->trasladar($this->mismoCurso(['fecha' => '2027-02-20']))
            ->assertCreated()
            ->assertJsonPath('data.nivelacion_pendiente', true)
            ->assertJsonPath('data.nivelacion.horas_faltantes', 4);

        $this->assertSame($this->cicloA2->id, $this->matricula->fresh()->ciclo_id);
        $this->assertTrue(MatriculaTraslado::first()->nivelacion_pendiente);
    }

    /** @test */
    public function el_listado_filtra_los_traslados_con_nivelacion_pendiente(): void
    {
        $this->programar($this->cicloA1, '2027-03-01', '2027-03-08');
        $this->programar($this->cicloA2, '2027-02-01', '2027-02-08');
        $this->trasladar($this->mismoCurso(['fecha' => '2027-02-20']))->assertCreated();

        $total = fn (string $valor) => $this->actingAs($this->coordinador)
            ->getJson(route('tra-traslados.index', ['nivelacion_pendiente' => $valor]))->assertOk()->json('meta.total');

        $this->assertSame(1, $total('1'));
        $this->assertSame(0, $total('0'));
    }

    // ─── Resolver la nivelación ───────────────────────────────────────────────

    private function trasladoConNivelacionPendiente(): MatriculaTraslado
    {
        $this->programar($this->cicloA1, '2027-03-01', '2027-03-08');
        $this->programar($this->cicloA2, '2027-02-01', '2027-02-08');
        $this->trasladar($this->mismoCurso(['fecha' => '2027-02-20']))->assertCreated();

        return MatriculaTraslado::first();
    }

    /** @test */
    public function se_marca_la_nivelacion_como_resuelta_con_lo_acordado(): void
    {
        $traslado = $this->trasladoConNivelacionPendiente();

        $this->actingAs($this->coordinador)
            ->postJson(route('tra-traslados.nivelacion-resuelta', $traslado), ['observaciones' => 'Clases de refuerzo los sábados'])
            ->assertOk()
            ->assertJsonPath('data.nivelacion_pendiente', false)
            ->assertJsonPath('data.nivelacion_observaciones', 'Clases de refuerzo los sábados')
            ->assertJsonPath('data.nivelacion_resuelta_por.id', $this->coordinador->id);

        $this->assertNotNull($traslado->fresh()->nivelacion_resuelta_at);
    }

    /** @test */
    public function no_se_resuelve_una_nivelacion_que_no_esta_pendiente(): void
    {
        $traslado = $this->trasladoConNivelacionPendiente();
        $ruta = route('tra-traslados.nivelacion-resuelta', $traslado);

        $this->actingAs($this->coordinador)->postJson($ruta, ['observaciones' => 'Refuerzo acordado'])->assertOk();
        $this->actingAs($this->coordinador)->postJson($ruta, ['observaciones' => 'Refuerzo acordado'])->assertUnprocessable();
    }

    /** @test */
    public function resolver_la_nivelacion_exige_observaciones_y_permiso(): void
    {
        $traslado = $this->trasladoConNivelacionPendiente();
        $ruta = route('tra-traslados.nivelacion-resuelta', $traslado);

        $this->actingAs($this->coordinador)->postJson($ruta, [])->assertUnprocessable()->assertJsonValidationErrors('observaciones');

        $lector = User::factory()->create();
        $lector->givePermissionTo('tra_traslados');
        $lector->sedes()->attach($this->sedeA->id);
        $this->actingAs($lector)->postJson($ruta, ['observaciones' => 'Refuerzo acordado'])->assertForbidden();

        $this->assertTrue($traslado->fresh()->nivelacion_pendiente);
    }

    /** @test */
    public function solo_resuelve_la_nivelacion_quien_tiene_asignada_una_sede_del_traslado(): void
    {
        $traslado = $this->trasladoConNivelacionPendiente();

        $ajeno = User::factory()->create();
        $ajeno->givePermissionTo('tra_trasladoNivelacion');

        $this->actingAs($ajeno)
            ->postJson(route('tra-traslados.nivelacion-resuelta', $traslado), ['observaciones' => 'Refuerzo acordado'])
            ->assertForbidden();
    }
}
