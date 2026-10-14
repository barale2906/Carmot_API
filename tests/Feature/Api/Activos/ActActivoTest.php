<?php

namespace Tests\Feature\Api\Activos;

use App\Models\Activos\ActActivo;
use App\Models\Activos\ActCategoria;
use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Activos fijos: registro, restricción por sede, traslados, cambios de estado e historial.
 */
class ActActivoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Sede $sede;
    private Sede $otraSede;
    private Sede $sedeAjena;
    private Area $taller;
    private ActCategoria $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permisos = ['act_activos', 'act_activoCrear', 'act_activoEditar', 'act_activoTrasladar', 'act_activoInactivar'];
        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->sede      = Sede::factory()->create();
        $this->otraSede  = Sede::factory()->create();
        $this->sedeAjena = Sede::factory()->create();

        $this->taller = Area::factory()->create(['nombre' => 'Taller de motos', 'status' => 1]);
        $this->taller->sedes()->attach($this->sede->id);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo($permisos);
        $this->usuario->sedes()->attach([$this->sede->id, $this->otraSede->id]);

        $this->categoria = ActCategoria::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'codigo'        => 'ACT-0001',
            'nombre'        => 'Compresor de aire',
            'categoria_id'  => $this->categoria->id,
            'sede_id'       => $this->sede->id,
            'area_id'       => $this->taller->id,
            'fecha_ingreso' => now()->subMonth()->toDateString(),
            'valor'         => 2500000,
        ], $overrides);
    }

    private function crearActivo(array $overrides = []): ActActivo
    {
        $id = $this->actingAs($this->usuario)->postJson(route('act-activos.store'), $this->payload($overrides))
            ->assertCreated()
            ->json('data.id');

        return ActActivo::find($id);
    }

    /** @test */
    public function registra_un_activo_con_su_movimiento_de_ingreso(): void
    {
        $this->actingAs($this->usuario)->postJson(route('act-activos.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.estado', 'operativo')
            ->assertJsonPath('data.area.nombre', 'Taller de motos')
            ->assertJsonPath('data.categoria.id', $this->categoria->id);

        $this->assertDatabaseHas('act_movimientos', ['tipo' => 'ingreso', 'sede_destino_id' => $this->sede->id, 'usuario_id' => $this->usuario->id]);
    }

    /** @test */
    public function valida_codigo_unico_area_de_la_sede_sede_asignada_y_que_no_nazca_de_baja(): void
    {
        $this->crearActivo();
        $areaDeOtraSede = Area::factory()->create();
        $areaDeOtraSede->sedes()->attach($this->otraSede->id);

        $this->actingAs($this->usuario)->postJson(route('act-activos.store'), $this->payload(['area_id' => $areaDeOtraSede->id, 'estado' => 'baja']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo', 'estado']);

        $this->actingAs($this->usuario)->postJson(route('act-activos.store'), $this->payload(['codigo' => 'ACT-0002', 'area_id' => $areaDeOtraSede->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('area_id');

        $this->actingAs($this->usuario)->postJson(route('act-activos.store'), $this->payload(['codigo' => 'ACT-0003', 'sede_id' => $this->sedeAjena->id, 'area_id' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sede_id');
    }

    /** @test */
    public function el_listado_solo_muestra_activos_de_las_sedes_asignadas_y_oculta_los_de_baja(): void
    {
        $this->crearActivo();
        ActActivo::factory()->create(['sede_id' => $this->sedeAjena->id]);
        ActActivo::factory()->create(['sede_id' => $this->sede->id, 'estado' => 'baja']);

        $this->actingAs($this->usuario)->getJson(route('act-activos.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo', 'ACT-0001');

        $this->actingAs($this->usuario)->getJson(route('act-activos.index', ['estado' => 'baja']))
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->usuario)->getJson(route('act-activos.index', ['search' => 'compres']))
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function no_permite_ver_un_activo_de_una_sede_no_asignada(): void
    {
        $ajeno = ActActivo::factory()->create(['sede_id' => $this->sedeAjena->id]);

        $this->actingAs($this->usuario)->getJson(route('act-activos.show', $ajeno))->assertForbidden();
    }

    /** @test */
    public function la_edicion_no_cambia_ubicacion_ni_estado(): void
    {
        $activo = $this->crearActivo();

        $this->actingAs($this->usuario)->putJson(route('act-activos.update', $activo), [
            'nombre' => 'Compresor 50 L', 'sede_id' => $this->otraSede->id, 'estado' => 'baja',
        ])->assertOk()->assertJsonPath('data.nombre', 'Compresor 50 L');

        $this->assertDatabaseHas('act_activos', ['id' => $activo->id, 'sede_id' => $this->sede->id, 'estado' => 'operativo']);
    }

    /** @test */
    public function traslada_el_activo_y_deja_historial(): void
    {
        $activo = $this->crearActivo();

        $this->actingAs($this->usuario)->postJson(route('act-activos.trasladar', $activo), [
            'sede_id' => $this->otraSede->id, 'area_id' => null, 'fecha' => now()->toDateString(), 'motivo' => 'Préstamo a la sede norte',
        ])->assertOk()->assertJsonPath('data.sede.id', $this->otraSede->id)->assertJsonPath('data.area', null);

        $this->actingAs($this->usuario)->getJson(route('act-activos.movimientos', $activo->fresh()))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.tipo', 'traslado')
            ->assertJsonPath('data.0.origen', $this->sede->nombre . ' · Taller de motos');
    }

    /** @test */
    public function el_traslado_valida_destino_distinto_y_sede_asignada(): void
    {
        $activo = $this->crearActivo();
        $base = ['fecha' => now()->toDateString(), 'motivo' => 'Reubicación'];

        $this->actingAs($this->usuario)->postJson(route('act-activos.trasladar', $activo), $base + ['sede_id' => $this->sede->id, 'area_id' => $this->taller->id])
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');

        $this->actingAs($this->usuario)->postJson(route('act-activos.trasladar', $activo), $base + ['sede_id' => $this->sedeAjena->id])
            ->assertUnprocessable()->assertJsonValidationErrors('sede_id');
    }

    /** @test */
    public function la_baja_es_definitiva(): void
    {
        $activo = $this->crearActivo();
        $base = ['fecha' => now()->toDateString(), 'motivo' => 'Daño irreparable del motor'];

        $this->actingAs($this->usuario)->postJson(route('act-activos.estado', $activo), $base + ['estado' => 'baja'])
            ->assertOk()->assertJsonPath('data.estado', 'baja');
        $this->assertDatabaseHas('act_movimientos', ['activo_id' => $activo->id, 'tipo' => 'cambio_estado', 'estado_anterior' => 'operativo', 'estado_nuevo' => 'baja']);

        $this->actingAs($this->usuario)->postJson(route('act-activos.estado', $activo), $base + ['estado' => 'operativo'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');

        $this->actingAs($this->usuario)->postJson(route('act-activos.trasladar', $activo), $base + ['sede_id' => $this->otraSede->id])
            ->assertUnprocessable()->assertJsonValidationErrors('sede_id');
    }

    /** @test */
    public function estadisticas_por_estado_y_area_sin_los_de_baja(): void
    {
        $this->crearActivo();
        $this->crearActivo(['codigo' => 'ACT-0002', 'valor' => 500000, 'estado' => 'mantenimiento']);
        ActActivo::factory()->create(['sede_id' => $this->sede->id, 'estado' => 'baja']);

        $this->actingAs($this->usuario)->getJson(route('act-activos.statistics', ['sede_id' => $this->sede->id]))
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.valor_total', 3000000)
            ->assertJsonPath('data.de_baja', 1)
            ->assertJsonPath('data.por_estado.mantenimiento', 1)
            ->assertJsonPath('data.por_area.0.area', 'Taller de motos');
    }

    /** @test */
    public function filtros_traen_sedes_asignadas_con_sus_areas(): void
    {
        $sedes = collect($this->actingAs($this->usuario)->getJson(route('act-activos.filters'))
            ->assertOk()
            ->json('data.sedes'))->keyBy('id');

        $this->assertEqualsCanonicalizing([$this->sede->id, $this->otraSede->id], $sedes->keys()->all());
        $this->assertSame('Taller de motos', $sedes[$this->sede->id]['areas'][0]['nombre']);
    }

    /** @test */
    public function permisos_por_accion(): void
    {
        $lector = User::factory()->create();
        $lector->givePermissionTo('act_activos');
        $lector->sedes()->attach($this->sede->id);
        $activo = $this->crearActivo();

        $this->actingAs($lector)->getJson(route('act-activos.index'))->assertOk();
        $this->actingAs($lector)->postJson(route('act-activos.store'), $this->payload(['codigo' => 'X']))->assertForbidden();
        $this->actingAs($lector)->postJson(route('act-activos.trasladar', $activo), [])->assertForbidden();
        $this->actingAs($lector)->deleteJson(route('act-activos.destroy', $activo))->assertForbidden();
    }
}
