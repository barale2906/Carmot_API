<?php

namespace Tests\Feature\Api\Configuracion;

use App\Models\Configuracion\Banco;
use App\Models\Configuracion\CuentaBancaria;
use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD de cuentas bancarias del instituto.
 */
class CuentaBancariaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sede $sede;

    private Banco $banco;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['co_cuentasBancarias', 'co_cuentasBancariasCrear', 'co_cuentasBancariasEditar', 'co_cuentasBancariasInactivar', 'fin_reciboPagoCrear'] as $nombre) {
            Permission::create(['name' => $nombre, 'descripcion' => $nombre]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'co_cuentasBancarias', 'co_cuentasBancariasCrear', 'co_cuentasBancariasEditar', 'co_cuentasBancariasInactivar',
        ]);

        $this->sede = Sede::factory()->create();
        $this->banco = Banco::factory()->create(['nombre' => 'Bancolombia']);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'sedes' => [$this->sede->id],
            'banco_id' => $this->banco->id,
            'tipo_cuenta' => 'ahorros',
            'numero_cuenta' => '123-456789-00',
            'titular' => 'Centro de Capacitación CARMOT',
        ], $override);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_con_sede_y_banco(): void
    {
        CuentaBancaria::factory()->count(3)->create();

        $this->actingAs($this->usuario)
            ->getJson(route('cuentas-bancarias.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'tipo_cuenta', 'tipo_cuenta_text', 'numero_cuenta', 'etiqueta', 'status_text', 'alcance', 'sedes' => [['id', 'nombre']], 'banco' => ['id', 'nombre']]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    /** @test */
    public function index_filtra_por_sede_y_busca_por_nombre_de_banco(): void
    {
        CuentaBancaria::factory()->paraSedes($this->sede)->create(['banco_id' => $this->banco->id]);
        CuentaBancaria::factory()->create(['banco_id' => Banco::factory()->create(['nombre' => 'Davivienda'])->id]);

        $this->actingAs($this->usuario)
            ->getJson(route('cuentas-bancarias.index', ['sede_id' => $this->sede->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->usuario)
            ->getJson(route('cuentas-bancarias.index', ['search' => 'davivienda']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.banco.nombre', 'Davivienda');
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('cuentas-bancarias.index'))
            ->assertForbidden();
    }

    // ─── activas ──────────────────────────────────────────────────────────────

    /** @test */
    public function activas_retorna_solo_las_cuentas_activas_de_la_sede(): void
    {
        $activa = CuentaBancaria::factory()->paraSedes($this->sede)->create();
        CuentaBancaria::factory()->paraSedes($this->sede)->inactiva()->create();
        CuentaBancaria::factory()->create(); // otra sede
        CuentaBancaria::factory()->paraSedes($this->sede)->create()->delete();

        $cajero = User::factory()->create();
        $cajero->givePermissionTo('fin_reciboPagoCrear');

        $this->actingAs($cajero)
            ->getJson(route('cuentas-bancarias.activas', ['sede_id' => $this->sede->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activa->id);
    }

    /** @test */
    public function activas_incluye_cuentas_de_varias_sedes_y_de_la_ciudad(): void
    {
        $tunja = Poblacion::factory()->create(['nombre' => 'Tunja']);
        $duitama = Poblacion::factory()->create(['nombre' => 'Duitama']);
        $tunjaNorte = Sede::factory()->create(['poblacion_id' => $tunja->id]);
        $tunjaSur = Sede::factory()->create(['poblacion_id' => $tunja->id]);
        $duitamaCentro = Sede::factory()->create(['poblacion_id' => $duitama->id]);

        $deCiudad = CuentaBancaria::factory()->paraPoblacion($tunja->id)->create();
        $compartida = CuentaBancaria::factory()->paraSedes([$tunjaSur, $duitamaCentro])->create();
        $soloDuitama = CuentaBancaria::factory()->paraSedes($duitamaCentro)->create();

        $ids = fn (Sede $sede) => collect($this->actingAs($this->usuario)
            ->getJson(route('cuentas-bancarias.activas', ['sede_id' => $sede->id]))
            ->assertOk()
            ->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$deCiudad->id], $ids($tunjaNorte));
        $this->assertSame(collect([$deCiudad->id, $compartida->id])->sort()->values()->all(), $ids($tunjaSur));
        $this->assertSame(collect([$compartida->id, $soloDuitama->id])->sort()->values()->all(), $ids($duitamaCentro));
    }

    /** @test */
    public function activas_exige_sede(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('cuentas-bancarias.activas'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sede_id']);
    }

    /** @test */
    public function activas_deniega_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('cuentas-bancarias.activas', ['sede_id' => $this->sede->id]))
            ->assertForbidden();
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_cuenta_bancaria(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('cuentas-bancarias.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.numero_cuenta', '123-456789-00')
            ->assertJsonPath('data.etiqueta', 'Bancolombia · Ahorros · 123-456789-00')
            ->assertJsonPath('data.status', 1);

        $cuenta = CuentaBancaria::where('numero_cuenta', '123-456789-00')->firstOrFail();
        $this->assertSame([$this->sede->id], $cuenta->sedes()->pluck('sedes.id')->all());
    }

    /** @test */
    public function store_crea_cuenta_para_varias_sedes_y_una_ciudad(): void
    {
        $otra = Sede::factory()->create();
        $ciudad = Poblacion::factory()->create(['nombre' => 'Tunja']);

        $this->actingAs($this->usuario)
            ->postJson(route('cuentas-bancarias.store'), $this->payload([
                'sedes' => [$this->sede->id, $otra->id],
                'poblacion_id' => $ciudad->id,
            ]))
            ->assertCreated()
            ->assertJsonCount(2, 'data.sedes')
            ->assertJsonPath('data.poblacion.nombre', 'Tunja')
            ->assertJsonPath('data.alcance', fn ($a) => str_starts_with($a, 'Todas las sedes de Tunja + '));
    }

    /** @test */
    public function store_exige_al_menos_una_sede_o_ciudad(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('cuentas-bancarias.store'), $this->payload(['sedes' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sedes']);
    }

    /** @test */
    public function store_valida_campos_obligatorios_y_tipo(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('cuentas-bancarias.store'), ['tipo_cuenta' => 'cdt'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sedes', 'banco_id', 'tipo_cuenta', 'numero_cuenta']);
    }

    /** @test */
    public function store_rechaza_numero_duplicado_en_el_mismo_banco(): void
    {
        CuentaBancaria::factory()->create(['banco_id' => $this->banco->id, 'numero_cuenta' => '123-456789-00']);

        $this->actingAs($this->usuario)
            ->postJson(route('cuentas-bancarias.store'), $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['numero_cuenta']);

        // El mismo número en otro banco sí se permite
        $this->actingAs($this->usuario)
            ->postJson(route('cuentas-bancarias.store'), $this->payload(['banco_id' => Banco::factory()->create()->id]))
            ->assertCreated();
    }

    /** @test */
    public function store_deniega_sin_permiso(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('co_cuentasBancarias');

        $this->actingAs($usuario)
            ->postJson(route('cuentas-bancarias.store'), $this->payload())
            ->assertForbidden();
    }

    // ─── show / update ────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_la_cuenta(): void
    {
        $cuenta = CuentaBancaria::factory()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('cuentas-bancarias.show', $cuenta))
            ->assertOk()
            ->assertJsonPath('data.id', $cuenta->id)
            ->assertJsonPath('data.medios_pago_count', 0);
    }

    /** @test */
    public function update_modifica_la_cuenta_e_ignora_su_propio_numero(): void
    {
        $cuenta = CuentaBancaria::factory()->create(['banco_id' => $this->banco->id, 'numero_cuenta' => '111']);

        $this->actingAs($this->usuario)
            ->putJson(route('cuentas-bancarias.update', $cuenta), ['numero_cuenta' => '111', 'tipo_cuenta' => 'corriente', 'status' => 0])
            ->assertOk()
            ->assertJsonPath('data.tipo_cuenta', 'corriente')
            ->assertJsonPath('data.status', 0);
    }

    /** @test */
    public function update_reemplaza_sedes_y_no_permite_dejarla_sin_alcance(): void
    {
        $cuenta = CuentaBancaria::factory()->paraSedes($this->sede)->create();
        $nueva = Sede::factory()->create();

        $this->actingAs($this->usuario)
            ->putJson(route('cuentas-bancarias.update', $cuenta), ['sedes' => [$nueva->id]])
            ->assertOk()
            ->assertJsonPath('data.sede_ids', [$nueva->id]);

        $this->actingAs($this->usuario)
            ->putJson(route('cuentas-bancarias.update', $cuenta), ['sedes' => [], 'poblacion_id' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sedes']);

        $this->assertSame([$nueva->id], $cuenta->sedes()->pluck('sedes.id')->all());
    }

    /** @test */
    public function update_deniega_sin_permiso(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('co_cuentasBancarias');

        $this->actingAs($usuario)
            ->putJson(route('cuentas-bancarias.update', CuentaBancaria::factory()->create()), ['titular' => 'X'])
            ->assertForbidden();
    }

    // ─── destroy / restore / forceDelete / trashed ───────────────────────────

    /** @test */
    public function destroy_restore_y_force_delete(): void
    {
        $cuenta = CuentaBancaria::factory()->create();

        $this->actingAs($this->usuario)->deleteJson(route('cuentas-bancarias.destroy', $cuenta))->assertOk();
        $this->assertSoftDeleted('cuentas_bancarias', ['id' => $cuenta->id]);

        $this->actingAs($this->usuario)
            ->getJson(route('cuentas-bancarias.trashed'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->usuario)->postJson(route('cuentas-bancarias.restore', $cuenta->id))->assertOk();
        $this->assertNotSoftDeleted('cuentas_bancarias', ['id' => $cuenta->id]);

        $cuenta->delete();
        $this->actingAs($this->usuario)->deleteJson(route('cuentas-bancarias.force-delete', $cuenta->id))->assertOk();
        $this->assertDatabaseMissing('cuentas_bancarias', ['id' => $cuenta->id]);
    }

    /** @test */
    public function destroy_no_permite_eliminar_cuenta_con_pagos(): void
    {
        $cuenta = CuentaBancaria::factory()->paraSedes($this->sede)->create();
        ReciboPagoMedioPago::create([
            'recibo_pago_id' => ReciboPago::factory()->pendienteAprobacion()->create(['sede_id' => $this->sede->id])->id,
            'medio_pago' => 'transferencia',
            'valor' => 1000,
            'cuenta_bancaria_id' => $cuenta->id,
        ]);

        $this->actingAs($this->usuario)
            ->deleteJson(route('cuentas-bancarias.destroy', $cuenta))
            ->assertUnprocessable();

        $this->assertNotSoftDeleted('cuentas_bancarias', ['id' => $cuenta->id]);
    }

    /** @test */
    public function destroy_deniega_sin_permiso(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(['co_cuentasBancarias', 'co_cuentasBancariasEditar']);

        $this->actingAs($usuario)
            ->deleteJson(route('cuentas-bancarias.destroy', CuentaBancaria::factory()->create()))
            ->assertForbidden();
    }

    // ─── menú ─────────────────────────────────────────────────────────────────

    /** @test */
    public function menu_muestra_cuentas_bancarias_solo_con_permiso(): void
    {
        $opciones = fn (User $user) => collect($this->actingAs($user)->getJson('/api/menu')->assertOk()->json('data'))
            ->flatMap(fn ($modulo) => $modulo['children'] ?? [])
            ->pluck('id');

        $this->assertContains('configuracion-cuentas-bancarias', $opciones($this->usuario));
        $this->assertNotContains('configuracion-cuentas-bancarias', $opciones(User::factory()->create()));
    }

    // ─── filters / statistics ────────────────────────────────────────────────

    /** @test */
    public function filters_y_statistics_responden(): void
    {
        CuentaBancaria::factory()->paraSedes($this->sede)->create();
        CuentaBancaria::factory()->paraSedes($this->sede)->inactiva()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('cuentas-bancarias.filters'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['status', 'tipos_cuenta', 'sedes', 'bancos']]);

        $this->actingAs($this->usuario)
            ->getJson(route('cuentas-bancarias.statistics'))
            ->assertOk()
            ->assertJsonPath('data.totales.activas', 1)
            ->assertJsonPath('data.totales.inactivas', 1);
    }
}
