<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Configuracion\Sede;
use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvOrdenCompra;
use App\Models\Inventarios\InvOrdenCompraItem;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvProveedor;
use App\Models\Inventarios\InvStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el ciclo de vida de órdenes de compra de inventario.
 */
class InvOrdenCompraTest extends TestCase
{
    use RefreshDatabase;

    private User $responsable;
    private InvProveedor $proveedor;
    private InvAlmacen $almacen;
    private InvProducto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_oc',         'descripcion' => 'ver OC']);
        Permission::create(['name' => 'inv_ocCrear',    'descripcion' => 'crear OC']);
        Permission::create(['name' => 'inv_ocEditar',   'descripcion' => 'editar OC']);
        Permission::create(['name' => 'inv_ocEnviar',   'descripcion' => 'enviar OC']);
        Permission::create(['name' => 'inv_ocRecibir',  'descripcion' => 'recibir OC']);
        Permission::create(['name' => 'inv_ocCancelar', 'descripcion' => 'cancelar OC']);

        $this->responsable = User::factory()->create();
        $this->responsable->givePermissionTo([
            'inv_oc', 'inv_ocCrear', 'inv_ocEditar',
            'inv_ocEnviar', 'inv_ocRecibir', 'inv_ocCancelar',
        ]);

        $sede            = Sede::factory()->create(['codigo_inventario' => 'TST-INV']);
        $this->almacen   = InvAlmacen::factory()->create(['sede_id' => $sede->id]);
        $this->proveedor = InvProveedor::factory()->create();
        $this->producto  = InvProducto::factory()->create(['tipo' => 'simple']);
    }

    /**
     * Crea una orden con un ítem en DB directamente.
     *
     * @param string $status
     * @return InvOrdenCompra
     */
    private function crearOrden(string $status = InvOrdenCompra::STATUS_BORRADOR): InvOrdenCompra
    {
        $orden = InvOrdenCompra::create([
            'proveedor_id'   => $this->proveedor->id,
            'almacen_id'     => $this->almacen->id,
            'responsable_id' => $this->responsable->id,
            'status'         => $status,
            'subtotal'       => 50000,
            'total'          => 50000,
        ]);

        InvOrdenCompraItem::create([
            'orden_id'              => $orden->id,
            'producto_id'           => $this->producto->id,
            'cantidad_solicitada'   => 10,
            'precio_costo_unitario' => 5000,
            'subtotal'              => 50000,
            'cantidad_recibida'     => 0,
        ]);

        return $orden;
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_ordenes(): void
    {
        $this->crearOrden();
        $this->crearOrden(InvOrdenCompra::STATUS_ENVIADA);

        $this->actingAs($this->responsable)
            ->getJson(route('inv-oc.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'status', 'total']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /** @test */
    public function index_filtra_por_status(): void
    {
        $this->crearOrden(InvOrdenCompra::STATUS_BORRADOR);
        $this->crearOrden(InvOrdenCompra::STATUS_ENVIADA);

        $response = $this->actingAs($this->responsable)
            ->getJson(route('inv-oc.index') . '?status=borrador')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('inv-oc.index'))
            ->assertForbidden();
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_orden_en_borrador(): void
    {
        $payload = [
            'proveedor_id'  => $this->proveedor->id,
            'almacen_id'    => $this->almacen->id,
            'observaciones' => 'Pedido urgente',
            'items'         => [
                [
                    'producto_id'          => $this->producto->id,
                    'cantidad_solicitada'  => 5,
                    'precio_costo_unitario' => 10000,
                ],
            ],
        ];

        $response = $this->actingAs($this->responsable)
            ->postJson(route('inv-oc.store'), $payload)
            ->assertCreated()
            ->assertJsonFragment(['status' => 'borrador']);

        $this->assertDatabaseHas('inv_ordenes_compra', [
            'proveedor_id' => $this->proveedor->id,
            'almacen_id'   => $this->almacen->id,
            'status'       => 'borrador',
            'total'        => 50000,
        ]);

        $this->assertDatabaseHas('inv_orden_compra_items', [
            'producto_id'          => $this->producto->id,
            'cantidad_solicitada'  => 5,
        ]);
    }

    /** @test */
    public function store_falla_sin_items(): void
    {
        $this->actingAs($this->responsable)
            ->postJson(route('inv-oc.store'), [
                'proveedor_id' => $this->proveedor->id,
                'almacen_id'   => $this->almacen->id,
                'items'        => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    /** @test */
    public function store_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-oc.store'), [])
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_detalle_con_items(): void
    {
        $orden = $this->crearOrden();

        $this->actingAs($this->responsable)
            ->getJson(route('inv-oc.show', $orden))
            ->assertOk()
            ->assertJsonFragment(['id' => $orden->id, 'status' => 'borrador'])
            ->assertJsonStructure(['data' => ['items']]);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_modifica_orden_en_borrador(): void
    {
        $orden = $this->crearOrden();

        $this->actingAs($this->responsable)
            ->putJson(route('inv-oc.update', $orden), [
                'observaciones' => 'Observación actualizada',
                'items'         => [
                    [
                        'producto_id'          => $this->producto->id,
                        'cantidad_solicitada'  => 20,
                        'precio_costo_unitario' => 6000,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonFragment(['total' => '120000.00']);
    }

    /** @test */
    public function update_rechaza_orden_no_borrador(): void
    {
        $orden = $this->crearOrden(InvOrdenCompra::STATUS_ENVIADA);

        $this->actingAs($this->responsable)
            ->putJson(route('inv-oc.update', $orden), [
                'observaciones' => 'Intento de edición',
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Solo se pueden editar órdenes en estado borrador.']);
    }

    // ─── enviar ───────────────────────────────────────────────────────────────

    /** @test */
    public function enviar_cambia_status_a_enviada(): void
    {
        $orden = $this->crearOrden();

        $this->actingAs($this->responsable)
            ->postJson(route('inv-oc.enviar', $orden))
            ->assertOk()
            ->assertJsonFragment(['status' => 'enviada']);

        $this->assertDatabaseHas('inv_ordenes_compra', ['id' => $orden->id, 'status' => 'enviada']);
    }

    /** @test */
    public function enviar_rechaza_orden_ya_enviada(): void
    {
        $orden = $this->crearOrden(InvOrdenCompra::STATUS_ENVIADA);

        $this->actingAs($this->responsable)
            ->postJson(route('inv-oc.enviar', $orden))
            ->assertUnprocessable();
    }

    // ─── recibir ──────────────────────────────────────────────────────────────

    /** @test */
    public function recibir_crea_entrada_de_stock_y_marca_orden_recibida(): void
    {
        $orden = $this->crearOrden(InvOrdenCompra::STATUS_ENVIADA);
        $item  = $orden->items()->first();

        $this->actingAs($this->responsable)
            ->postJson(route('inv-oc.recibir', $orden), [
                'items' => [
                    ['orden_item_id' => $item->id, 'cantidad_recibida' => 10],
                ],
            ])
            ->assertOk()
            ->assertJsonFragment(['status' => 'recibida'])
            ->assertJsonStructure(['numero_documento']);

        // Stock debe haberse incrementado
        $this->assertDatabaseHas('inv_stock', [
            'producto_id' => $this->producto->id,
            'almacen_id'  => $this->almacen->id,
        ]);

        $stock = InvStock::where('producto_id', $this->producto->id)
            ->where('almacen_id', $this->almacen->id)
            ->first();

        $this->assertEquals(10, $stock->cantidad_total);
    }

    /** @test */
    public function recibir_parcialmente_deja_orden_en_recibida_parcial(): void
    {
        $orden = $this->crearOrden(InvOrdenCompra::STATUS_ENVIADA);
        $item  = $orden->items()->first();

        $this->actingAs($this->responsable)
            ->postJson(route('inv-oc.recibir', $orden), [
                'items' => [
                    ['orden_item_id' => $item->id, 'cantidad_recibida' => 4],
                ],
            ])
            ->assertOk()
            ->assertJsonFragment(['status' => 'recibida_parcial']);
    }

    /** @test */
    public function recibir_rechaza_orden_cancelada(): void
    {
        $orden = $this->crearOrden(InvOrdenCompra::STATUS_CANCELADA);
        $item  = $orden->items()->first();

        $this->actingAs($this->responsable)
            ->postJson(route('inv-oc.recibir', $orden), [
                'items' => [['orden_item_id' => $item->id, 'cantidad_recibida' => 5]],
            ])
            ->assertUnprocessable();
    }

    // ─── cancelar ─────────────────────────────────────────────────────────────

    /** @test */
    public function cancelar_orden_borrador(): void
    {
        $orden = $this->crearOrden();

        $this->actingAs($this->responsable)
            ->postJson(route('inv-oc.cancelar', $orden))
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelada']);
    }

    /** @test */
    public function cancelar_rechaza_orden_ya_recibida(): void
    {
        $orden = $this->crearOrden(InvOrdenCompra::STATUS_RECIBIDA);

        $this->actingAs($this->responsable)
            ->postJson(route('inv-oc.cancelar', $orden))
            ->assertUnprocessable();
    }

    // ─── pendientesRecepcion ──────────────────────────────────────────────────

    /** @test */
    public function pendientes_recepcion_retorna_solo_enviadas_y_parciales(): void
    {
        $this->crearOrden(InvOrdenCompra::STATUS_BORRADOR);
        $this->crearOrden(InvOrdenCompra::STATUS_ENVIADA);
        $this->crearOrden(InvOrdenCompra::STATUS_RECIBIDA_PARCIAL);
        $this->crearOrden(InvOrdenCompra::STATUS_RECIBIDA);

        $response = $this->actingAs($this->responsable)
            ->getJson(route('inv-oc.pendientes-recepcion'))
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }
}
