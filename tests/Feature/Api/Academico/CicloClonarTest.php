<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\Configuracion\DiaNoLaborable;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Clonación de ciclos: solo se informa la nueva fecha de inicio y el resto
 * (grupos, orden, fechas por grupo, fecha fin) se calcula.
 *
 * Escenario (2027, sesiones de 2 h, módulos de 10 h = 5 sesiones):
 *  - grupoA: lunes; grupoB: martes.
 *  - Inicio lunes 2027-02-01 → A = 02-01..03-01, B = 03-02..03-30.
 *  - Clon lunes 2027-09-06  → A = 09-06..10-04, B = 10-05..11-02.
 *
 * El clon inicia después de la finalización del origen (sugerencia: primer
 * día de clase laborable) y no puede solaparse con otro ciclo en ninguno de sus grupos.
 */
class CicloClonarTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Sede $sede;
    private Curso $curso;
    private Grupo $grupoA;
    private Grupo $grupoB;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['aca_ciclos', 'aca_cicloCrear', 'aca_cicloEditar'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['aca_cicloCrear', 'aca_cicloEditar']);

        $this->sede  = Sede::factory()->create();
        $this->curso = Curso::factory()->create();

        $moduloA = Modulo::create(['nombre' => 'Módulo A', 'duracion' => 10, 'status' => 1]);
        $moduloB = Modulo::create(['nombre' => 'Módulo B', 'duracion' => 10, 'status' => 1]);

        foreach ([$moduloA, $moduloB] as $i => $modulo) {
            DB::table('modulo_curso')->insert([
                'modulo_id' => $modulo->id, 'curso_id' => $this->curso->id, 'orden' => $i + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->grupoA = Grupo::factory()->create(['sede_id' => $this->sede->id, 'modulo_id' => $moduloA->id, 'status' => 1]);
        $this->grupoB = Grupo::factory()->create(['sede_id' => $this->sede->id, 'modulo_id' => $moduloB->id, 'status' => 1]);

        $areaId = DB::table('areas')->insertGetId([
            'nombre' => 'Área Test', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$this->grupoA, 'lunes'], [$this->grupoB, 'martes']] as [$grupo, $dia]) {
            DB::table('horarios')->insert([
                'sede_id' => $this->sede->id, 'area_id' => $areaId, 'grupo_id' => $grupo->id,
                'grupo_nombre' => $grupo->nombre, 'tipo' => false, 'periodo' => true, 'dia' => $dia,
                'hora' => '08:00:00', 'duracion_horas' => 2, 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** @test */
    public function clona_el_ciclo_recalculando_fechas_desde_la_nueva_fecha_de_inicio(): void
    {
        $origen = $this->crearOrigen();

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-09-06'])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Origen (copia)')
            ->assertJsonPath('data.fecha_inicio', '2027-09-06')
            ->assertJsonPath('data.fecha_fin', '2027-11-02');

        $clon = Ciclo::where('nombre', 'Origen (copia)')->firstOrFail();
        $this->assertSame($origen->sede_id, $clon->sede_id);
        $this->assertSame($origen->curso_id, $clon->curso_id);
        $this->assertSame(0, $clon->inscritos);

        $pivots = DB::table('ciclo_grupo')->where('ciclo_id', $clon->id)->orderBy('orden')->get();
        $this->assertSame([$this->grupoA->id, $this->grupoB->id], $pivots->pluck('grupo_id')->all());
        $this->assertSame('2027-09-06', substr($pivots[0]->fecha_inicio_grupo, 0, 10));
        $this->assertSame('2027-10-04', substr($pivots[0]->fecha_fin_grupo, 0, 10));
        $this->assertSame('2027-10-05', substr($pivots[1]->fecha_inicio_grupo, 0, 10));
        $this->assertSame('2027-11-02', substr($pivots[1]->fecha_fin_grupo, 0, 10));
        $this->assertNull($pivots[0]->ciclo_origen_id);
    }

    /** @test */
    public function en_modo_fijo_conserva_la_duracion_del_origen_o_usa_la_fecha_fin_enviada(): void
    {
        $origen = $this->crearOrigen(['fecha_fin_automatica' => false, 'fecha_fin' => '2027-06-30']);

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-09-06'])
            ->assertCreated()
            ->assertJsonPath('data.fecha_fin', '2028-02-02'); // 149 días, como 2027-02-01 → 2027-06-30

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), [
                'fecha_inicio' => '2028-03-06', 'fecha_fin' => '2028-06-30', 'nombre' => 'Otro clon',
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Otro clon')
            ->assertJsonPath('data.fecha_fin', '2028-06-30');
    }

    /** @test */
    public function repetir_la_clonacion_genera_nombres_unicos(): void
    {
        $origen = $this->crearOrigen();

        foreach (['2027-09-06' => 'Origen (copia)', '2028-03-06' => 'Origen (copia 2)'] as $inicio => $esperado) {
            $this->actingAs($this->usuario)
                ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => $inicio])
                ->assertCreated()
                ->assertJsonPath('data.nombre', $esperado);
        }
    }

    /** @test */
    public function valida_fecha_inicio_obligatoria_pasada_y_nombre_duplicado(): void
    {
        $origen = $this->crearOrigen();

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), [])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_inicio');

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2020-01-06'])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_inicio');

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-09-06', 'nombre' => 'Origen'])
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');
    }

    /** @test */
    public function sin_permiso_no_puede_clonar_y_ciclo_inexistente_devuelve_404(): void
    {
        $origen = $this->crearOrigen();
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-09-06'])
            ->assertForbidden();

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', 999999), ['fecha_inicio' => '2027-09-06'])
            ->assertNotFound();
    }

    /** @test */
    public function rechaza_fecha_de_inicio_anterior_o_igual_a_la_finalizacion_del_origen(): void
    {
        $origen = $this->crearOrigen(); // termina 2027-03-30

        foreach (['2027-02-15', '2027-03-30'] as $fecha) {
            $this->actingAs($this->usuario)
                ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => $fecha])
                ->assertUnprocessable()
                ->assertJsonPath('errors.fecha_inicio.0', 'La fecha de inicio debe ser posterior a la finalización del ciclo a clonar (2027-03-30).');
        }

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-04-05'])
            ->assertCreated();
    }

    /** @test */
    public function rechaza_fecha_de_inicio_que_no_es_dia_de_clase_laborable(): void
    {
        $origen  = $this->crearOrigen(); // grupo A dicta los lunes
        $mensaje = 'La fecha de inicio debe ser un día de clase laborable del ciclo (lunes).';

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-04-07']) // miércoles
            ->assertUnprocessable()
            ->assertJsonPath('errors.fecha_inicio.0', $mensaje);

        DiaNoLaborable::create(['fecha' => '2027-04-05', 'nombre' => 'Festivo', 'tipo' => 'festivo', 'status' => 1]);

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-04-05']) // lunes festivo
            ->assertUnprocessable()
            ->assertJsonPath('errors.fecha_inicio.0', $mensaje);

        $this->assertSame(1, Ciclo::count());
    }

    /** @test */
    public function no_clona_un_ciclo_sin_fechas_calculadas(): void
    {
        $origen = $this->crearOrigen(['grupos' => []]);

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-09-06'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_inicio');

        $this->actingAs($this->usuario)
            ->getJson(route('ciclos.clonar.sugerencia', $origen))
            ->assertUnprocessable();
    }

    /** @test */
    public function sugiere_el_primer_dia_de_clase_laborable_despues_de_la_finalizacion(): void
    {
        $origen = $this->crearOrigen(); // termina martes 2027-03-30; grupo A dicta los lunes

        $this->actingAs($this->usuario)
            ->getJson(route('ciclos.clonar.sugerencia', $origen))
            ->assertOk()
            ->assertExactJson(['data' => [
                'fecha_terminacion_origen' => '2027-03-30',
                'fecha_minima'             => '2027-03-31',
                'fecha_inicio_sugerida'    => '2027-04-05',
                'dias_clase'               => ['lunes'],
            ]]);

        DiaNoLaborable::create(['fecha' => '2027-04-05', 'nombre' => 'Festivo', 'tipo' => 'festivo', 'status' => 1]);

        $this->actingAs($this->usuario)
            ->getJson(route('ciclos.clonar.sugerencia', $origen))
            ->assertJsonPath('data.fecha_inicio_sugerida', '2027-04-12');
    }

    /** @test */
    public function la_sugerencia_requiere_permiso_de_crear_ciclos(): void
    {
        $origen = $this->crearOrigen();

        $this->actingAs(User::factory()->create())
            ->getJson(route('ciclos.clonar.sugerencia', $origen))
            ->assertForbidden();
    }

    /** @test */
    public function rechaza_el_clon_si_comparte_la_ejecucion_de_un_grupo_con_otro_ciclo(): void
    {
        $origen = $this->crearOrigen();

        // Otro ciclo, iniciado antes que el clon, tiene al grupo A en curso (lunes 08-30..09-27).
        $this->crearOrigen(['nombre' => 'Ocupado', 'fecha_inicio' => '2027-08-30', 'grupos' => [$this->grupoA->id]]);

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-09-06'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_inicio')
            ->assertJsonFragment([
                "El grupo '{$this->grupoA->nombre}' (2027-08-30 a 2027-09-27) se solapa con el ciclo 'Ocupado' (2027-08-30 a 2027-09-27).",
            ]);

        $this->assertSame(2, Ciclo::count());
    }

    /** @test */
    public function rechaza_el_clon_si_sus_fechas_calculadas_se_cruzan_con_otro_ciclo(): void
    {
        $origen = $this->crearOrigen();

        // Otro ciclo, que inicia después del clon, usa el grupo B (martes 10-12..11-09):
        // el B del clon (10-05..11-02) se cruza con él.
        $this->crearOrigen(['nombre' => 'Ocupado', 'fecha_inicio' => '2027-10-12', 'grupos' => [$this->grupoB->id]]);

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-09-06'])
            ->assertUnprocessable()
            ->assertJsonFragment([
                "El grupo '{$this->grupoB->nombre}' (2027-10-05 a 2027-11-02) se solapa con el ciclo 'Ocupado' (2027-10-12 a 2027-11-09).",
            ]);

        $this->assertSame(2, Ciclo::count());
    }

    /** @test */
    public function un_ciclo_eliminado_no_bloquea_la_clonacion(): void
    {
        $origen = $this->crearOrigen();

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-09-06'])
            ->assertCreated();
        Ciclo::where('nombre', 'Origen (copia)')->firstOrFail()->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-09-06'])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Origen (copia 2)');
    }

    /** @test */
    public function recalcular_el_origen_no_adopta_las_fechas_del_clon_ni_viceversa(): void
    {
        $origen = $this->crearOrigen();

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.clonar', $origen), ['fecha_inicio' => '2027-09-06'])
            ->assertCreated();
        $clon = Ciclo::where('nombre', 'Origen (copia)')->firstOrFail();

        foreach ([$origen, $clon] as $ciclo) {
            $this->actingAs($this->usuario)
                ->postJson(route('ciclos.calcular-fecha-fin', $ciclo))
                ->assertOk();
        }

        $this->assertSame('2027-03-30', $origen->fresh()->fecha_fin->toDateString());
        $this->assertSame('2027-11-02', $clon->fresh()->fecha_fin->toDateString());
        $this->assertSame(0, DB::table('ciclo_grupo')->whereNotNull('ciclo_origen_id')->count());
    }

    /**
     * Crea un ciclo origen (por defecto 2027-02-01) con ambos grupos en orden A, B.
     */
    private function crearOrigen(array $extra = []): Ciclo
    {
        $datos = array_merge([
            'sede_id'      => $this->sede->id,
            'curso_id'     => $this->curso->id,
            'nombre'       => 'Origen',
            'fecha_inicio' => '2027-02-01',
            'grupos'       => [$this->grupoA->id, $this->grupoB->id],
        ], $extra);

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.store'), $datos)
            ->assertCreated();

        return Ciclo::where('nombre', $datos['nombre'])->firstOrFail();
    }
}
