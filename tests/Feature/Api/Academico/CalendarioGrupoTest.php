<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CalendarioGrupoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Sede $sede;
    private Curso $curso;
    private Modulo $moduloA;
    private Modulo $moduloB;
    private Grupo $grupoA;
    private Grupo $grupoB;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_ciclos',           'descripcion' => 'ver ciclos']);
        Permission::create(['name' => 'aca_cicloCrear',       'descripcion' => 'crear ciclo']);
        Permission::create(['name' => 'aca_cicloEditar',      'descripcion' => 'editar ciclo']);
        Permission::create(['name' => 'aca_cicloInactivar',   'descripcion' => 'inactivar ciclo']);
        Permission::create(['name' => 'aca_claseProgramar',   'descripcion' => 'programar clases']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'aca_ciclos', 'aca_cicloCrear', 'aca_cicloEditar', 'aca_cicloInactivar', 'aca_claseProgramar',
        ]);

        $this->sede = Sede::factory()->create();
        $this->curso = Curso::factory()->create();

        // Crear módulos directamente (sin factory) para evitar que configure() los asocie
        // aleatoriamente al mismo curso y provoque UniqueConstraintViolation en modulo_curso.
        $this->moduloA = Modulo::create(['nombre' => 'Módulo A', 'duracion' => 10, 'status' => 1]);
        $this->moduloB = Modulo::create(['nombre' => 'Módulo B', 'duracion' => 10, 'status' => 1]);

        // Asociar módulos al curso con orden canónico
        DB::table('modulo_curso')->insert([
            ['modulo_id' => $this->moduloA->id, 'curso_id' => $this->curso->id, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['modulo_id' => $this->moduloB->id, 'curso_id' => $this->curso->id, 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Un grupo por módulo con horarios (lunes 2 h/semana → 5 semanas para 10 h)
        $this->grupoA = Grupo::factory()->create([
            'sede_id'   => $this->sede->id,
            'modulo_id' => $this->moduloA->id,
            'status'    => 1,
        ]);
        $this->grupoB = Grupo::factory()->create([
            'sede_id'   => $this->sede->id,
            'modulo_id' => $this->moduloB->id,
            'status'    => 1,
        ]);

        $this->crearHorario($this->grupoA, 'lunes', '08:00:00', 2);
        $this->crearHorario($this->grupoB, 'martes', '08:00:00', 2);
    }

    // =========================================================================
    // previsualizarCalendario
    // =========================================================================

    /** @test */
    public function previsualizar_retorna_estructura_correcta_para_grupos_sin_fechas(): void
    {
        $fechaInicio = '2026-08-04'; // lunes

        $response = $this->actingAs($this->usuario)
            ->getJson(route('ciclos.previsualizar', [
                'curso_id'    => $this->curso->id,
                'fecha_inicio' => $fechaInicio,
            ]));

        $response->assertOk()
            ->assertJsonPath('data.curso_id', $this->curso->id)
            ->assertJsonPath('data.fecha_inicio', $fechaInicio)
            ->assertJsonCount(2, 'data.modulos');

        $primerModulo = $response->json('data.modulos.0');
        $this->assertEquals($this->moduloA->id, $primerModulo['modulo_id']);
        $this->assertEquals(1, $primerModulo['orden']);
        $this->assertCount(1, $primerModulo['grupos']);

        $primerGrupo = $primerModulo['grupos'][0];
        $this->assertFalse($primerGrupo['con_fechas']);
        $this->assertEquals($fechaInicio, $primerGrupo['fecha_inicio']);
        $this->assertNotNull($primerGrupo['fecha_fin']);
        $this->assertNull($primerGrupo['ciclo_referencia_id']);
    }

    /** @test */
    public function previsualizar_muestra_con_fechas_true_cuando_grupo_tiene_ejecucion_activa(): void
    {
        // Ciclo de referencia que le asigna fechas activas al grupoA
        $cicloRef = $this->crearCicloConFechasGrupo($this->grupoA, '2026-08-01', '2026-09-30');

        $response = $this->actingAs($this->usuario)
            ->getJson(route('ciclos.previsualizar', [
                'curso_id'    => $this->curso->id,
                'fecha_inicio' => '2026-08-10',
            ]));

        $response->assertOk();

        $grupoAData = $response->json('data.modulos.0.grupos.0');
        $this->assertTrue($grupoAData['con_fechas']);
        $this->assertEquals('2026-08-01', $grupoAData['fecha_inicio']);
        $this->assertEquals('2026-09-30', $grupoAData['fecha_fin']);
        $this->assertEquals($cicloRef->id, $grupoAData['ciclo_referencia_id']);
    }

    /** @test */
    public function previsualizar_requiere_permiso_aca_ciclos(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('ciclos.previsualizar', ['curso_id' => $this->curso->id, 'fecha_inicio' => '2026-08-04']))
            ->assertForbidden();
    }

    /** @test */
    public function previsualizar_valida_campos_requeridos(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('ciclos.previsualizar'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['curso_id', 'fecha_inicio']);
    }

    // =========================================================================
    // store: asignación de fechas calendar-aware
    // =========================================================================

    /** @test */
    public function store_usa_fechas_existentes_cuando_grupo_tiene_ejecucion_activa(): void
    {
        // Ciclo de referencia que establece fechas para grupoA
        $this->crearCicloConFechasGrupo($this->grupoA, '2026-08-01', '2026-09-30');

        $response = $this->actingAs($this->usuario)
            ->postJson(route('ciclos.store'), [
                'sede_id'             => $this->sede->id,
                'curso_id'            => $this->curso->id,
                'nombre'              => 'Ciclo Nuevo',
                'fecha_inicio'        => '2026-08-10',
                'fecha_fin_automatica' => true,
                'status'              => 1,
                'grupos'              => [$this->grupoA->id],
            ]);

        $response->assertCreated();

        $cicloNuevo = Ciclo::latest('id')->first();
        $pivot = DB::table('ciclo_grupo')
            ->where('ciclo_id', $cicloNuevo->id)
            ->where('grupo_id', $this->grupoA->id)
            ->first();

        // Debe usar las fechas del ciclo de referencia, no calcular de cero
        $this->assertEquals('2026-08-01', $pivot->fecha_inicio_grupo);
        $this->assertEquals('2026-09-30', $pivot->fecha_fin_grupo);
    }

    /** @test */
    public function store_calcula_fechas_nuevas_cuando_grupo_esta_entre_ejecuciones(): void
    {
        // Sin ciclo de referencia → grupo entre ejecuciones
        $fechaInicio = '2026-08-04'; // lunes

        $response = $this->actingAs($this->usuario)
            ->postJson(route('ciclos.store'), [
                'sede_id'             => $this->sede->id,
                'curso_id'            => $this->curso->id,
                'nombre'              => 'Ciclo Bootstrap',
                'fecha_inicio'        => $fechaInicio,
                'fecha_fin_automatica' => true,
                'status'              => 1,
                'grupos'              => [$this->grupoA->id],
            ]);

        $response->assertCreated();

        $ciclo = Ciclo::latest('id')->first();
        $pivot = DB::table('ciclo_grupo')
            ->where('ciclo_id', $ciclo->id)
            ->where('grupo_id', $this->grupoA->id)
            ->first();

        // Debe tener fecha_inicio_grupo = fecha de inicio del ciclo
        $this->assertEquals($fechaInicio, $pivot->fecha_inicio_grupo);
        $this->assertNotNull($pivot->fecha_fin_grupo);

        // 10 h / 2 h_semana = 5 semanas → fecha_fin = fechaInicio + 5 semanas
        $fechaFinEsperada = Carbon::parse($fechaInicio)->addWeeks(5)->format('Y-m-d');
        $this->assertEquals($fechaFinEsperada, $pivot->fecha_fin_grupo);

        // El ciclo también debe tener fecha_fin actualizada
        $this->assertEquals($fechaFinEsperada, $ciclo->fresh()->fecha_fin->format('Y-m-d'));
    }

    /** @test */
    public function store_secuencia_mixta_combina_fechas_existentes_y_calculadas(): void
    {
        // grupoA tiene fechas activas, grupoB está entre ejecuciones
        $this->crearCicloConFechasGrupo($this->grupoA, '2026-08-01', '2026-09-05');

        $fechaInicio = '2026-08-10';

        $response = $this->actingAs($this->usuario)
            ->postJson(route('ciclos.store'), [
                'sede_id'             => $this->sede->id,
                'curso_id'            => $this->curso->id,
                'nombre'              => 'Ciclo Mixto',
                'fecha_inicio'        => $fechaInicio,
                'fecha_fin_automatica' => true,
                'status'              => 1,
                'grupos'              => [$this->grupoA->id, $this->grupoB->id],
            ]);

        $response->assertCreated();

        $ciclo = Ciclo::latest('id')->first();

        $pivotA = DB::table('ciclo_grupo')
            ->where('ciclo_id', $ciclo->id)->where('grupo_id', $this->grupoA->id)->first();
        $pivotB = DB::table('ciclo_grupo')
            ->where('ciclo_id', $ciclo->id)->where('grupo_id', $this->grupoB->id)->first();

        // grupoA → fechas del ciclo de referencia
        $this->assertEquals('2026-08-01', $pivotA->fecha_inicio_grupo);
        $this->assertEquals('2026-09-05', $pivotA->fecha_fin_grupo);

        // grupoB → calculado desde fecha_inicio del ciclo
        $this->assertEquals($fechaInicio, $pivotB->fecha_inicio_grupo);
        $this->assertNotNull($pivotB->fecha_fin_grupo);
    }

    // =========================================================================
    // generarAutomaticas
    // =========================================================================

    /** @test */
    public function generar_automaticas_crea_clases_segun_horario_del_grupo(): void
    {
        // Crear ciclo con fechas en el pivot (una semana, lunes y miércoles)
        $ciclo = Ciclo::factory()->create([
            'sede_id'      => $this->sede->id,
            'curso_id'     => $this->curso->id,
            'fecha_inicio' => '2026-08-03', // lunes
            'status'       => 1,
        ]);

        // Asignar grupoA con fechas que cubren lunes 4-ago y lunes 11-ago
        DB::table('ciclo_grupo')->insert([
            'ciclo_id'           => $ciclo->id,
            'grupo_id'           => $this->grupoA->id,
            'orden'              => 1,
            'fecha_inicio_grupo' => '2026-08-03',
            'fecha_fin_grupo'    => '2026-08-17', // 2 lunes: 10-ago y 17-ago también
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('asistencia-clases-programadas.generar-automaticas'), [
                'grupo_id' => $this->grupoA->id,
                'ciclo_id' => $ciclo->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('clases_generadas', 3); // lunes 3, 10, 17 agosto

        $this->assertDatabaseCount('asistencia_clases_programadas', 3);
        $this->assertDatabaseHas('asistencia_clases_programadas', [
            'grupo_id'   => $this->grupoA->id,
            'ciclo_id'   => $ciclo->id,
            'fecha_clase' => '2026-08-03',
            'hora_inicio' => '08:00:00',
            'hora_fin'   => '10:00:00',
            'estado'     => 'programada',
        ]);
    }

    /** @test */
    public function generar_automaticas_no_duplica_clases_existentes(): void
    {
        $ciclo = Ciclo::factory()->create(['sede_id' => $this->sede->id, 'curso_id' => $this->curso->id, 'status' => 1]);

        // Rango que solo cubre un lunes (3-ago), así la pre-existente cubre todo el rango
        DB::table('ciclo_grupo')->insert([
            'ciclo_id'           => $ciclo->id,
            'grupo_id'           => $this->grupoA->id,
            'orden'              => 1,
            'fecha_inicio_grupo' => '2026-08-03',
            'fecha_fin_grupo'    => '2026-08-09', // solo llega al domingo, 1 solo lunes
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Clase pre-existente para el único lunes del rango (3-ago)
        AsistenciaClaseProgramada::factory()->create([
            'grupo_id'    => $this->grupoA->id,
            'ciclo_id'    => $ciclo->id,
            'fecha_clase' => '2026-08-03',
            'hora_inicio' => '08:00:00',
            'hora_fin'    => '10:00:00',
            'estado'      => 'programada',
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('asistencia-clases-programadas.generar-automaticas'), [
                'grupo_id' => $this->grupoA->id,
                'ciclo_id' => $ciclo->id,
            ]);

        $response->assertOk()->assertJsonPath('clases_generadas', 0);

        // La clase pre-existente no debe duplicarse
        $this->assertDatabaseCount('asistencia_clases_programadas', 1);
    }

    /** @test */
    public function generar_automaticas_falla_si_grupo_no_tiene_fechas_en_pivot(): void
    {
        $ciclo = Ciclo::factory()->create(['sede_id' => $this->sede->id, 'curso_id' => $this->curso->id, 'status' => 1]);

        // Asignar grupo sin fechas en el pivot
        DB::table('ciclo_grupo')->insert([
            'ciclo_id'   => $ciclo->id,
            'grupo_id'   => $this->grupoA->id,
            'orden'      => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('asistencia-clases-programadas.generar-automaticas'), [
                'grupo_id' => $this->grupoA->id,
                'ciclo_id' => $ciclo->id,
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('asistencia_clases_programadas', 0);
    }

    /** @test */
    public function generar_automaticas_requiere_permiso_aca_claseProgramar(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('asistencia-clases-programadas.generar-automaticas'), [
                'grupo_id' => $this->grupoA->id,
                'ciclo_id' => 1,
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function crearHorario(Grupo $grupo, string $dia, string $hora, int $duracionHoras): void
    {
        DB::table('horarios')->insert([
            'sede_id'        => $this->sede->id,
            'area_id'        => $this->areaId(),
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

    private ?int $cachedAreaId = null;

    private function areaId(): int
    {
        if ($this->cachedAreaId === null) {
            $this->cachedAreaId = DB::table('areas')->insertGetId([
                'nombre'     => 'Área Test',
                'status'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this->cachedAreaId;
    }

    private function crearCicloConFechasGrupo(Grupo $grupo, string $fechaInicio, string $fechaFin): Ciclo
    {
        $ciclo = Ciclo::factory()->create([
            'sede_id'      => $this->sede->id,
            'curso_id'     => $this->curso->id,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'status'       => 1,
        ]);

        DB::table('ciclo_grupo')->insert([
            'ciclo_id'           => $ciclo->id,
            'grupo_id'           => $grupo->id,
            'orden'              => 1,
            'fecha_inicio_grupo' => $fechaInicio,
            'fecha_fin_grupo'    => $fechaFin,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return $ciclo;
    }
}
