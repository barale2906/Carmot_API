<?php

namespace Tests\Feature\Api\Configuracion;

use App\Models\Configuracion\DiaNoLaborable;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD del calendario de días no laborables.
 * El efecto sobre los ciclos se prueba en CalendarioLaboralCicloTest.
 */
class DiaNoLaborableTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'co_diasNoLaborables',        'descripcion' => 'ver días no laborables']);
        Permission::create(['name' => 'co_diaNoLaborableCrear',     'descripcion' => 'crear días no laborables']);
        Permission::create(['name' => 'co_diaNoLaborableEditar',    'descripcion' => 'editar días no laborables']);
        Permission::create(['name' => 'co_diaNoLaborableInactivar', 'descripcion' => 'inactivar días no laborables']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'co_diasNoLaborables', 'co_diaNoLaborableCrear', 'co_diaNoLaborableEditar', 'co_diaNoLaborableInactivar',
        ]);

        $this->sede = Sede::factory()->create();
    }

    // ─── index / calendario ───────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada(): void
    {
        DiaNoLaborable::factory()->count(3)->create();

        $this->actingAs($this->usuario)
            ->getJson(route('dias-no-laborables.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'fecha', 'nombre', 'tipo', 'tipo_text', 'sede_id', 'todas_las_sedes', 'status', 'status_text']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            ]);
    }

    /** @test */
    public function index_filtrado_por_sede_incluye_los_generales(): void
    {
        $otraSede = Sede::factory()->create();
        DiaNoLaborable::factory()->create(['fecha' => '2027-01-01', 'sede_id' => null]);
        DiaNoLaborable::factory()->create(['fecha' => '2027-02-01', 'sede_id' => $this->sede->id]);
        DiaNoLaborable::factory()->create(['fecha' => '2027-03-01', 'sede_id' => $otraSede->id]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('dias-no-laborables.index', ['sede_id' => $this->sede->id]))
            ->assertOk();

        $this->assertEqualsCanonicalizing(['2027-01-01', '2027-02-01'], collect($response->json('data'))->pluck('fecha')->all());
    }

    /** @test */
    public function calendario_retorna_solo_activos_del_anio_ordenados(): void
    {
        DiaNoLaborable::factory()->create(['fecha' => '2027-05-01', 'tipo' => 'festivo']);
        DiaNoLaborable::factory()->create(['fecha' => '2027-01-01', 'tipo' => 'festivo']);
        DiaNoLaborable::factory()->create(['fecha' => '2027-12-28', 'tipo' => 'vacaciones']);
        DiaNoLaborable::factory()->create(['fecha' => '2027-03-03', 'status' => 0]);
        DiaNoLaborable::factory()->create(['fecha' => '2028-01-01']);

        $this->actingAs($this->usuario)
            ->getJson(route('dias-no-laborables.calendario', ['anio' => 2027]))
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.por_tipo.festivo', 2)
            ->assertJsonPath('meta.por_tipo.vacaciones', 1)
            ->assertJsonPath('data.0.fecha', '2027-01-01')
            ->assertJsonPath('data.2.fecha', '2027-12-28');
    }

    /** @test */
    public function calendario_requiere_anio(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('dias-no-laborables.calendario'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['anio']);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_dia_general_para_todas_las_sedes(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), [
                'fecha'  => '2027-04-10',
                'nombre' => 'Aniversario del instituto',
                'tipo'   => 'institucional',
            ])
            ->assertCreated()
            ->assertJsonPath('data.todas_las_sedes', true)
            ->assertJsonPath('data.status', 1)
            ->assertJsonPath('meta.ciclos_recalculados', []);

        $this->assertDatabaseHas('dias_no_laborables', ['fecha' => '2027-04-10', 'sede_id' => null, 'tipo' => 'institucional']);
    }

    /** @test */
    public function store_crea_dia_para_una_sede(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), [
                'fecha'   => '2027-04-10',
                'nombre'  => 'Cierre por obras',
                'tipo'    => 'otro',
                'sede_id' => $this->sede->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.todas_las_sedes', false)
            ->assertJsonPath('data.sede.id', $this->sede->id);
    }

    /** @test */
    public function store_valida_campos_requeridos_y_tipo(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), ['tipo' => 'feriado'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha', 'nombre', 'tipo']);
    }

    /** @test */
    public function store_rechaza_fecha_repetida_para_la_misma_sede(): void
    {
        DiaNoLaborable::factory()->create(['fecha' => '2027-04-10', 'sede_id' => $this->sede->id]);

        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), [
                'fecha' => '2027-04-10', 'nombre' => 'Otro', 'tipo' => 'otro', 'sede_id' => $this->sede->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha']);
    }

    /** @test */
    public function store_rechaza_dia_de_sede_si_ya_existe_como_general(): void
    {
        DiaNoLaborable::factory()->create(['fecha' => '2027-04-10', 'sede_id' => null]);

        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), [
                'fecha' => '2027-04-10', 'nombre' => 'Otro', 'tipo' => 'otro', 'sede_id' => $this->sede->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha']);
    }

    /** @test */
    public function store_permite_la_fecha_de_un_dia_eliminado(): void
    {
        DiaNoLaborable::factory()->create(['fecha' => '2027-04-10', 'sede_id' => null])->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.store'), ['fecha' => '2027-04-10', 'nombre' => 'Nuevo', 'tipo' => 'festivo'])
            ->assertCreated();
    }

    // ─── rango ────────────────────────────────────────────────────────────────

    /** @test */
    public function rango_crea_un_dia_por_fecha_y_omite_existentes(): void
    {
        DiaNoLaborable::factory()->create(['fecha' => '2027-12-25', 'sede_id' => null, 'nombre' => 'Navidad']);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.rango'), [
                'fecha_inicio' => '2027-12-20',
                'fecha_fin'    => '2027-12-31',
                'nombre'       => 'Vacaciones colectivas',
                'tipo'         => 'vacaciones',
            ])
            ->assertCreated();

        $this->assertCount(11, $response->json('data.creados'));
        $this->assertCount(1, $response->json('data.omitidos'));
        $this->assertEquals('2027-12-25', $response->json('data.omitidos.0.fecha'));
        $this->assertDatabaseCount('dias_no_laborables', 12);
    }

    /** @test */
    public function rango_valida_orden_de_fechas_y_tope(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.rango'), [
                'fecha_inicio' => '2027-12-20', 'fecha_fin' => '2027-12-10', 'nombre' => 'X', 'tipo' => 'vacaciones',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_fin']);

        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.rango'), [
                'fecha_inicio' => '2027-01-01', 'fecha_fin' => '2028-06-01', 'nombre' => 'X', 'tipo' => 'vacaciones',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_fin']);
    }

    // ─── generar-festivos ─────────────────────────────────────────────────────

    /** @test */
    public function generar_festivos_crea_los_festivos_de_colombia_del_anio(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.generar-festivos'), ['anio' => 2026])
            ->assertCreated()
            ->assertJsonCount(18, 'data.creados')
            ->assertJsonCount(0, 'data.omitidos');

        $this->assertDatabaseHas('dias_no_laborables', ['fecha' => '2026-08-17', 'nombre' => 'Asunción de la Virgen', 'tipo' => 'festivo', 'sede_id' => null]);
    }

    /** @test */
    public function generar_festivos_dos_veces_no_duplica(): void
    {
        $this->actingAs($this->usuario)->postJson(route('dias-no-laborables.generar-festivos'), ['anio' => 2026]);

        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.generar-festivos'), ['anio' => 2026])
            ->assertOk()
            ->assertJsonCount(0, 'data.creados')
            ->assertJsonCount(18, 'data.omitidos');

        $this->assertDatabaseCount('dias_no_laborables', 18);
    }

    /** @test */
    public function generar_festivos_no_revive_un_festivo_eliminado(): void
    {
        $this->actingAs($this->usuario)->postJson(route('dias-no-laborables.generar-festivos'), ['anio' => 2026]);
        DiaNoLaborable::whereDate('fecha', '2026-11-16')->first()->delete();

        $response = $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.generar-festivos'), ['anio' => 2026])
            ->assertOk();

        $this->assertContains('2026-11-16', collect($response->json('data.omitidos'))->pluck('fecha'));
        $this->assertSoftDeleted('dias_no_laborables', ['fecha' => '2026-11-16']);
    }

    /** @test */
    public function generar_festivos_valida_anio(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.generar-festivos'), ['anio' => 1990])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['anio']);
    }

    // ─── show / update ────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_el_dia(): void
    {
        $dia = DiaNoLaborable::factory()->create(['fecha' => '2027-04-10']);

        $this->actingAs($this->usuario)
            ->getJson(route('dias-no-laborables.show', $dia))
            ->assertOk()
            ->assertJsonPath('data.fecha', '2027-04-10');
    }

    /** @test */
    public function update_modifica_el_dia(): void
    {
        $dia = DiaNoLaborable::factory()->create(['fecha' => '2027-04-10', 'nombre' => 'Original']);

        $this->actingAs($this->usuario)
            ->putJson(route('dias-no-laborables.update', $dia), ['nombre' => 'Editado', 'sede_id' => $this->sede->id])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Editado')
            ->assertJsonPath('data.sede_id', $this->sede->id);
    }

    /** @test */
    public function update_rechaza_fecha_que_choca_con_otro_dia(): void
    {
        DiaNoLaborable::factory()->create(['fecha' => '2027-04-11', 'sede_id' => null]);
        $dia = DiaNoLaborable::factory()->create(['fecha' => '2027-04-10', 'sede_id' => null]);

        $this->actingAs($this->usuario)
            ->putJson(route('dias-no-laborables.update', $dia), ['fecha' => '2027-04-11'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha']);
    }

    /** @test */
    public function update_permite_conservar_su_propia_fecha(): void
    {
        $dia = DiaNoLaborable::factory()->create(['fecha' => '2027-04-10', 'sede_id' => null]);

        $this->actingAs($this->usuario)
            ->putJson(route('dias-no-laborables.update', $dia), ['fecha' => '2027-04-10', 'status' => 0])
            ->assertOk()
            ->assertJsonPath('data.status', 0);
    }

    // ─── destroy / restore / forceDelete / trashed ────────────────────────────

    /** @test */
    public function destroy_restore_y_force_delete(): void
    {
        $dia = DiaNoLaborable::factory()->create(['fecha' => '2027-04-10']);

        $this->actingAs($this->usuario)->deleteJson(route('dias-no-laborables.destroy', $dia))->assertOk();
        $this->assertSoftDeleted('dias_no_laborables', ['id' => $dia->id]);

        $this->actingAs($this->usuario)
            ->getJson(route('dias-no-laborables.trashed'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->usuario)->postJson(route('dias-no-laborables.restore', $dia->id))->assertOk();
        $this->assertNotSoftDeleted('dias_no_laborables', ['id' => $dia->id]);

        $dia->delete();
        $this->actingAs($this->usuario)->deleteJson(route('dias-no-laborables.force-delete', $dia->id))->assertOk();
        $this->assertDatabaseMissing('dias_no_laborables', ['id' => $dia->id]);
    }

    /** @test */
    public function restore_rechaza_si_la_fecha_ya_se_registro_de_nuevo(): void
    {
        $eliminado = DiaNoLaborable::factory()->create(['fecha' => '2027-04-10', 'sede_id' => null]);
        $eliminado->delete();
        DiaNoLaborable::factory()->create(['fecha' => '2027-04-10', 'sede_id' => null]);

        $this->actingAs($this->usuario)
            ->postJson(route('dias-no-laborables.restore', $eliminado->id))
            ->assertUnprocessable();
    }

    /** @test */
    public function force_delete_retorna_404_si_no_esta_eliminado(): void
    {
        $dia = DiaNoLaborable::factory()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('dias-no-laborables.force-delete', $dia->id))
            ->assertNotFound();
    }

    // ─── filters / statistics ─────────────────────────────────────────────────

    /** @test */
    public function filters_y_statistics(): void
    {
        DiaNoLaborable::factory()->create(['fecha' => now()->addDays(5)->toDateString(), 'sede_id' => null]);
        DiaNoLaborable::factory()->create(['fecha' => now()->addDays(6)->toDateString(), 'sede_id' => $this->sede->id, 'tipo' => 'vacaciones']);

        $this->actingAs($this->usuario)
            ->getJson(route('dias-no-laborables.filters'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['tipos' => ['festivo', 'vacaciones', 'institucional', 'otro'], 'status']]);

        $this->actingAs($this->usuario)
            ->getJson(route('dias-no-laborables.statistics'))
            ->assertOk()
            ->assertJsonPath('data.totales.total', 2)
            ->assertJsonPath('data.alcance.generales', 1)
            ->assertJsonPath('data.alcance.por_sede', 1)
            ->assertJsonCount(2, 'data.proximos');
    }

    // ─── permisos ─────────────────────────────────────────────────────────────

    /** @test */
    public function usuario_sin_permisos_recibe_403(): void
    {
        $sinPermiso = User::factory()->create();
        $dia        = DiaNoLaborable::factory()->create();

        $this->actingAs($sinPermiso)->getJson(route('dias-no-laborables.index'))->assertForbidden();
        $this->actingAs($sinPermiso)->getJson(route('dias-no-laborables.calendario', ['anio' => 2027]))->assertForbidden();
        $this->actingAs($sinPermiso)->postJson(route('dias-no-laborables.store'), [])->assertForbidden();
        $this->actingAs($sinPermiso)->postJson(route('dias-no-laborables.generar-festivos'), ['anio' => 2027])->assertForbidden();
        $this->actingAs($sinPermiso)->putJson(route('dias-no-laborables.update', $dia), [])->assertForbidden();
        $this->actingAs($sinPermiso)->deleteJson(route('dias-no-laborables.destroy', $dia))->assertForbidden();
    }

    /** @test */
    public function solo_lectura_no_puede_crear(): void
    {
        $lector = User::factory()->create();
        $lector->givePermissionTo('co_diasNoLaborables');

        $this->actingAs($lector)->getJson(route('dias-no-laborables.index'))->assertOk();
        $this->actingAs($lector)
            ->postJson(route('dias-no-laborables.store'), ['fecha' => '2027-04-10', 'nombre' => 'X', 'tipo' => 'otro'])
            ->assertForbidden();
    }

    // ─── menú ─────────────────────────────────────────────────────────────────

    /** @test */
    public function menu_muestra_calendario_laboral_solo_con_permiso(): void
    {
        $opciones = fn (User $user) => collect($this->actingAs($user)->getJson('/api/menu')->assertOk()->json('data'))
            ->flatMap(fn ($modulo) => $modulo['children'] ?? [])
            ->pluck('id');

        $this->assertContains('configuracion-calendario-laboral', $opciones($this->usuario));
        $this->assertNotContains('configuracion-calendario-laboral', $opciones(User::factory()->create()));
    }

    /** @test */
    public function no_autenticado_recibe_401(): void
    {
        $this->getJson(route('dias-no-laborables.index'))->assertUnauthorized();
    }
}
