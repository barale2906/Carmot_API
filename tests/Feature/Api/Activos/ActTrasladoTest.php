<?php

namespace Tests\Feature\Api\Activos;

use App\Models\Activos\ActActivo;
use App\Models\Activos\ActMovimiento;
use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Traslados de activos en dos pasos: envío desde el origen y recepción registrada en el destino.
 */
class ActTrasladoTest extends TestCase
{
    use RefreshDatabase;

    private User $origen;
    private User $destino;
    private Sede $sedeOrigen;
    private Sede $sedeDestino;
    private Area $bodega;
    private ActActivo $activo;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['act_activos', 'act_activoTrasladar', 'act_activoRecibir'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->sedeOrigen  = Sede::factory()->create();
        $this->sedeDestino = Sede::factory()->create();
        $this->bodega = Area::factory()->create(['nombre' => 'Bodega', 'status' => 1]);
        $this->bodega->sedes()->attach($this->sedeDestino->id);

        // Quien envía tiene ambas sedes (el destino debe estar asignado); quien recibe, solo la de destino.
        $this->origen = User::factory()->create();
        $this->origen->givePermissionTo(['act_activos', 'act_activoTrasladar']);
        $this->origen->sedes()->attach([$this->sedeOrigen->id, $this->sedeDestino->id]);

        $this->destino = User::factory()->create();
        $this->destino->givePermissionTo('act_activoRecibir');
        $this->destino->sedes()->attach($this->sedeDestino->id);

        $this->activo = ActActivo::factory()->create(['sede_id' => $this->sedeOrigen->id, 'nombre' => 'Soldador MIG']);
    }

    private function enviar(array $overrides = []): ActMovimiento
    {
        $this->actingAs($this->origen)->postJson(route('act-activos.trasladar', $this->activo), array_merge([
            'sede_id' => $this->sedeDestino->id,
            'area_id' => $this->bodega->id,
            'fecha'   => now()->subDays(2)->toDateString(),
            'motivo'  => 'Se necesita en la sede de destino',
        ], $overrides))->assertOk();

        return $this->activo->trasladoPendiente()->firstOrFail();
    }

    /** @test */
    public function el_destino_ve_el_traslado_pendiente_entrante(): void
    {
        $traslado = $this->enviar();

        $this->actingAs($this->destino)->getJson(route('act-traslados.index', ['estado' => 'pendiente', 'direccion' => 'entrantes']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $traslado->id)
            ->assertJsonPath('data.0.activo.id', $this->activo->id)
            ->assertJsonPath('data.0.activo.nombre', 'Soldador MIG')
            ->assertJsonPath('data.0.estado_traslado_texto', 'Pendiente de recepción');

        $this->actingAs($this->destino)->getJson(route('act-traslados.index', ['direccion' => 'salientes']))
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function el_listado_no_muestra_traslados_de_sedes_ajenas(): void
    {
        $this->enviar();
        $ajeno = User::factory()->create();
        $ajeno->givePermissionTo('act_activoRecibir');
        $ajeno->sedes()->attach(Sede::factory()->create()->id);

        $this->actingAs($ajeno)->getJson(route('act-traslados.index'))->assertOk()->assertJsonCount(0, 'data');
    }

    /** @test */
    public function al_recibir_el_activo_queda_en_el_destino_con_el_responsable_propuesto(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'profesor']);
        $profesor = User::factory()->create();
        $profesor->assignRole('profesor');
        $traslado = $this->enviar(['responsable_id' => $profesor->id]);

        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), [
            'fecha_recepcion'         => now()->toDateString(),
            'observaciones_recepcion' => 'Llegó con la careta rayada',
        ])->assertOk()
            ->assertJsonPath('data.estado_traslado', 'recibido')
            ->assertJsonPath('data.recibido_por.id', $this->destino->id)
            ->assertJsonPath('data.observaciones_recepcion', 'Llegó con la careta rayada');

        $this->assertDatabaseHas('act_activos', [
            'id' => $this->activo->id, 'sede_id' => $this->sedeDestino->id, 'area_id' => $this->bodega->id, 'responsable_id' => $profesor->id,
        ]);
        $this->assertFalse($this->activo->fresh()->estaEnTransito());
    }

    /** @test */
    public function al_recibir_se_puede_asignar_otro_responsable_o_conservar_el_actual(): void
    {
        $actual = User::factory()->create();
        $this->activo->update(['responsable_id' => $actual->id]);

        $traslado = $this->enviar();
        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString()])
            ->assertOk();
        $this->assertSame($actual->id, $this->activo->fresh()->responsable_id);

        $this->activo->update(['sede_id' => $this->sedeOrigen->id, 'area_id' => null]);
        $traslado = $this->enviar();
        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString(), 'responsable_id' => null])
            ->assertOk();
        $this->assertNull($this->activo->fresh()->responsable_id);
    }

    /** @test */
    public function la_recepcion_valida_fechas_y_que_siga_pendiente(): void
    {
        $traslado = $this->enviar();

        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->subDays(5)->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_recepcion');

        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->addDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_recepcion');

        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), [])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_recepcion');

        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString()])
            ->assertOk();

        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('traslado');
    }

    /** @test */
    public function solo_recibe_quien_tiene_asignada_la_sede_de_destino(): void
    {
        $traslado = $this->enviar();
        $soloOrigen = User::factory()->create();
        $soloOrigen->givePermissionTo('act_activoRecibir');
        $soloOrigen->sedes()->attach($this->sedeOrigen->id);

        $this->actingAs($soloOrigen)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString()])
            ->assertForbidden();
        $this->assertTrue($this->activo->fresh()->estaEnTransito());
    }

    /** @test */
    public function quien_envio_el_traslado_no_puede_recibirlo(): void
    {
        $this->origen->givePermissionTo('act_activoRecibir');
        $traslado = $this->enviar();

        $this->actingAs($this->origen)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString()])
            ->assertForbidden()
            ->assertJsonPath('message', 'La recepción la debe registrar una persona distinta a quien envió el traslado.');
        $this->assertTrue($this->activo->fresh()->estaEnTransito());

        // Otra persona con la sede de destino sí lo recibe
        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString()])
            ->assertOk();
    }

    /** @test */
    public function el_superusuario_puede_recibir_un_traslado_que_el_mismo_envio(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superusuario']);
        $this->origen->assignRole('superusuario');
        $this->origen->givePermissionTo('act_activoRecibir');
        $traslado = $this->enviar();

        $this->actingAs($this->origen)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.recibido_por.id', $this->origen->id);
        $this->assertDatabaseHas('act_activos', ['id' => $this->activo->id, 'sede_id' => $this->sedeDestino->id]);
    }

    /** @test */
    public function el_origen_puede_cancelar_un_traslado_pendiente(): void
    {
        $traslado = $this->enviar();

        $this->actingAs($this->origen)->postJson(route('act-traslados.cancelar', $traslado), ['motivo' => 'Ya no se necesita'])
            ->assertOk()
            ->assertJsonPath('data.estado_traslado', 'cancelado')
            ->assertJsonPath('data.cancelado_por.id', $this->origen->id);

        $this->assertDatabaseHas('act_activos', ['id' => $this->activo->id, 'sede_id' => $this->sedeOrigen->id]);
        $this->assertFalse($this->activo->fresh()->estaEnTransito());

        // Cancelado ya no se recibe ni se vuelve a cancelar
        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('traslado');
        $this->actingAs($this->origen)->postJson(route('act-traslados.cancelar', $traslado), ['motivo' => 'Otra vez'])
            ->assertUnprocessable()->assertJsonValidationErrors('traslado');
    }

    /** @test */
    public function la_cancelacion_exige_motivo_y_la_sede_de_origen(): void
    {
        $traslado = $this->enviar();

        $this->actingAs($this->origen)->postJson(route('act-traslados.cancelar', $traslado), [])
            ->assertUnprocessable()->assertJsonValidationErrors('motivo');

        $soloDestino = User::factory()->create();
        $soloDestino->givePermissionTo('act_activoTrasladar');
        $soloDestino->sedes()->attach($this->sedeDestino->id);

        $this->actingAs($soloDestino)->postJson(route('act-traslados.cancelar', $traslado), ['motivo' => 'No lo necesitamos'])
            ->assertForbidden();
    }

    /** @test */
    public function permisos_por_accion(): void
    {
        $traslado = $this->enviar();
        $sinPermisos = User::factory()->create();
        $sinPermisos->sedes()->attach([$this->sedeOrigen->id, $this->sedeDestino->id]);

        $this->actingAs($sinPermisos)->getJson(route('act-traslados.index'))->assertForbidden();
        $this->actingAs($sinPermisos)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString()])->assertForbidden();
        $this->actingAs($this->destino)->postJson(route('act-traslados.cancelar', $traslado), ['motivo' => 'Sin permiso'])->assertForbidden();
    }

    /** @test */
    public function quien_solo_recibe_puede_consultar_el_catalogo_de_filtros(): void
    {
        $this->actingAs($this->destino)->getJson(route('act-activos.filters'))
            ->assertOk()
            ->assertJsonPath('data.sedes.0.id', $this->sedeDestino->id);
        $this->actingAs($this->destino)->getJson(route('act-activos.index'))->assertForbidden();
    }

    /** @test */
    public function un_movimiento_que_no_es_traslado_responde_404(): void
    {
        $ingreso = $this->activo->movimientos()->create(['tipo' => 'ingreso', 'sede_destino_id' => $this->sedeOrigen->id, 'fecha' => now()->toDateString()]);

        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $ingreso), ['fecha_recepcion' => now()->toDateString()])
            ->assertNotFound();
    }

    /** @test */
    public function no_se_recibe_el_traslado_de_un_activo_eliminado(): void
    {
        $traslado = $this->enviar();
        $this->activo->delete();

        $this->actingAs($this->destino)->getJson(route('act-traslados.index'))->assertJsonCount(0, 'data');
        $this->actingAs($this->destino)->postJson(route('act-traslados.recibir', $traslado), ['fecha_recepcion' => now()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('traslado');
    }
}
