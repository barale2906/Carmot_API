<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Inventarios\InvPrecioProducto;
use App\Models\Inventarios\InvProducto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD de precios de productos de inventario.
 */
class InvPrecioProductoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private LpListaPrecio $lista;
    private InvProducto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_precios',         'descripcion' => 'ver precios']);
        Permission::create(['name' => 'inv_preciosCrear',    'descripcion' => 'crear precios']);
        Permission::create(['name' => 'inv_preciosEditar',   'descripcion' => 'editar precios']);
        Permission::create(['name' => 'inv_preciosEliminar', 'descripcion' => 'eliminar precios']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'inv_precios', 'inv_preciosCrear', 'inv_preciosEditar', 'inv_preciosEliminar',
        ]);

        $this->lista    = LpListaPrecio::factory()->create(['origen' => 0, 'status' => LpListaPrecio::STATUS_EN_PROCESO]);
        $this->producto = InvProducto::factory()->create(['tipo' => 'simple']);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_precios(): void
    {
        InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 50000,
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'lista_precio', 'producto', 'precio']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('inv-precios.index'))
            ->assertForbidden();
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_precio_correctamente(): void
    {
        $payload = [
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 75000,
            'observaciones'   => 'Precio especial',
        ];

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.store'), $payload)
            ->assertCreated()
            ->assertJsonFragment(['precio' => '75000.00']);

        $this->assertDatabaseHas('inv_precios_producto', [
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 75000,
        ]);
    }

    /** @test */
    public function store_falla_sin_lista_precio(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.store'), [
                'producto_id' => $this->producto->id,
                'precio'      => 50000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lista_precio_id']);
    }

    /** @test */
    public function store_actualiza_precio_si_ya_existe_en_misma_lista(): void
    {
        InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 50000,
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.store'), [
                'lista_precio_id' => $this->lista->id,
                'producto_id'     => $this->producto->id,
                'precio'          => 60000,
            ])
            ->assertCreated()
            ->assertJsonFragment(['precio' => '60000.00']);

        $this->assertDatabaseHas('inv_precios_producto', [
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 60000,
        ]);
        // Solo debe haber un registro (upsert, no duplicado)
        $this->assertDatabaseCount('inv_precios_producto', 1);
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_precio_con_relaciones(): void
    {
        $precio = InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 90000,
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.show', $precio))
            ->assertOk()
            ->assertJsonFragment(['id' => $precio->id]);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_modifica_precio_existente(): void
    {
        $precio = InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 90000,
        ]);

        $this->actingAs($this->usuario)
            ->putJson(route('inv-precios.update', $precio), ['precio' => 100000])
            ->assertOk()
            ->assertJsonFragment(['precio' => '100000.00']);

        $this->assertDatabaseHas('inv_precios_producto', ['id' => $precio->id, 'precio' => 100000]);
    }

    /** @test */
    public function update_falla_con_precio_negativo(): void
    {
        $precio = InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 90000,
        ]);

        $this->actingAs($this->usuario)
            ->putJson(route('inv-precios.update', $precio), ['precio' => -500])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['precio']);
    }

    // ─── destroy / restore / forceDelete ──────────────────────────────────────

    /** @test */
    public function destroy_elimina_logicamente_el_precio(): void
    {
        $precio = InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 50000,
        ]);

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-precios.destroy', $precio))
            ->assertOk()
            ->assertJsonFragment(['message' => 'Precio eliminado exitosamente.']);

        $this->assertSoftDeleted('inv_precios_producto', ['id' => $precio->id]);
    }

    /** @test */
    public function restore_recupera_precio_eliminado(): void
    {
        $precio = InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 50000,
        ]);
        $precio->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.restore', $precio->id))
            ->assertOk()
            ->assertJsonFragment(['message' => 'Precio restaurado exitosamente.']);

        $this->assertNotSoftDeleted('inv_precios_producto', ['id' => $precio->id]);
    }

    /** @test */
    public function force_delete_elimina_permanentemente(): void
    {
        $precio = InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 50000,
        ]);
        $precio->delete();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-precios.force-delete', $precio->id))
            ->assertOk();

        $this->assertDatabaseMissing('inv_precios_producto', ['id' => $precio->id]);
    }

    // ─── sincronizar ──────────────────────────────────────────────────────────

    /** @test */
    public function sincronizar_crea_y_actualiza_precios_masivamente(): void
    {
        $productoA = InvProducto::factory()->create(['tipo' => 'simple']);
        $productoB = InvProducto::factory()->create(['tipo' => 'kit']);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.sincronizar', $this->lista), [
                'items' => [
                    ['producto_id' => $productoA->id, 'precio' => 45000],
                    ['producto_id' => $productoB->id, 'precio' => 90000, 'observaciones' => 'Kit especial'],
                ],
            ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Precios sincronizados exitosamente.']);

        $this->assertDatabaseHas('inv_precios_producto', ['lista_precio_id' => $this->lista->id, 'producto_id' => $productoA->id, 'precio' => 45000]);
        $this->assertDatabaseHas('inv_precios_producto', ['lista_precio_id' => $this->lista->id, 'producto_id' => $productoB->id, 'precio' => 90000]);
    }

    /** @test */
    public function sincronizar_elimina_precios_no_incluidos(): void
    {
        $productoA = InvProducto::factory()->create(['tipo' => 'simple']);
        $productoB = InvProducto::factory()->create(['tipo' => 'simple']);

        InvPrecioProducto::create(['lista_precio_id' => $this->lista->id, 'producto_id' => $productoA->id, 'precio' => 50000]);
        InvPrecioProducto::create(['lista_precio_id' => $this->lista->id, 'producto_id' => $productoB->id, 'precio' => 60000]);

        // Solo enviamos A → B debe quedar soft-deleted
        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.sincronizar', $this->lista), [
                'items' => [['producto_id' => $productoA->id, 'precio' => 55000]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('inv_precios_producto', ['producto_id' => $productoA->id, 'precio' => 55000]);
        $this->assertSoftDeleted('inv_precios_producto', ['lista_precio_id' => $this->lista->id, 'producto_id' => $productoB->id]);
    }

    /** @test */
    public function sincronizar_rechaza_lista_no_en_proceso(): void
    {
        $this->lista->update(['status' => LpListaPrecio::STATUS_ACTIVA]);
        $producto = InvProducto::factory()->create(['tipo' => 'simple']);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.sincronizar', $this->lista), [
                'items' => [['producto_id' => $producto->id, 'precio' => 50000]],
            ])
            ->assertUnprocessable();
    }

    /** @test */
    public function sincronizar_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-precios.sincronizar', $this->lista), ['items' => []])
            ->assertForbidden();
    }

    /** @test */
    public function sincronizar_expande_grupo_a_sus_variantes(): void
    {
        $grupo    = InvProducto::factory()->create(['tipo' => 'grupo']);
        $variante1 = InvProducto::factory()->create(['tipo' => 'simple', 'producto_padre_id' => $grupo->id, 'status' => 1]);
        $variante2 = InvProducto::factory()->create(['tipo' => 'simple', 'producto_padre_id' => $grupo->id, 'status' => 1]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.sincronizar', $this->lista), [
                'items' => [['producto_id' => $grupo->id, 'precio' => 35000]],
            ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Precios sincronizados exitosamente.']);

        // El precio se asigna a las variantes, no al grupo
        $this->assertDatabaseMissing('inv_precios_producto', ['lista_precio_id' => $this->lista->id, 'producto_id' => $grupo->id]);
        $this->assertDatabaseHas('inv_precios_producto', ['lista_precio_id' => $this->lista->id, 'producto_id' => $variante1->id, 'precio' => 35000]);
        $this->assertDatabaseHas('inv_precios_producto', ['lista_precio_id' => $this->lista->id, 'producto_id' => $variante2->id, 'precio' => 35000]);
    }

    // ─── porProducto ──────────────────────────────────────────────────────────

    /** @test */
    public function por_producto_retorna_precios_del_producto(): void
    {
        InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 50000,
        ]);

        $otraLista = LpListaPrecio::factory()->create(['origen' => 0]);
        InvPrecioProducto::create([
            'lista_precio_id' => $otraLista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 55000,
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.por-producto', $this->producto->id))
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }
}
