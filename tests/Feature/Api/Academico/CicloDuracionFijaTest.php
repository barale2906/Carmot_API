<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciclos con fecha fin fija (fecha_fin_automatica = false): el contenido se
 * ajusta con un factor para terminar en la fecha fijada.
 *
 * Escenario (2027, sesiones de 2 h):
 *  - grupoA: lunes, módulo de 10 h → 5 sesiones al 100 %.
 *  - grupoB: martes, módulo de 10 h → 5 sesiones al 100 %.
 *  - Inicio lunes 2027-02-01. Al 100 %: A = 02-01..03-01, B = 03-02..03-30.
 *  - Con n sesiones por módulo: A = n lunes desde 02-01, B = n martes desde el día siguiente.
 *      n = 4 → B termina 03-16 (factor ≈ 0,8)
 *      n = 3 → B termina 03-02 (factor ≈ 0,6)
 */
class CicloDuracionFijaTest extends TestCase
{
    use RefreshDatabase;

    private const INICIO = '2027-02-01';

    private User $usuario;
    private Sede $sede;
    private Curso $curso;
    private Modulo $moduloA;
    private Grupo $grupoA;
    private Grupo $grupoB;
    private ?int $areaId = null;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = ['aca_ciclos', 'aca_cicloCrear', 'aca_cicloEditar', 'co_diasNoLaborables', 'co_diaNoLaborableCrear'];
        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo($permisos);

        $this->sede  = Sede::factory()->create();
        $this->curso = Curso::factory()->create();

        $this->moduloA = Modulo::create(['nombre' => 'Módulo A', 'duracion' => 10, 'status' => 1]);
        $moduloB       = Modulo::create(['nombre' => 'Módulo B', 'duracion' => 10, 'status' => 1]);

        DB::table('modulo_curso')->insert([
            ['modulo_id' => $this->moduloA->id, 'curso_id' => $this->curso->id, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['modulo_id' => $moduloB->id, 'curso_id' => $this->curso->id, 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->grupoA = Grupo::factory()->create(['sede_id' => $this->sede->id, 'modulo_id' => $this->moduloA->id, 'status' => 1, 'nombre' => 'Grupo A']);
        $this->grupoB = Grupo::factory()->create(['sede_id' => $this->sede->id, 'modulo_id' => $moduloB->id, 'status' => 1]);

        $this->crearHorario($this->grupoA, 'lunes');
        $this->crearHorario($this->grupoB, 'martes');
    }

    // =========================================================================
    // Cálculo en modo fijo
    // =========================================================================

    /** @test */
    public function fecha_fin_holgada_dicta_el_100_por_ciento_y_conserva_la_fecha_fijada(): void
    {
        $response = $this->crearCicloFijo('2027-06-30');

        $response->assertJsonPath('data.fecha_fin', '2027-06-30')
            ->assertJsonPath('data.fecha_fin_teorica', '2027-03-30')
            ->assertJsonPath('data.ajuste.modo', 'fijo')
            ->assertJsonPath('data.ajuste.factor', 1)
            ->assertJsonPath('data.ajuste.advertencia', null);

        $ciclo = Ciclo::latest('id')->first();
        $this->assertPivot($ciclo, $this->grupoB, '2027-03-02', '2027-03-30', 10.0);
    }

    /** @test */
    public function fecha_fin_ajustada_reduce_las_horas_y_un_80_por_ciento_exacto_no_advierte(): void
    {
        $response = $this->crearCicloFijo('2027-03-16');

        $response->assertJsonPath('data.ajuste.factor', 0.8)
            ->assertJsonPath('data.ajuste.porcentaje_horas', 80)
            ->assertJsonPath('data.fecha_fin', '2027-03-16')
            ->assertJsonPath('data.fecha_fin_teorica', '2027-03-30')
            ->assertJsonPath('data.ajuste.advertencia', null);

        $ciclo = Ciclo::latest('id')->first();
        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-02-22', 8.0);
        $this->assertPivot($ciclo, $this->grupoB, '2027-02-23', '2027-03-16', 8.0);
    }

    /** @test */
    public function factor_bajo_el_umbral_genera_advertencia_sin_bloquear(): void
    {
        $response = $this->crearCicloFijo('2027-03-02');

        $advertencia = $response->json('data.ajuste.advertencia');
        $this->assertStringContainsString('60 %', $advertencia);
        $this->assertStringContainsString('12 de 20 h', $advertencia);
        $this->assertStringContainsString('3,3 h/semana', $advertencia);
        $this->assertStringContainsString('30/03/2027', $advertencia);

        $ciclo = Ciclo::latest('id')->first();
        $this->assertPivot($ciclo, $this->grupoB, '2027-02-16', '2027-03-02', 6.0);
    }

    /** @test */
    public function fecha_fin_que_no_alcanza_programa_el_100_por_ciento_y_advierte(): void
    {
        // Inicio martes 02-feb: el primer lunes (08-feb) ya supera la fecha fijada
        $response = $this->crearCicloFijo('2027-02-05', '2027-02-02');

        $response->assertJsonPath('data.fecha_fin', '2027-02-05')
            ->assertJsonPath('data.ajuste.factor', null);
        $this->assertStringContainsString('no alcanza', $response->json('data.ajuste.advertencia'));

        $ciclo = Ciclo::latest('id')->first();
        $this->assertPivot($ciclo, $this->grupoA, '2027-02-08', '2027-03-08', 10.0);
    }

    /** @test */
    public function grupo_compartido_es_tramo_fijo_y_advierte_si_supera_la_fecha(): void
    {
        $this->crearCiclo(['fecha_fin_automatica' => true, 'grupos' => [$this->grupoA->id]]);
        $origen = Ciclo::latest('id')->first();

        $response = $this->crearCicloFijo('2027-02-24', '2027-02-10');
        $ciclo    = Ciclo::latest('id')->first();

        // A conserva el slot del origen (no se ajusta) y termina después de la fecha fijada
        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-03-01', 10.0);
        $this->assertEquals($origen->id, $this->pivot($ciclo, $this->grupoA)->ciclo_origen_id);
        $this->assertStringContainsString("El grupo 'Grupo A' comparte fechas", $response->json('data.ajuste.advertencia'));
    }

    /** @test */
    public function el_modulo_libre_arranca_despues_del_tramo_compartido_y_se_ajusta_al_resto(): void
    {
        $this->crearCiclo(['fecha_fin_automatica' => true, 'grupos' => [$this->grupoA->id]]);

        // Ciclo que entra con A en ejecución: B no se dicta en paralelo, arranca el martes 03-02.
        // Fecha fin 03-23 → caben 4 martes (02, 09, 16, 23) = 8 de 10 h
        $this->crearCicloFijo('2027-03-23', '2027-02-10');
        $ciclo = Ciclo::latest('id')->first();

        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-03-01', 10.0);
        $this->assertPivot($ciclo, $this->grupoB, '2027-03-02', '2027-03-23', 8.0);
    }

    /** @test */
    public function el_ciclo_entra_al_modulo_en_curso_y_el_primer_modulo_pasa_al_final(): void
    {
        // Ciclo de referencia con B (módulo 2) en ejecución del 02-02 al 03-02
        $this->crearCiclo(['fecha_fin_automatica' => true, 'fecha_inicio' => '2027-02-02', 'grupos' => [$this->grupoB->id]]);

        // Nuevo ciclo con A (módulo 1, libre) y B: la rotación arranca en B y A va al final
        $this->crearCiclo(['fecha_fin_automatica' => true, 'fecha_inicio' => '2027-02-03']);
        $ciclo = Ciclo::latest('id')->first();

        $this->assertPivot($ciclo, $this->grupoB, '2027-02-02', '2027-03-02', 10.0);
        // A: primer lunes después del 03-02 → 03-08, 5 lunes
        $this->assertPivot($ciclo, $this->grupoA, '2027-03-08', '2027-04-05', 10.0);
        $this->assertEquals('2027-04-05', $ciclo->fresh()->fecha_fin->toDateString());
    }

    /** @test */
    public function umbral_configurable_controla_la_advertencia(): void
    {
        config(['academico.factor_ajuste_minimo' => 0.5]);

        $this->crearCicloFijo('2027-03-02') // factor 0,6
            ->assertJsonPath('data.ajuste.factor', 0.6)
            ->assertJsonPath('data.ajuste.advertencia', null);
    }

    /** @test */
    public function modo_automatico_no_tiene_factor_ni_advertencia(): void
    {
        $this->crearCiclo(['fecha_fin_automatica' => true])
            ->assertJsonPath('data.fecha_fin', '2027-03-30')
            ->assertJsonPath('data.fecha_fin_teorica', '2027-03-30')
            ->assertJsonPath('data.ajuste.modo', 'automatico')
            ->assertJsonPath('data.ajuste.factor', null)
            ->assertJsonPath('data.grupos.0.horas_planeadas', 10);
    }

    // =========================================================================
    // Update, recálculo y validación
    // =========================================================================

    /** @test */
    public function pasar_un_ciclo_a_fecha_fija_por_update_recalcula_el_ajuste(): void
    {
        $this->crearCiclo(['fecha_fin_automatica' => true]);
        $ciclo = Ciclo::latest('id')->first();

        $this->actingAs($this->usuario)
            ->putJson(route('ciclos.update', $ciclo), ['fecha_fin_automatica' => false, 'fecha_fin' => '2027-03-16'])
            ->assertOk()
            ->assertJsonPath('data.fecha_fin', '2027-03-16')
            ->assertJsonPath('data.ajuste.modo', 'fijo');

        $this->assertPivot($ciclo, $this->grupoB, '2027-02-23', '2027-03-16', 8.0);
    }

    /** @test */
    public function festivo_en_ciclo_de_fecha_fija_conserva_la_fecha_y_reduce_el_factor(): void
    {
        $this->crearCicloFijo('2027-03-30');
        $ciclo = Ciclo::latest('id')->first();
        $this->assertEquals(1.0, $ciclo->factor_ajuste);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), ['fecha' => '2027-02-15', 'nombre' => 'Cierre', 'tipo' => 'otro'])
            ->assertCreated()
            ->assertJsonPath('meta.ciclos_recalculados.0.fecha_fin', '2027-03-30');

        $this->assertEquals(0.8, $response->json('meta.ciclos_recalculados.0.factor_ajuste'));

        // A pierde el 15 → 4 sesiones: 01, 08, 22-feb y 01-mar; B: 02, 09, 16 y 23-mar
        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-03-01', 8.0);
        $this->assertPivot($ciclo, $this->grupoB, '2027-03-02', '2027-03-23', 8.0);
        $this->assertEquals('2027-03-30', $ciclo->fresh()->fecha_fin->toDateString());
    }

    /** @test */
    public function store_en_modo_fijo_exige_fecha_fin(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.store'), $this->datosCiclo(['fecha_fin_automatica' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_fin']);
    }

    /** @test */
    public function store_rechaza_un_grupo_sin_horarios_porque_su_modulo_quedaria_sin_fechas(): void
    {
        $sinHorario = Grupo::factory()->create(['sede_id' => $this->sede->id, 'modulo_id' => $this->moduloA->id, 'status' => 1, 'nombre' => 'Grupo Sin Horario']);

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.store'), $this->datosCiclo(['grupos' => [$sinHorario->id, $this->grupoB->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupos')
            ->assertJsonFragment(['El grupo Grupo Sin Horario no tiene horarios: no se pueden calcular las fechas de su módulo.']);

        $this->assertDatabaseCount('ciclos', 0);
    }

    /** @test */
    public function update_rechaza_agregar_un_grupo_sin_horarios(): void
    {
        $this->crearCiclo([]);
        $ciclo      = Ciclo::firstOrFail();
        $sinHorario = Grupo::factory()->create(['sede_id' => $this->sede->id, 'modulo_id' => $this->moduloA->id, 'status' => 1]);

        $this->actingAs($this->usuario)
            ->putJson(route('ciclos.update', $ciclo), ['grupos' => [$sinHorario->id, $this->grupoB->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupos');
    }

    /** @test */
    public function update_rechaza_quitar_la_fecha_fin_en_modo_fijo(): void
    {
        $this->crearCicloFijo('2027-06-30');
        $ciclo = Ciclo::latest('id')->first();

        $this->actingAs($this->usuario)
            ->putJson(route('ciclos.update', $ciclo), ['fecha_fin' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_fin']);
    }

    // =========================================================================
    // Previsualización
    // =========================================================================

    /** @test */
    public function previsualizar_con_fecha_fin_informa_ajuste_horas_y_advertencia(): void
    {
        $response = $this->actingAs($this->usuario)
            ->getJson(route('ciclos.previsualizar', [
                'curso_id'     => $this->curso->id,
                'fecha_inicio' => self::INICIO,
                'fecha_fin'    => '2027-03-02',
            ]))
            ->assertOk()
            ->assertJsonPath('data.fecha_fin', '2027-03-02')
            ->assertJsonPath('data.fecha_fin_teorica', '2027-03-30')
            ->assertJsonPath('data.horas_semana_sugeridas', 3.3)
            ->assertJsonPath('data.modulos.1.grupos.0.fecha_fin', '2027-03-02')
            ->assertJsonPath('data.modulos.1.grupos.0.horas_planeadas', 6);

        $this->assertEqualsWithDelta(60, $response->json('data.porcentaje_horas'), 0.01);
        $this->assertStringContainsString('60 %', $response->json('data.advertencia'));
    }

    /** @test */
    public function previsualizar_sigue_la_secuencia_ciclica_desde_el_modulo_en_curso(): void
    {
        $this->crearCiclo(['fecha_fin_automatica' => true, 'fecha_inicio' => '2027-02-02', 'grupos' => [$this->grupoB->id]]);

        $this->actingAs($this->usuario)
            ->getJson(route('ciclos.previsualizar', ['curso_id' => $this->curso->id, 'fecha_inicio' => '2027-02-03']))
            ->assertOk()
            // Orden canónico en la respuesta; A (libre) se calcula después del tramo en curso de B
            ->assertJsonPath('data.modulos.0.grupos.0.fecha_inicio', '2027-03-08')
            ->assertJsonPath('data.modulos.1.grupos.0.con_fechas', true)
            ->assertJsonPath('data.modulos.1.grupos.0.fecha_fin', '2027-03-02');
    }

    /** @test */
    public function previsualizar_sin_fecha_fin_no_ajusta(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('ciclos.previsualizar', ['curso_id' => $this->curso->id, 'fecha_inicio' => self::INICIO]))
            ->assertOk()
            ->assertJsonPath('data.factor_ajuste', null)
            ->assertJsonPath('data.advertencia', null)
            ->assertJsonPath('data.fecha_fin_teorica', '2027-03-30')
            ->assertJsonPath('data.modulos.0.grupos.0.horas_planeadas', 10);
    }

    /** @test */
    public function previsualizar_valida_fecha_fin_posterior_al_inicio(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('ciclos.previsualizar', [
                'curso_id' => $this->curso->id, 'fecha_inicio' => self::INICIO, 'fecha_fin' => '2027-01-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_fin']);
    }

    // =========================================================================
    // Planeación por tema
    // =========================================================================

    /** @test */
    public function planeacion_reparte_las_horas_ajustadas_entre_los_temas_sin_tocar_el_curriculo(): void
    {
        $topicoId = DB::table('topicos')->insertGetId(['nombre' => 'Tópico 1', 'descripcion' => '-', 'duracion' => 10, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $tema1    = DB::table('temas')->insertGetId(['nombre' => 'Tema 1', 'descripcion' => '-', 'duracion' => 4, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $tema2    = DB::table('temas')->insertGetId(['nombre' => 'Tema 2', 'descripcion' => '-', 'duracion' => 6, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('topico_modulo')->insert(['topico_id' => $topicoId, 'modulo_id' => $this->moduloA->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tema_topico')->insert([
            ['tema_id' => $tema1, 'topico_id' => $topicoId, 'created_at' => now(), 'updated_at' => now()],
            ['tema_id' => $tema2, 'topico_id' => $topicoId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->crearCicloFijo('2027-03-02'); // factor ≈ 0,6 → A = 6 h en 3 lunes (01, 08 y 15-feb)
        $ciclo = Ciclo::latest('id')->first();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('ciclos.planeacion', $ciclo))
            ->assertOk()
            ->assertJsonPath('data.grupos.0.horas_planeadas', 6)
            ->assertJsonPath('data.grupos.0.sesiones', 3);

        $temas = $response->json('data.grupos.0.topicos.0.temas');
        $this->assertEquals(2.4, $temas[0]['horas_ajustadas']);
        $this->assertEquals('2 h 24 min', $temas[0]['tiempo_ajustado']);
        $this->assertEquals(['2027-02-01', '2027-02-08'], [$temas[0]['fecha_inicio'], $temas[0]['fecha_fin']]);
        $this->assertEquals(3.6, $temas[1]['horas_ajustadas']);
        $this->assertEquals('3 h 36 min', $temas[1]['tiempo_ajustado']);
        $this->assertEquals(['2027-02-08', '2027-02-15'], [$temas[1]['fecha_inicio'], $temas[1]['fecha_fin']]);

        // El currículo no cambia
        $this->assertDatabaseHas('temas', ['id' => $tema1, 'duracion' => 4]);
        $this->assertDatabaseHas('temas', ['id' => $tema2, 'duracion' => 6]);
    }

    /** @test */
    public function planeacion_requiere_permiso(): void
    {
        $this->crearCiclo(['fecha_fin_automatica' => true]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('ciclos.planeacion', Ciclo::latest('id')->first()))
            ->assertForbidden();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function crearCicloFijo(string $fechaFin, string $fechaInicio = self::INICIO): \Illuminate\Testing\TestResponse
    {
        return $this->crearCiclo(['fecha_fin_automatica' => false, 'fecha_fin' => $fechaFin, 'fecha_inicio' => $fechaInicio]);
    }

    private function crearCiclo(array $datos): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->usuario)
            ->postJson(route('ciclos.store'), $this->datosCiclo($datos))
            ->assertCreated();
    }

    private function datosCiclo(array $datos): array
    {
        static $n = 0;

        return array_merge([
            'sede_id'      => $this->sede->id,
            'curso_id'     => $this->curso->id,
            'nombre'       => 'Ciclo ' . (++$n),
            'fecha_inicio' => self::INICIO,
            'status'       => 1,
            'grupos'       => [$this->grupoA->id, $this->grupoB->id],
        ], $datos);
    }

    private function pivot(Ciclo $ciclo, Grupo $grupo): object
    {
        return DB::table('ciclo_grupo')->where('ciclo_id', $ciclo->id)->where('grupo_id', $grupo->id)->first();
    }

    private function assertPivot(Ciclo $ciclo, Grupo $grupo, string $inicio, string $fin, float $horas): void
    {
        $pivot = $this->pivot($ciclo, $grupo);
        $this->assertEquals($inicio, $pivot->fecha_inicio_grupo, "fecha_inicio_grupo del grupo {$grupo->id}");
        $this->assertEquals($fin, $pivot->fecha_fin_grupo, "fecha_fin_grupo del grupo {$grupo->id}");
        $this->assertEquals($horas, (float) $pivot->horas_planeadas, "horas_planeadas del grupo {$grupo->id}");
    }

    private function crearHorario(Grupo $grupo, string $dia): void
    {
        $this->areaId ??= DB::table('areas')->insertGetId([
            'nombre' => 'Área Test', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('horarios')->insert([
            'sede_id' => $this->sede->id, 'area_id' => $this->areaId, 'grupo_id' => $grupo->id,
            'grupo_nombre' => $grupo->nombre, 'tipo' => false, 'periodo' => true, 'dia' => $dia,
            'hora' => '08:00:00', 'duracion_horas' => 2, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
