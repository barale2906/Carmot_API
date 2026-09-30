<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Inventarios\InvKitComponente;
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

        $this->lista = LpListaPrecio::factory()->create(['origen' => 0, 'status' => LpListaPrecio::STATUS_EN_PROCESO]);
        $this->producto = InvProducto::factory()->create(['tipo' => 'simple']);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_precios(): void
    {
        $this->lista->update([
            'status' => LpListaPrecio::STATUS_ACTIVA,
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addDay(),
        ]);

        InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id' => $this->producto->id,
            'precio' => 50000,
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

    /** @test */
    public function index_por_defecto_solo_muestra_precios_de_listas_vigentes(): void
    {
        // $this->lista queda "En Proceso" (no vigente) desde el setUp
        InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id' => $this->producto->id,
            'precio' => 50000,
        ]);

        $listaVigente = LpListaPrecio::factory()->create([
            'origen' => 0,
            'status' => LpListaPrecio::STATUS_ACTIVA,
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addDay(),
        ]);
        $precioVigente = InvPrecioProducto::create([
            'lista_precio_id' => $listaVigente->id,
            'producto_id' => $this->producto->id,
            'precio' => 60000,
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.index'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($precioVigente->id, $response->json('data.0.id'));
    }

    /** @test */
    public function index_incluye_no_vigentes_cuando_se_solicita_explicitamente(): void
    {
        InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id' => $this->producto->id,
            'precio' => 50000,
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.index', ['incluir_no_vigentes' => 1]))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function index_filtra_por_lista_precio_id_sin_importar_vigencia(): void
    {
        // $this->lista está "En Proceso" (no vigente), pero se pide analizar puntualmente
        $precio = InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id' => $this->producto->id,
            'precio' => 50000,
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.index', ['lista_precio_id' => $this->lista->id]))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($precio->id, $response->json('data.0.id'));
    }

    /** @test */
    public function index_agrupa_resultados_por_lista_y_luego_por_producto(): void
    {
        $vigente = fn () => [
            'status' => LpListaPrecio::STATUS_ACTIVA,
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addDay(),
        ];

        $listaSur = LpListaPrecio::factory()->create(array_merge($vigente(), ['nombre' => 'Lista Sur', 'origen' => 0]));
        $listaNorte = LpListaPrecio::factory()->create(array_merge($vigente(), ['nombre' => 'Lista Norte', 'origen' => 0]));

        $productoB = InvProducto::factory()->create(['tipo' => 'simple', 'nombre' => 'Zapato']);
        $productoA = InvProducto::factory()->create(['tipo' => 'simple', 'nombre' => 'Camisa']);

        // Se crean en orden inverso al esperado para probar que el ORDER BY reordena, no el insert
        InvPrecioProducto::create(['lista_precio_id' => $listaSur->id, 'producto_id' => $productoA->id, 'precio' => 10000]);
        InvPrecioProducto::create(['lista_precio_id' => $listaNorte->id, 'producto_id' => $productoB->id, 'precio' => 20000]);
        InvPrecioProducto::create(['lista_precio_id' => $listaNorte->id, 'producto_id' => $productoA->id, 'precio' => 30000]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.index', ['incluir_no_vigentes' => 1]))
            ->assertOk();

        $filas = collect($response->json('data'))
            ->map(fn ($f) => [$f['lista_precio']['nombre'], $f['producto']['nombre']])
            ->values()
            ->all();

        $this->assertSame([
            ['Lista Norte', 'Camisa'],
            ['Lista Norte', 'Zapato'],
            ['Lista Sur', 'Camisa'],
        ], $filas);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_precio_correctamente(): void
    {
        $payload = [
            'lista_precio_id' => $this->lista->id,
            'producto_id' => $this->producto->id,
            'precio' => 75000,
            'observaciones' => 'Precio especial',
        ];

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.store'), $payload)
            ->assertCreated()
            ->assertJsonFragment(['precio' => '75000.00']);

        $this->assertDatabaseHas('inv_precios_producto', [
            'lista_precio_id' => $this->lista->id,
            'producto_id' => $this->producto->id,
            'precio' => 75000,
        ]);
    }

    /** @test */
    public function store_falla_sin_lista_precio(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.store'), [
                'producto_id' => $this->producto->id,
                'precio' => 50000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lista_precio_id']);
    }

    /** @test */
    public function store_con_grupo_asigna_precio_a_sus_variantes_activas(): void
    {
        $grupo     = InvProducto::factory()->grupo()->create(['nombre' => 'Camisas']);
        $variante1 = InvProducto::factory()->create(['producto_padre_id' => $grupo->id, 'status' => 1]);
        $variante2 = InvProducto::factory()->create(['producto_padre_id' => $grupo->id, 'status' => 1]);
        $inactiva  = InvProducto::factory()->create(['producto_padre_id' => $grupo->id, 'status' => 0]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.store'), [
                'lista_precio_id' => $this->lista->id,
                'producto_id'     => $grupo->id,
                'precio'          => 45000,
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['message' => 'Precio asignado a 2 variante(s) del grupo Camisas.']);

        $this->assertDatabaseHas('inv_precios_producto', ['lista_precio_id' => $this->lista->id, 'producto_id' => $variante1->id, 'precio' => 45000]);
        $this->assertDatabaseHas('inv_precios_producto', ['lista_precio_id' => $this->lista->id, 'producto_id' => $variante2->id, 'precio' => 45000]);
        $this->assertDatabaseMissing('inv_precios_producto', ['producto_id' => $inactiva->id]);
        $this->assertDatabaseMissing('inv_precios_producto', ['producto_id' => $grupo->id]);
    }

    /** @test */
    public function store_con_grupo_conserva_los_demas_precios_de_la_lista(): void
    {
        $otroPrecio = InvPrecioProducto::factory()->create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => 78000,
        ]);
        $grupo = InvProducto::factory()->grupo()->create();
        InvProducto::factory()->create(['producto_padre_id' => $grupo->id, 'status' => 1]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.store'), [
                'lista_precio_id' => $this->lista->id,
                'producto_id'     => $grupo->id,
                'precio'          => 45000,
            ])
            ->assertCreated();

        $this->assertNotSoftDeleted('inv_precios_producto', ['id' => $otroPrecio->id, 'precio' => 78000]);
    }

    /** @test */
    public function store_con_grupo_funciona_en_lista_activa(): void
    {
        $this->lista->update(['status' => LpListaPrecio::STATUS_ACTIVA]);
        $grupo    = InvProducto::factory()->grupo()->create();
        $variante = InvProducto::factory()->create(['producto_padre_id' => $grupo->id, 'status' => 1]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.store'), [
                'lista_precio_id' => $this->lista->id,
                'producto_id'     => $grupo->id,
                'precio'          => 45000,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('inv_precios_producto', ['producto_id' => $variante->id, 'precio' => 45000]);
    }

    /** @test */
    public function store_con_grupo_sin_variantes_activas_falla(): void
    {
        $grupo = InvProducto::factory()->grupo()->create();
        InvProducto::factory()->create(['producto_padre_id' => $grupo->id, 'status' => 0]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.store'), [
                'lista_precio_id' => $this->lista->id,
                'producto_id'     => $grupo->id,
                'precio'          => 45000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['producto_id']);

        $this->assertDatabaseCount('inv_precios_producto', 0);
    }

    /** @test */
    public function store_actualiza_precio_si_ya_existe_en_misma_lista(): void
    {
        InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id' => $this->producto->id,
            'precio' => 50000,
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-precios.store'), [
                'lista_precio_id' => $this->lista->id,
                'producto_id' => $this->producto->id,
                'precio' => 60000,
            ])
            ->assertCreated()
            ->assertJsonFragment(['precio' => '60000.00']);

        $this->assertDatabaseHas('inv_precios_producto', [
            'lista_precio_id' => $this->lista->id,
            'producto_id' => $this->producto->id,
            'precio' => 60000,
        ]);
        // Solo debe haber un registro (upsert, no duplicado)
        $this->assertDatabaseCount('inv_precios_producto', 1);
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_incluye_tipo_del_producto_para_identificar_kits(): void
    {
        $kit    = InvProducto::factory()->kit()->create();
        $precio = InvPrecioProducto::asignar($this->lista->id, $kit->id, 90000);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.show', $precio))
            ->assertOk()
            ->assertJsonPath('data.producto.tipo', 'kit');
    }

    /** @test */
    public function show_retorna_precio_con_relaciones(): void
    {
        $precio = InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id' => $this->producto->id,
            'precio' => 90000,
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
            'producto_id' => $this->producto->id,
            'precio' => 90000,
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
            'producto_id' => $this->producto->id,
            'precio' => 90000,
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
            'producto_id' => $this->producto->id,
            'precio' => 50000,
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
            'producto_id' => $this->producto->id,
            'precio' => 50000,
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
            'producto_id' => $this->producto->id,
            'precio' => 50000,
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
        $grupo = InvProducto::factory()->create(['tipo' => 'grupo']);
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

    // ─── desgloseKit ──────────────────────────────────────────────────────────

    /**
     * Kit con 2 camisas (grupo con variantes a 40.000 y 50.000) y 1 cinturón (simple a 10.000).
     *
     * @return InvProducto
     */
    private function crearKitConPrecios(): InvProducto
    {
        $camisas  = InvProducto::factory()->grupo()->create(['nombre' => 'Camisas']);
        $camisaM  = InvProducto::factory()->create(['producto_padre_id' => $camisas->id, 'status' => 1]);
        $camisaL  = InvProducto::factory()->create(['producto_padre_id' => $camisas->id, 'status' => 1]);
        $cinturon = InvProducto::factory()->create(['nombre' => 'Cinturón']);
        $kit      = InvProducto::factory()->kit()->create();

        InvKitComponente::create(['kit_id' => $kit->id, 'grupo_producto_id' => $camisas->id, 'cantidad' => 2, 'orden' => 1]);
        InvKitComponente::create(['kit_id' => $kit->id, 'grupo_producto_id' => $cinturon->id, 'cantidad' => 1, 'orden' => 2]);

        InvPrecioProducto::asignar($this->lista->id, $camisaM->id, 40000);
        InvPrecioProducto::asignar($this->lista->id, $camisaL->id, 50000);
        InvPrecioProducto::asignar($this->lista->id, $cinturon->id, 10000);

        return $kit;
    }

    /** @test */
    public function desglose_kit_suma_precio_unitario_por_cantidad_de_cada_componente(): void
    {
        $kit = $this->crearKitConPrecios();
        InvPrecioProducto::asignar($this->lista->id, $kit->id, 95000);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.desglose-kit', [$this->lista, $kit]))
            ->assertOk()
            ->assertJsonPath('data.precio_actual', 95000)
            ->assertJsonPath('data.componentes.0.nombre', 'Camisas')
            ->assertJsonPath('data.componentes.0.precio_unitario_min', 40000)
            ->assertJsonPath('data.componentes.0.precio_unitario_max', 50000)
            ->assertJsonPath('data.componentes.0.subtotal_min', 80000)
            ->assertJsonPath('data.componentes.0.subtotal_max', 100000)
            ->assertJsonCount(2, 'data.componentes.0.variantes')
            ->assertJsonPath('data.componentes.1.subtotal_min', 10000)
            ->assertJsonCount(0, 'data.componentes.1.variantes')
            ->assertJsonPath('data.total_min', 90000)
            ->assertJsonPath('data.total_max', 110000)
            ->assertJsonPath('data.completo', true);
    }

    /** @test */
    public function desglose_kit_marca_incompleto_si_falta_precio_de_una_variante(): void
    {
        $kit    = $this->crearKitConPrecios();
        $grupo  = InvProducto::where('nombre', 'Camisas')->first();
        InvProducto::factory()->create(['producto_padre_id' => $grupo->id, 'status' => 1]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.desglose-kit', [$this->lista, $kit]))
            ->assertOk()
            ->assertJsonPath('data.componentes.0.completo', false)
            ->assertJsonPath('data.completo', false)
            ->assertJsonPath('data.total_min', 90000)
            ->assertJsonPath('data.precio_actual', null);
    }

    /** @test */
    public function desglose_kit_rechaza_producto_que_no_es_kit(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.desglose-kit', [$this->lista, $this->producto]))
            ->assertUnprocessable();
    }

    /** @test */
    public function desglose_kit_retorna_404_si_la_lista_no_es_de_inventario(): void
    {
        $listaAcademica = LpListaPrecio::factory()->create(['origen' => 1]);
        $kit            = InvProducto::factory()->kit()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.desglose-kit', [$listaAcademica, $kit]))
            ->assertNotFound();
    }

    /** @test */
    public function desglose_kit_deniega_sin_permiso(): void
    {
        $kit = InvProducto::factory()->kit()->create();

        $this->actingAs(User::factory()->create())
            ->getJson(route('inv-precios.desglose-kit', [$this->lista, $kit]))
            ->assertForbidden();
    }

    // ─── porProducto ──────────────────────────────────────────────────────────

    /** @test */
    public function por_producto_retorna_precios_del_producto(): void
    {
        InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id' => $this->producto->id,
            'precio' => 50000,
        ]);

        $otraLista = LpListaPrecio::factory()->create(['origen' => 0]);
        InvPrecioProducto::create([
            'lista_precio_id' => $otraLista->id,
            'producto_id' => $this->producto->id,
            'precio' => 55000,
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-precios.por-producto', $this->producto->id))
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }
}
