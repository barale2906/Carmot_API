<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Configuracion\Poblacion;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Inventarios\InvPrecioProducto;
use App\Models\Inventarios\InvProducto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD y flujo de aprobación de listas de precios de inventario.
 */
class InvListaPrecioTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Poblacion $poblacion;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_listas',         'descripcion' => 'ver listas']);
        Permission::create(['name' => 'inv_listasCrear',    'descripcion' => 'crear listas']);
        Permission::create(['name' => 'inv_listasEditar',   'descripcion' => 'editar listas']);
        Permission::create(['name' => 'inv_listasAprobar',  'descripcion' => 'aprobar listas']);
        Permission::create(['name' => 'inv_listasInactivar','descripcion' => 'inactivar listas']);
        Permission::create(['name' => 'inv_listasClonar',   'descripcion' => 'clonar listas']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'inv_listas', 'inv_listasCrear', 'inv_listasEditar',
            'inv_listasAprobar', 'inv_listasInactivar', 'inv_listasClonar',
        ]);

        $this->poblacion = Poblacion::factory()->create();
    }

    private function listaInv(array $attrs = []): LpListaPrecio
    {
        return LpListaPrecio::factory()->create(array_merge([
            'origen' => 0,
            'status' => LpListaPrecio::STATUS_EN_PROCESO,
        ], $attrs));
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_solo_listas_de_inventario(): void
    {
        $this->listaInv();
        LpListaPrecio::factory()->create(['origen' => 1]); // académica — no debe aparecer

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-listas-precios.index'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function index_filtra_por_status(): void
    {
        $this->listaInv(['status' => LpListaPrecio::STATUS_EN_PROCESO]);
        $this->listaInv(['status' => LpListaPrecio::STATUS_ACTIVA]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-listas-precios.index', ['status' => LpListaPrecio::STATUS_EN_PROCESO]))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(LpListaPrecio::STATUS_EN_PROCESO, $response->json('data.0.status'));
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('inv-listas-precios.index'))
            ->assertForbidden();
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_lista_con_origen_cero_forzado(): void
    {
        $payload = [
            'nombre'       => 'Lista Uniformes 2026',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin'    => '2026-12-31',
            'descripcion'  => 'Precios del segundo semestre',
            'poblaciones'  => [$this->poblacion->id],
        ];

        $response = $this->actingAs($this->usuario)
            ->postJson(route('inv-listas-precios.store'), $payload)
            ->assertCreated()
            ->assertJsonFragment(['nombre' => 'Lista Uniformes 2026']);

        $this->assertDatabaseHas('lp_listas_precios', [
            'id'     => $response->json('data.id'),
            'origen' => 0,
            'status' => LpListaPrecio::STATUS_EN_PROCESO,
        ]);

        // La población debe estar asociada
        $lista = LpListaPrecio::find($response->json('data.id'));
        $this->assertTrue($lista->poblaciones->contains($this->poblacion->id));
    }

    /** @test */
    public function store_falla_sin_poblaciones(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-listas-precios.store'), [
                'nombre'       => 'Sin Poblaciones',
                'fecha_inicio' => '2026-09-01',
                'fecha_fin'    => '2026-12-31',
                'poblaciones'  => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['poblaciones']);
    }

    /** @test */
    public function store_falla_si_fecha_fin_es_anterior_a_inicio(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-listas-precios.store'), [
                'nombre'       => 'Fechas invertidas',
                'fecha_inicio' => '2026-12-01',
                'fecha_fin'    => '2026-09-01',
                'poblaciones'  => [$this->poblacion->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_fin']);
    }

    /** @test */
    public function store_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-listas-precios.store'), [])
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_lista_con_precios_y_poblaciones(): void
    {
        $lista    = $this->listaInv();
        $lista->poblaciones()->attach($this->poblacion->id);
        $producto = InvProducto::factory()->create(['tipo' => 'simple']);
        InvPrecioProducto::create(['lista_precio_id' => $lista->id, 'producto_id' => $producto->id, 'precio' => 50000]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-listas-precios.show', $lista))
            ->assertOk();

        $this->assertCount(1, $response->json('data.precios'));
        $this->assertCount(1, $response->json('data.poblaciones'));
    }

    /** @test */
    public function show_retorna_404_para_lista_academica(): void
    {
        $listaAcademica = LpListaPrecio::factory()->create(['origen' => 1]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-listas-precios.show', $listaAcademica))
            ->assertNotFound();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_modifica_nombre_y_poblaciones(): void
    {
        $lista = $this->listaInv();
        $nuevaPoblacion = Poblacion::factory()->create();

        $this->actingAs($this->usuario)
            ->putJson(route('inv-listas-precios.update', $lista), [
                'nombre'      => 'Lista Modificada',
                'poblaciones' => [$nuevaPoblacion->id],
            ])
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Lista Modificada']);

        $lista->refresh();
        $this->assertFalse($lista->poblaciones->contains($this->poblacion->id));
        $this->assertTrue($lista->poblaciones->contains($nuevaPoblacion->id));
    }

    /** @test */
    public function update_rechaza_lista_aprobada(): void
    {
        $lista = $this->listaInv(['status' => LpListaPrecio::STATUS_APROBADA]);

        $this->actingAs($this->usuario)
            ->putJson(route('inv-listas-precios.update', $lista), ['nombre' => 'No permitido'])
            ->assertUnprocessable();
    }

    /** @test */
    public function update_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $lista = $this->listaInv();

        $this->actingAs($sinPermiso)
            ->putJson(route('inv-listas-precios.update', $lista), ['nombre' => 'X'])
            ->assertForbidden();
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    /** @test */
    public function destroy_elimina_logicamente_la_lista(): void
    {
        $lista = $this->listaInv();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-listas-precios.destroy', $lista))
            ->assertOk()
            ->assertJsonFragment(['message' => 'Lista de precios eliminada exitosamente.']);

        $this->assertSoftDeleted('lp_listas_precios', ['id' => $lista->id]);
    }

    // ─── aprobar ──────────────────────────────────────────────────────────────

    /** @test */
    public function aprobar_cambia_estado_a_aprobada(): void
    {
        $lista = $this->listaInv(['status' => LpListaPrecio::STATUS_EN_PROCESO]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-listas-precios.aprobar', $lista))
            ->assertOk()
            ->assertJsonFragment(['status' => LpListaPrecio::STATUS_APROBADA]);

        $this->assertDatabaseHas('lp_listas_precios', ['id' => $lista->id, 'status' => LpListaPrecio::STATUS_APROBADA]);
    }

    /** @test */
    public function aprobar_rechaza_lista_ya_activa(): void
    {
        $lista = $this->listaInv(['status' => LpListaPrecio::STATUS_ACTIVA]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-listas-precios.aprobar', $lista))
            ->assertUnprocessable();
    }

    // ─── activar ──────────────────────────────────────────────────────────────

    /** @test */
    public function activar_cambia_estado_a_activa(): void
    {
        $lista = $this->listaInv(['status' => LpListaPrecio::STATUS_APROBADA]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-listas-precios.activar', $lista))
            ->assertOk()
            ->assertJsonFragment(['status' => LpListaPrecio::STATUS_ACTIVA]);
    }

    /** @test */
    public function activar_rechaza_lista_en_proceso(): void
    {
        $lista = $this->listaInv(['status' => LpListaPrecio::STATUS_EN_PROCESO]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-listas-precios.activar', $lista))
            ->assertUnprocessable();
    }

    // ─── inactivar ────────────────────────────────────────────────────────────

    /** @test */
    public function inactivar_cambia_estado_a_inactiva(): void
    {
        $lista = $this->listaInv(['status' => LpListaPrecio::STATUS_ACTIVA]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-listas-precios.inactivar', $lista))
            ->assertOk()
            ->assertJsonFragment(['status' => LpListaPrecio::STATUS_INACTIVA]);
    }

    // ─── clonar ───────────────────────────────────────────────────────────────

    /** @test */
    public function clonar_duplica_lista_y_precios(): void
    {
        $lista    = $this->listaInv();
        $lista->poblaciones()->attach($this->poblacion->id);
        $producto = InvProducto::factory()->create(['tipo' => 'simple']);
        InvPrecioProducto::create(['lista_precio_id' => $lista->id, 'producto_id' => $producto->id, 'precio' => 75000]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('inv-listas-precios.clonar', $lista), [
                'nombre'       => 'Lista Clonada',
                'fecha_inicio' => '2027-01-01',
                'fecha_fin'    => '2027-06-30',
            ])
            ->assertCreated()
            ->assertJsonFragment(['precios_copiados' => 1]);

        $nuevaId = $response->json('data.id');

        $this->assertDatabaseHas('lp_listas_precios', [
            'id'     => $nuevaId,
            'origen' => 0,
            'status' => LpListaPrecio::STATUS_EN_PROCESO,
        ]);

        $this->assertDatabaseHas('inv_precios_producto', [
            'lista_precio_id' => $nuevaId,
            'producto_id'     => $producto->id,
            'precio'          => 75000,
        ]);
    }

    /** @test */
    public function clonar_sin_precios_cuando_copiar_precios_false(): void
    {
        $lista    = $this->listaInv();
        $producto = InvProducto::factory()->create(['tipo' => 'simple']);
        InvPrecioProducto::create(['lista_precio_id' => $lista->id, 'producto_id' => $producto->id, 'precio' => 75000]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('inv-listas-precios.clonar', $lista), [
                'nombre'         => 'Clon vacío',
                'fecha_inicio'   => '2027-01-01',
                'fecha_fin'      => '2027-06-30',
                'copiar_precios' => false,
            ])
            ->assertCreated()
            ->assertJsonFragment(['precios_copiados' => 0]);

        $this->assertDatabaseMissing('inv_precios_producto', [
            'lista_precio_id' => $response->json('data.id'),
        ]);
    }

    /** @test */
    public function clonar_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $lista = $this->listaInv();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-listas-precios.clonar', $lista), [])
            ->assertForbidden();
    }
}
