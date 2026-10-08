<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\Academico\TipoAplazamiento;
use App\Models\Configuracion\DiaNoLaborable;
use App\Models\Configuracion\Sede;
use App\Models\User;
use App\Services\Academico\CalendarioLaboralService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Integración del calendario de días no laborables con los ciclos:
 * cálculo de fechas, previsualización, cronograma, generación de clases,
 * recálculo automático, grupos compartidos y aplazamientos.
 *
 * Escenario base (todas las fechas en 2027, futuras y deterministas):
 *  - grupoA: lunes 2 h, módulo de 10 h → 5 sesiones.
 *  - grupoB: martes 2 h, módulo de 10 h → 5 sesiones.
 *  - Ciclo desde el lunes 2027-02-01:
 *      A = 02-01, 02-08, 02-15, 02-22, 03-01
 *      B = 03-02, 03-09, 03-16, 03-23, 03-30 → fecha_fin 2027-03-30
 */
class CalendarioLaboralCicloTest extends TestCase
{
    use RefreshDatabase;

    private const INICIO = '2027-02-01';

    private User $usuario;
    private Sede $sede;
    private Curso $curso;
    private Modulo $moduloA;
    private Modulo $moduloB;
    private Grupo $grupoA;
    private Grupo $grupoB;
    private ?int $areaId = null;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'aca_ciclos', 'aca_cicloCrear', 'aca_cicloEditar', 'aca_claseProgramar',
            'aca_aplazamientos', 'aca_aplazamientoCrear', 'aca_aplazamientoInactivar',
            'co_diasNoLaborables', 'co_diaNoLaborableCrear', 'co_diaNoLaborableEditar', 'co_diaNoLaborableInactivar',
        ];
        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo($permisos);

        $this->sede  = Sede::factory()->create();
        $this->curso = Curso::factory()->create();

        $this->moduloA = Modulo::create(['nombre' => 'Módulo A', 'duracion' => 10, 'status' => 1]);
        $this->moduloB = Modulo::create(['nombre' => 'Módulo B', 'duracion' => 10, 'status' => 1]);

        DB::table('modulo_curso')->insert([
            ['modulo_id' => $this->moduloA->id, 'curso_id' => $this->curso->id, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['modulo_id' => $this->moduloB->id, 'curso_id' => $this->curso->id, 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->grupoA = Grupo::factory()->create(['sede_id' => $this->sede->id, 'modulo_id' => $this->moduloA->id, 'status' => 1]);
        $this->grupoB = Grupo::factory()->create(['sede_id' => $this->sede->id, 'modulo_id' => $this->moduloB->id, 'status' => 1]);

        $this->crearHorario($this->grupoA, 'lunes', '08:00:00', 2);
        $this->crearHorario($this->grupoB, 'martes', '08:00:00', 2);
    }

    // =========================================================================
    // Cálculo de fechas
    // =========================================================================

    /** @test */
    public function sin_dias_no_laborables_la_fecha_fin_es_la_ultima_sesion(): void
    {
        $ciclo = $this->crearCicloPorApi();

        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-03-01');
        $this->assertPivot($ciclo, $this->grupoB, '2027-03-02', '2027-03-30');
        $this->assertEquals('2027-03-30', $ciclo->fresh()->fecha_fin->toDateString());
    }

    /** @test */
    public function festivo_en_dia_de_clase_corre_la_fecha_fin_a_la_siguiente_sesion(): void
    {
        $this->crearDia('2027-02-15');

        $ciclo = $this->crearCicloPorApi();

        // A pierde el lunes 15 → termina el 08-mar; B arranca el martes 09-mar
        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-03-08');
        $this->assertPivot($ciclo, $this->grupoB, '2027-03-09', '2027-04-06');
        $this->assertEquals('2027-04-06', $ciclo->fresh()->fecha_fin->toDateString());
    }

    /** @test */
    public function festivo_en_dia_sin_clase_no_cambia_las_fechas(): void
    {
        $this->crearDia('2027-02-17'); // miércoles

        $ciclo = $this->crearCicloPorApi();

        $this->assertEquals('2027-03-30', $ciclo->fresh()->fecha_fin->toDateString());
    }

    /** @test */
    public function dia_de_otra_sede_se_ignora_y_dia_de_la_misma_sede_aplica(): void
    {
        $otraSede = Sede::factory()->create();
        $this->crearDia('2027-02-08', $otraSede->id);
        $this->crearDia('2027-03-09', $this->sede->id);

        $ciclo = $this->crearCicloPorApi();

        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-03-01');
        $this->assertPivot($ciclo, $this->grupoB, '2027-03-02', '2027-04-06');
    }

    /** @test */
    public function dias_inactivos_o_eliminados_no_cuentan(): void
    {
        $this->crearDia('2027-02-08', null, ['status' => 0]);
        $this->crearDia('2027-02-15')->delete();

        $ciclo = $this->crearCicloPorApi();

        $this->assertEquals('2027-03-30', $ciclo->fresh()->fecha_fin->toDateString());
    }

    /** @test */
    public function rango_de_vacaciones_omite_todas_las_sesiones_del_rango(): void
    {
        $this->actingAs($this->usuario)->postJson(route('dias-no-laborables.rango'), [
            'fecha_inicio' => '2027-02-06', 'fecha_fin' => '2027-02-21', 'nombre' => 'Receso', 'tipo' => 'vacaciones',
        ])->assertCreated();

        $ciclo = $this->crearCicloPorApi();

        // A pierde 08 y 15 → 02-01, 02-22, 03-01, 03-08, 03-15
        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-03-15');
    }

    /** @test */
    public function modulo_con_horas_no_multiplo_de_la_sesion_usa_la_sesion_que_completa_las_horas(): void
    {
        $this->moduloA->update(['duracion' => 9]); // 4 sesiones = 8 h, la 5.ª completa 9 h

        $ciclo = $this->crearCicloPorApi([$this->grupoA->id]);

        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-03-01');
    }

    /** @test */
    public function grupo_con_todas_sus_sesiones_bloqueadas_no_entra_en_ciclo_infinito(): void
    {
        $lunes = Carbon::parse(self::INICIO);
        $hasta = $lunes->copy()->addDays(CalendarioLaboralService::TOPE_DIAS);
        $filas = [];
        while ($lunes->lte($hasta)) {
            $filas[] = ['fecha' => $lunes->toDateString(), 'nombre' => 'Bloqueo', 'tipo' => 'otro', 'status' => 1, 'created_at' => now(), 'updated_at' => now()];
            $lunes->addWeek();
        }
        DB::table('dias_no_laborables')->insert($filas);

        $rango = app(CalendarioLaboralService::class)->calcularRangoGrupo($this->grupoA->load('horarios', 'modulo'), Carbon::parse(self::INICIO));

        $this->assertNull($rango);
    }

    // =========================================================================
    // Previsualización y cronograma
    // =========================================================================

    /** @test */
    public function previsualizar_informa_sesiones_y_dias_omitidos(): void
    {
        $this->crearDia('2027-02-15', null, ['nombre' => 'Cierre']);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('ciclos.previsualizar', ['curso_id' => $this->curso->id, 'fecha_inicio' => self::INICIO]))
            ->assertOk();

        $grupoA = $response->json('data.modulos.0.grupos.0');
        $this->assertEquals('2027-03-08', $grupoA['fecha_fin']);
        $this->assertEquals(5, $grupoA['sesiones']);
        $this->assertEquals([['fecha' => '2027-02-15', 'nombre' => 'Cierre']], $grupoA['dias_omitidos']);

        $grupoB = $response->json('data.modulos.1.grupos.0');
        $this->assertEquals('2027-03-09', $grupoB['fecha_inicio']);
    }

    /** @test */
    public function cronograma_muestra_sesiones_reales_y_dias_omitidos(): void
    {
        $this->crearDia('2027-02-15');
        $ciclo = $this->crearCicloPorApi();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('ciclos.cronograma', $ciclo))
            ->assertOk();

        $this->assertEquals(5, $response->json('data.cronograma.0.sesiones'));
        $this->assertEquals('2027-02-15', $response->json('data.cronograma.0.dias_omitidos.0.fecha'));
    }

    // =========================================================================
    // Generación de clases programadas
    // =========================================================================

    /** @test */
    public function generar_clases_omite_los_dias_no_laborables(): void
    {
        $this->crearDia('2027-02-15');
        $ciclo = $this->crearCicloPorApi([$this->grupoA->id]);

        $this->actingAs($this->usuario)
            ->postJson(route('asistencia-clases-programadas.generar-automaticas'), [
                'grupo_id' => $this->grupoA->id,
                'ciclo_id' => $ciclo->id,
            ])
            ->assertOk()
            ->assertJsonPath('clases_generadas', 5)
            ->assertJsonPath('clases_omitidas', 1);

        $this->assertDatabaseMissing('asistencia_clases_programadas', ['ciclo_id' => $ciclo->id, 'fecha_clase' => '2027-02-15']);
    }

    // =========================================================================
    // Recálculo automático
    // =========================================================================

    /** @test */
    public function crear_y_eliminar_un_dia_recalcula_automaticamente_el_ciclo(): void
    {
        $ciclo = $this->crearCicloPorApi();

        $response = $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), ['fecha' => '2027-02-15', 'nombre' => 'Cierre', 'tipo' => 'otro'])
            ->assertCreated()
            ->assertJsonPath('meta.ciclos_recalculados.0.id', $ciclo->id)
            ->assertJsonPath('meta.ciclos_recalculados.0.fecha_fin_anterior', '2027-03-30')
            ->assertJsonPath('meta.ciclos_recalculados.0.fecha_fin', '2027-04-06');

        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-03-08');

        $dia = DiaNoLaborable::find($response->json('data.id'));
        $this->actingAs($this->usuario)->deleteJson(route('dias-no-laborables.destroy', $dia))->assertOk();

        $this->assertEquals('2027-03-30', $ciclo->fresh()->fecha_fin->toDateString());
    }

    /** @test */
    public function inactivar_un_dia_por_update_recalcula_el_ciclo(): void
    {
        $dia   = $this->crearDia('2027-02-15');
        $ciclo = $this->crearCicloPorApi();
        $this->assertEquals('2027-04-06', $ciclo->fresh()->fecha_fin->toDateString());

        $this->actingAs($this->usuario)
            ->putJson(route('dias-no-laborables.update', $dia), ['status' => 0])
            ->assertOk()
            ->assertJsonPath('meta.ciclos_recalculados.0.fecha_fin', '2027-03-30');
    }

    /** @test */
    public function dia_fuera_del_rango_del_ciclo_no_lo_recalcula(): void
    {
        $this->crearCicloPorApi();

        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), ['fecha' => '2027-06-14', 'nombre' => 'Lejano', 'tipo' => 'otro'])
            ->assertCreated()
            ->assertJsonPath('meta.ciclos_recalculados', []);
    }

    /** @test */
    public function dia_de_otra_sede_no_recalcula_el_ciclo(): void
    {
        $this->crearCicloPorApi();
        $otraSede = Sede::factory()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), [
                'fecha' => '2027-02-15', 'nombre' => 'Cierre', 'tipo' => 'otro', 'sede_id' => $otraSede->id,
            ])
            ->assertCreated()
            ->assertJsonPath('meta.ciclos_recalculados', []);
    }

    /** @test */
    public function ciclo_con_fecha_fin_manual_recalcula_grupos_pero_conserva_su_fecha_fin(): void
    {
        $ciclo = $this->crearCicloPorApi();
        $ciclo->update(['fecha_fin_automatica' => false, 'fecha_fin' => '2027-06-30']);

        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), ['fecha' => '2027-02-15', 'nombre' => 'Cierre', 'tipo' => 'otro'])
            ->assertCreated();

        $this->assertPivot($ciclo, $this->grupoA, '2027-02-01', '2027-03-08');
        $this->assertEquals('2027-06-30', $ciclo->fresh()->fecha_fin->toDateString());
    }

    /** @test */
    public function recalculo_se_propaga_a_los_ciclos_que_comparten_el_grupo(): void
    {
        $origen = $this->crearCicloPorApi([$this->grupoA->id]);
        $copia  = $this->crearCicloPorApi([$this->grupoA->id], '2027-02-10');

        // La copia toma el slot del origen y lo registra
        $pivotCopia = $this->pivot($copia, $this->grupoA);
        $this->assertEquals($origen->id, $pivotCopia->ciclo_origen_id);
        $this->assertEquals('2027-03-01', $pivotCopia->fecha_fin_grupo);

        // Festivo antes del inicio de la copia, pero dentro del slot compartido
        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), ['fecha' => '2027-02-08', 'nombre' => 'Cierre', 'tipo' => 'otro'])
            ->assertCreated();

        $this->assertPivot($origen, $this->grupoA, '2027-02-01', '2027-03-08');
        $this->assertPivot($copia, $this->grupoA, '2027-02-01', '2027-03-08');
        $this->assertEquals($origen->id, $this->pivot($copia, $this->grupoA)->ciclo_origen_id);
    }

    /** @test */
    public function recalculo_realinea_clases_pendientes_y_respeta_las_dictadas(): void
    {
        $ciclo = $this->crearCicloPorApi([$this->grupoA->id]);
        $this->actingAs($this->usuario)->postJson(route('asistencia-clases-programadas.generar-automaticas'), [
            'grupo_id' => $this->grupoA->id, 'ciclo_id' => $ciclo->id,
        ])->assertOk();

        AsistenciaClaseProgramada::where('ciclo_id', $ciclo->id)->whereDate('fecha_clase', '2027-02-01')->update(['estado' => 'dictada']);

        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), ['fecha' => '2027-02-15', 'nombre' => 'Cierre', 'tipo' => 'otro'])
            ->assertCreated()
            ->assertJsonPath('meta.ciclos_recalculados.0.clases_realineadas', 3); // 15→22, 22→01-mar, 01-mar→08-mar

        $fechas = AsistenciaClaseProgramada::where('ciclo_id', $ciclo->id)->orderBy('fecha_clase')->get()
            ->map(fn ($c) => $c->fecha_clase->toDateString() . ':' . $c->estado)->all();

        $this->assertEquals([
            '2027-02-01:dictada',
            '2027-02-08:programada',
            '2027-02-22:programada',
            '2027-03-01:programada',
            '2027-03-08:programada',
        ], $fechas);
    }

    // =========================================================================
    // Aplazamientos
    // =========================================================================

    /** @test */
    public function aplazar_realinea_las_clases_a_los_dias_del_horario_y_salta_festivos(): void
    {
        $ciclo = $this->crearCicloPorApi([$this->grupoA->id]);
        $this->actingAs($this->usuario)->postJson(route('asistencia-clases-programadas.generar-automaticas'), [
            'grupo_id' => $this->grupoA->id, 'ciclo_id' => $ciclo->id,
        ])->assertOk();

        $this->crearDia('2027-02-22');
        $tipo = TipoAplazamiento::factory()->create(['nombre' => 'Prueba']);

        // 10 días (no múltiplo de 7): antes las clases terminaban en jueves
        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $ciclo), [
                'tipo_aplazamiento_id'    => $tipo->id,
                'fecha_reinicio_probable' => '2027-02-11',
            ])
            ->assertCreated();

        $fechas = AsistenciaClaseProgramada::where('ciclo_id', $ciclo->id)->orderBy('fecha_clase')->pluck('fecha_clase')
            ->map(fn ($f) => $f->toDateString())->all();

        // Lunes desde el 11-feb, saltando el festivo del 22
        $this->assertEquals(['2027-02-15', '2027-03-01', '2027-03-08', '2027-03-15', '2027-03-22'], $fechas);
        $this->assertEquals('2027-03-22', $ciclo->fresh()->fecha_fin->toDateString());
    }

    /** @test */
    public function revertir_aplazamiento_devuelve_las_clases_a_sus_fechas_originales(): void
    {
        $ciclo = $this->crearCicloPorApi([$this->grupoA->id]);
        $this->actingAs($this->usuario)->postJson(route('asistencia-clases-programadas.generar-automaticas'), [
            'grupo_id' => $this->grupoA->id, 'ciclo_id' => $ciclo->id,
        ])->assertOk();
        $tipo = TipoAplazamiento::factory()->create(['nombre' => 'Prueba']);

        $aplazamientoId = $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $ciclo), [
                'tipo_aplazamiento_id'    => $tipo->id,
                'fecha_reinicio_probable' => '2027-02-11',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.revertir', $aplazamientoId))
            ->assertOk();

        $fechas = AsistenciaClaseProgramada::where('ciclo_id', $ciclo->id)->orderBy('fecha_clase')->pluck('fecha_clase')
            ->map(fn ($f) => $f->toDateString())->all();

        $this->assertEquals(['2027-02-01', '2027-02-08', '2027-02-15', '2027-02-22', '2027-03-01'], $fechas);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function crearCicloPorApi(?array $grupos = null, string $fechaInicio = self::INICIO): Ciclo
    {
        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.store'), [
                'sede_id'              => $this->sede->id,
                'curso_id'             => $this->curso->id,
                'nombre'               => 'Ciclo ' . $fechaInicio,
                'fecha_inicio'         => $fechaInicio,
                'fecha_fin_automatica' => true,
                'status'               => 1,
                'grupos'               => $grupos ?? [$this->grupoA->id, $this->grupoB->id],
            ])
            ->assertCreated();

        return Ciclo::latest('id')->first();
    }

    private function crearDia(string $fecha, ?int $sedeId = null, array $extra = []): DiaNoLaborable
    {
        return DiaNoLaborable::factory()->create(array_merge([
            'fecha'   => $fecha,
            'sede_id' => $sedeId,
            'status'  => 1,
        ], $extra));
    }

    private function pivot(Ciclo $ciclo, Grupo $grupo): object
    {
        return DB::table('ciclo_grupo')->where('ciclo_id', $ciclo->id)->where('grupo_id', $grupo->id)->first();
    }

    private function assertPivot(Ciclo $ciclo, Grupo $grupo, string $inicio, string $fin): void
    {
        $pivot = $this->pivot($ciclo, $grupo);
        $this->assertEquals($inicio, $pivot->fecha_inicio_grupo, "fecha_inicio_grupo del grupo {$grupo->id}");
        $this->assertEquals($fin, $pivot->fecha_fin_grupo, "fecha_fin_grupo del grupo {$grupo->id}");
    }

    private function crearHorario(Grupo $grupo, string $dia, string $hora, int $duracionHoras): void
    {
        $this->areaId ??= DB::table('areas')->insertGetId([
            'nombre' => 'Área Test', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('horarios')->insert([
            'sede_id'        => $this->sede->id,
            'area_id'        => $this->areaId,
            'grupo_id'       => $grupo->id,
            'grupo_nombre'   => $grupo->nombre,
            'tipo'           => false,
            'periodo'        => true,
            'dia'            => $dia,
            'hora'           => $hora,
            'duracion_horas' => $duracionHoras,
            'status'         => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
