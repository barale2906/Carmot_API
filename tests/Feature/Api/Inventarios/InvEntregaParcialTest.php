<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvEntregaKit;
use App\Models\Inventarios\InvEntregaKitComponente;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvKitComponente;
use App\Models\Inventarios\InvPedido;
use App\Models\Inventarios\InvPrecioProducto;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para la verificación de disponibilidad previa a la venta,
 * la entrega inmediata desde el recibo y la entrega parcial de kits.
 */
class InvEntregaParcialTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;
    private User $estudiante;
    private Sede $sede;
    private InvAlmacen $almacen;
    private LpListaPrecio $lista;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_ventasCrear',       'descripcion' => 'crear ventas']);
        Permission::create(['name' => 'inv_ventasAbonar',      'descripcion' => 'abonar ventas']);
        Permission::create(['name' => 'inv_entregas',          'descripcion' => 'ver entregas']);
        Permission::create(['name' => 'inv_entregasCompletar', 'descripcion' => 'completar entregas']);

        $poblacion = Poblacion::factory()->create();

        $this->sede = Sede::factory()->create([
            'codigo_inventario' => 'TST-INV',
            'poblacion_id'      => $poblacion->id,
        ]);

        $this->almacen    = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        $this->estudiante = User::factory()->create();

        $this->cajero = User::factory()->create();
        $this->cajero->givePermissionTo([
            'inv_ventasCrear', 'inv_ventasAbonar', 'inv_entregas', 'inv_entregasCompletar',
        ]);

        $this->lista = LpListaPrecio::factory()->create([
            'origen'       => 0,
            'status'       => LpListaPrecio::STATUS_ACTIVA,
            'fecha_inicio' => today()->subMonth()->toDateString(),
            'fecha_fin'    => today()->addMonths(6)->toDateString(),
        ]);

        $this->lista->poblaciones()->attach($poblacion->id);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    /**
     * Crea un producto con precio vigente en la lista activa de la sede.
     *
     * @param string $tipo
     * @param float  $precio
     * @return InvProducto
     */
    private function crearProducto(string $tipo = 'simple', float $precio = 50000): InvProducto
    {
        $producto = InvProducto::factory()->create(['tipo' => $tipo]);

        InvPrecioProducto::create([
            'lista_precio_id' => $this->lista->id,
            'producto_id'     => $producto->id,
            'precio'          => $precio,
        ]);

        return $producto;
    }

    /**
     * Registra stock disponible de un producto en el almacén de pruebas.
     *
     * @param InvProducto $producto
     * @param int         $cantidad
     * @return void
     */
    private function darStock(InvProducto $producto, int $cantidad): void
    {
        InvStock::create([
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $producto->id,
            'cantidad_total'      => $cantidad,
            'cantidad_reservada'  => 0,
            'cantidad_disponible' => $cantidad,
        ]);
    }

    /**
     * Crea un kit con dos componentes simples de una unidad cada uno.
     *
     * @return array{kit: InvProducto, a: InvProducto, b: InvProducto, compA: InvKitComponente, compB: InvKitComponente}
     */
    private function crearKitConDosComponentes(): array
    {
        $kit = $this->crearProducto('kit', 100000);
        $a   = InvProducto::factory()->create(['tipo' => 'simple']);
        $b   = InvProducto::factory()->create(['tipo' => 'simple']);

        $compA = InvKitComponente::create([
            'kit_id'            => $kit->id,
            'grupo_producto_id' => $a->id,
            'cantidad'          => 1,
            'orden'             => 1,
        ]);

        $compB = InvKitComponente::create([
            'kit_id'            => $kit->id,
            'grupo_producto_id' => $b->id,
            'cantidad'          => 1,
            'orden'             => 2,
        ]);

        return ['kit' => $kit, 'a' => $a, 'b' => $b, 'compA' => $compA, 'compB' => $compB];
    }

    /**
     * Construye el payload de una venta pagada de contado.
     *
     * @param array $items
     * @param float $total
     * @param array $override
     * @return array
     */
    private function payloadVenta(array $items, float $total, array $override = []): array
    {
        return array_merge([
            'estudiante_id' => $this->estudiante->id,
            'sede_id'       => $this->sede->id,
            'almacen_id'    => $this->almacen->id,
            'items'         => $items,
            'monto_abono'   => $total,
            'medios_pago'   => [['medio_pago' => 'efectivo', 'valor' => $total]],
        ], $override);
    }

    // ─── verificar disponibilidad ─────────────────────────────────────────────

    /** @test */
    public function verificar_disponibilidad_marca_producto_con_stock_como_entregable(): void
    {
        $producto = $this->crearProducto('simple');
        $this->darStock($producto, 10);

        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.verificar-disponibilidad'), [
                'almacen_id' => $this->almacen->id,
                'items'      => [['producto_id' => $producto->id, 'cantidad' => 3]],
            ])
            ->assertOk();

        $item = $response->json('data.items.0');

        $this->assertTrue($item['vendible']);
        $this->assertTrue($item['entregable_ahora']);
        $this->assertEquals(10, $item['stock_disponible']);
        $this->assertEquals(3, $item['cantidad_entregable']);
        $this->assertEquals(0, $item['faltante']);
        $this->assertTrue($response->json('data.entregable_completo'));
    }

    /** @test */
    public function verificar_disponibilidad_reporta_faltante_sin_marcar_no_vendible(): void
    {
        $producto = $this->crearProducto('simple');
        $this->darStock($producto, 1);

        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.verificar-disponibilidad'), [
                'almacen_id' => $this->almacen->id,
                'items'      => [['producto_id' => $producto->id, 'cantidad' => 4]],
            ])
            ->assertOk();

        $item = $response->json('data.items.0');

        // El stock insuficiente nunca impide vender: solo informa el faltante.
        $this->assertTrue($item['vendible']);
        $this->assertFalse($item['entregable_ahora']);
        $this->assertEquals(1, $item['cantidad_entregable']);
        $this->assertEquals(3, $item['faltante']);
    }

    /** @test */
    public function verificar_disponibilidad_de_kit_detalla_el_componente_sin_stock(): void
    {
        $kit = $this->crearKitConDosComponentes();
        $this->darStock($kit['a'], 5);
        // El componente B queda sin stock a propósito.

        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.verificar-disponibilidad'), [
                'almacen_id' => $this->almacen->id,
                'items'      => [['producto_id' => $kit['kit']->id, 'cantidad' => 1]],
            ])
            ->assertOk();

        $item = $response->json('data.items.0');

        $this->assertTrue($item['vendible']);
        $this->assertFalse($item['entregable_ahora']);
        $this->assertCount(2, $item['componentes']);

        $componenteA = collect($item['componentes'])->firstWhere('componente_id', $kit['a']->id);
        $componenteB = collect($item['componentes'])->firstWhere('componente_id', $kit['b']->id);

        $this->assertTrue($componenteA['entregable_ahora']);
        $this->assertFalse($componenteB['entregable_ahora']);
        $this->assertEquals(1, $componenteB['faltante']);
    }

    /** @test */
    public function verificar_disponibilidad_deniega_sin_permiso(): void
    {
        $producto   = $this->crearProducto('simple');
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-ventas.verificar-disponibilidad'), [
                'almacen_id' => $this->almacen->id,
                'items'      => [['producto_id' => $producto->id, 'cantidad' => 1]],
            ])
            ->assertForbidden();
    }

    // ─── venta con kit incompleto ─────────────────────────────────────────────

    /** @test */
    public function venta_de_kit_con_componente_sin_stock_se_registra_y_deja_pendiente(): void
    {
        $kit = $this->crearKitConDosComponentes();
        $this->darStock($kit['a'], 5);
        // Componente B sin stock: la venta debe completarse igual.

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([['producto_id' => $kit['kit']->id, 'cantidad' => 1]], 100000)
            )
            ->assertCreated();

        $pedido = InvPedido::latest('id')->first();

        // El pedido queda en 'entregando': se despachó lo disponible, falta el resto.
        $this->assertEquals(InvPedido::STATUS_ENTREGANDO, $pedido->status);

        $entregaKit = InvEntregaKit::where('kit_producto_id', $kit['kit']->id)->first();
        $this->assertEquals(InvEntregaKit::STATUS_PARCIAL, $entregaKit->status);

        // El componente con stock salió de inventario; el otro quedó pendiente.
        $lineaA = InvEntregaKitComponente::where('entrega_kit_id', $entregaKit->id)
            ->where('kit_componente_id', $kit['compA']->id)->first();
        $lineaB = InvEntregaKitComponente::where('entrega_kit_id', $entregaKit->id)
            ->where('kit_componente_id', $kit['compB']->id)->first();

        $this->assertEquals(InvEntregaKitComponente::STATUS_ENTREGADO, $lineaA->status);
        $this->assertEquals(InvEntregaKitComponente::STATUS_PENDIENTE, $lineaB->status);

        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $kit['a']->id,
            'cantidad_disponible' => 4,
        ]);

        // El faltante genera su necesidad de compra.
        $this->assertDatabaseHas('inv_necesidades_compra', [
            'producto_id' => $kit['b']->id,
            'status'      => 'pendiente',
        ]);
    }

    /** @test */
    public function venta_con_entrega_inmediata_descarga_el_inventario(): void
    {
        $producto = $this->crearProducto('simple');
        $this->darStock($producto, 10);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([['producto_id' => $producto->id, 'cantidad' => 2]], 100000)
            )
            ->assertCreated();

        $pedido = InvPedido::latest('id')->first();

        $this->assertEquals(InvPedido::STATUS_ENTREGADO, $pedido->status);
        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $producto->id,
            'cantidad_disponible' => 8,
        ]);
    }

    /** @test */
    public function venta_con_entrega_inmediata_en_false_no_descarga_inventario(): void
    {
        $producto = $this->crearProducto('simple');
        $this->darStock($producto, 10);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta(
                    [['producto_id' => $producto->id, 'cantidad' => 2]],
                    100000,
                    ['entrega_inmediata' => false]
                )
            )
            ->assertCreated();

        $pedido = InvPedido::latest('id')->first();

        // Queda pagado y pendiente de entrega, sin tocar el stock.
        $this->assertEquals(InvPedido::STATUS_PAGADO, $pedido->status);
        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $producto->id,
            'cantidad_disponible' => 10,
        ]);
    }

    /** @test */
    public function venta_permite_excluir_un_item_de_la_entrega_inmediata(): void
    {
        $entregado = $this->crearProducto('simple', 40000);
        $diferido  = $this->crearProducto('simple', 60000);
        $this->darStock($entregado, 5);
        $this->darStock($diferido, 5);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $entregado->id, 'cantidad' => 1],
                    ['producto_id' => $diferido->id,  'cantidad' => 1, 'entregar' => false],
                ], 100000)
            )
            ->assertCreated();

        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $entregado->id,
            'cantidad_disponible' => 4,
        ]);

        // El ítem excluido conserva su stock intacto.
        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $diferido->id,
            'cantidad_disponible' => 5,
        ]);
    }

    // ─── entrega parcial de kit ───────────────────────────────────────────────

    /**
     * Crea una venta de kit sin stock para trabajar sobre sus entregas pendientes.
     *
     * @return array{entregaKit: InvEntregaKit, kit: array}
     */
    private function venderKitSinStock(): array
    {
        $kit = $this->crearKitConDosComponentes();

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([['producto_id' => $kit['kit']->id, 'cantidad' => 1]], 100000)
            )
            ->assertCreated();

        return [
            'entregaKit' => InvEntregaKit::where('kit_producto_id', $kit['kit']->id)->firstOrFail(),
            'kit'        => $kit,
        ];
    }

    /** @test */
    public function entregar_componentes_despacha_solo_los_indicados(): void
    {
        ['entregaKit' => $entregaKit, 'kit' => $kit] = $this->venderKitSinStock();

        // Llega stock de ambos componentes, pero el cajero entrega solo el primero.
        $this->darStock($kit['a'], 3);
        $this->darStock($kit['b'], 3);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.entregar-componentes', $entregaKit->id), [
                'componentes' => [['kit_componente_id' => $kit['compA']->id]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', InvEntregaKit::STATUS_PARCIAL);

        $lineaA = InvEntregaKitComponente::where('kit_componente_id', $kit['compA']->id)->first();
        $lineaB = InvEntregaKitComponente::where('kit_componente_id', $kit['compB']->id)->first();

        $this->assertEquals(InvEntregaKitComponente::STATUS_ENTREGADO, $lineaA->status);
        $this->assertEquals(InvEntregaKitComponente::STATUS_PENDIENTE, $lineaB->status);

        // Solo se descontó el componente entregado.
        $this->assertDatabaseHas('inv_stock', ['producto_id' => $kit['a']->id, 'cantidad_disponible' => 2]);
        $this->assertDatabaseHas('inv_stock', ['producto_id' => $kit['b']->id, 'cantidad_disponible' => 3]);
    }

    /** @test */
    public function entregar_componentes_completa_el_kit_cuando_se_entregan_todos(): void
    {
        ['entregaKit' => $entregaKit, 'kit' => $kit] = $this->venderKitSinStock();

        $this->darStock($kit['a'], 3);
        $this->darStock($kit['b'], 3);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.entregar-componentes', $entregaKit->id), [
                'componentes' => [
                    ['kit_componente_id' => $kit['compA']->id],
                    ['kit_componente_id' => $kit['compB']->id],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', InvEntregaKit::STATUS_COMPLETO);

        $this->assertEquals(
            InvPedido::STATUS_ENTREGADO,
            InvPedido::latest('id')->first()->status
        );
    }

    /** @test */
    public function entregar_componentes_con_cantidad_parcial_deja_el_componente_en_parcial(): void
    {
        $kit = $this->crearKitConDosComponentes();

        // 2 kits ⇒ se requieren 2 unidades de cada componente.
        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([['producto_id' => $kit['kit']->id, 'cantidad' => 2]], 200000)
            )
            ->assertCreated();

        $entregaKit = InvEntregaKit::where('kit_producto_id', $kit['kit']->id)->firstOrFail();

        $this->darStock($kit['a'], 5);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.entregar-componentes', $entregaKit->id), [
                'componentes' => [
                    ['kit_componente_id' => $kit['compA']->id, 'cantidad' => 1],
                ],
            ])
            ->assertOk();

        $lineaA = InvEntregaKitComponente::where('kit_componente_id', $kit['compA']->id)->first();

        $this->assertEquals(InvEntregaKitComponente::STATUS_PARCIAL, $lineaA->status);
        $this->assertEquals(1, $lineaA->cantidad_entregada);
        $this->assertEquals(2, $lineaA->cantidad_solicitada);

        // No se registra necesidad de compra del componente A: queda 1 por entregar
        // pero hay stock de sobra (4). Una necesidad significa que hay que comprar.
        $this->assertDatabaseMissing('inv_necesidades_compra', [
            'producto_id' => $kit['a']->id,
            'status'      => 'pendiente',
        ]);

        // El componente B sí la genera: se requieren 2 y no hay stock.
        $this->assertDatabaseHas('inv_necesidades_compra', [
            'producto_id'        => $kit['b']->id,
            'cantidad_necesaria' => 2,
            'status'             => 'pendiente',
        ]);
    }

    /** @test */
    public function entregar_componentes_rechaza_un_componente_de_otro_kit(): void
    {
        ['entregaKit' => $entregaKit] = $this->venderKitSinStock();

        $otroKit = $this->crearKitConDosComponentes();

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.entregar-componentes', $entregaKit->id), [
                'componentes' => [['kit_componente_id' => $otroKit['compA']->id]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('componentes.0.kit_componente_id');
    }

    /** @test */
    public function entregar_componentes_deniega_sin_permiso(): void
    {
        ['entregaKit' => $entregaKit, 'kit' => $kit] = $this->venderKitSinStock();

        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-entregas.entregar-componentes', $entregaKit->id), [
                'componentes' => [['kit_componente_id' => $kit['compA']->id]],
            ])
            ->assertForbidden();
    }

    // ─── entrega parcial de producto simple ───────────────────────────────────

    /** @test */
    public function completar_simple_entrega_solo_la_cantidad_indicada(): void
    {
        $producto = $this->crearProducto('simple', 50000);

        // Se vende sin stock: la entrega queda pendiente.
        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([['producto_id' => $producto->id, 'cantidad' => 3]], 150000)
            )
            ->assertCreated();

        $entrega = InvEntregaSimple::where('producto_id', $producto->id)->firstOrFail();
        $this->assertEquals(InvEntregaSimple::STATUS_PENDIENTE, $entrega->status);

        $this->darStock($producto, 10);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.completar-simple', $entrega->id), ['cantidad' => 2])
            ->assertOk()
            ->assertJsonPath('data.status', InvEntregaSimple::STATUS_PARCIAL);

        $entrega->refresh();
        $this->assertEquals(2, $entrega->cantidad_entregada);

        // Solo se descontaron las 2 unidades entregadas.
        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $producto->id,
            'cantidad_disponible' => 8,
        ]);

        // La segunda entrega cierra el ítem.
        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.completar-simple', $entrega->id))
            ->assertOk()
            ->assertJsonPath('data.status', InvEntregaSimple::STATUS_ENTREGADO);

        $this->assertEquals(
            InvPedido::STATUS_ENTREGADO,
            InvPedido::latest('id')->first()->status
        );
    }
    // ─── entrega completa exigida por el comprador ────────────────────────────

    /** @test */
    public function item_con_entrega_completa_no_descarga_nada_si_el_stock_no_alcanza(): void
    {
        $producto = $this->crearProducto('simple', 50000);
        $this->darStock($producto, 2); // se piden 3

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $producto->id, 'cantidad' => 3, 'entrega_completa' => true],
                ], 150000)
            )
            ->assertCreated();

        // El stock queda intacto: el comprador quiere las 3 unidades juntas.
        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $producto->id,
            'cantidad_disponible' => 2,
        ]);

        $entrega = InvEntregaSimple::where('producto_id', $producto->id)->firstOrFail();
        $this->assertEquals(InvEntregaSimple::STATUS_PENDIENTE, $entrega->status);
        $this->assertEquals(0, $entrega->cantidad_entregada);

        // La marca quedó registrada en el ítem para las entregas posteriores.
        $this->assertDatabaseHas('inv_pedido_items', [
            'producto_id'      => $producto->id,
            'entrega_completa' => true,
        ]);
    }

    /** @test */
    public function item_sin_entrega_completa_si_descarga_lo_disponible(): void
    {
        $producto = $this->crearProducto('simple', 50000);
        $this->darStock($producto, 2);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([['producto_id' => $producto->id, 'cantidad' => 3]], 150000)
            )
            ->assertCreated();

        // Comportamiento por defecto: entrega parcial de lo que hay.
        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $producto->id,
            'cantidad_disponible' => 0,
        ]);

        $entrega = InvEntregaSimple::where('producto_id', $producto->id)->firstOrFail();
        $this->assertEquals(InvEntregaSimple::STATUS_PARCIAL, $entrega->status);
        $this->assertEquals(2, $entrega->cantidad_entregada);
    }

    /** @test */
    public function entrega_completa_descarga_todo_cuando_llega_el_stock_faltante(): void
    {
        $producto = $this->crearProducto('simple', 50000);
        $this->darStock($producto, 2);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $producto->id, 'cantidad' => 3, 'entrega_completa' => true],
                ], 150000)
            )
            ->assertCreated();

        $entrega = InvEntregaSimple::where('producto_id', $producto->id)->firstOrFail();

        // Aún sin stock suficiente, el intento manual tampoco descarga nada.
        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.completar-simple', $entrega->id))
            ->assertOk()
            ->assertJsonPath('data.status', InvEntregaSimple::STATUS_PENDIENTE);

        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $producto->id,
            'cantidad_disponible' => 2,
        ]);

        // Llega una unidad más: ahora sí sale completo de una sola vez.
        InvStock::where('producto_id', $producto->id)
            ->update(['cantidad_total' => 3, 'cantidad_disponible' => 3]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.completar-simple', $entrega->id))
            ->assertOk()
            ->assertJsonPath('data.status', InvEntregaSimple::STATUS_ENTREGADO);

        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $producto->id,
            'cantidad_disponible' => 0,
        ]);
    }

    /** @test */
    public function forzar_parcial_permite_entregar_pese_a_la_marca_de_entrega_completa(): void
    {
        $producto = $this->crearProducto('simple', 50000);
        $this->darStock($producto, 2);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $producto->id, 'cantidad' => 3, 'entrega_completa' => true],
                ], 150000)
            )
            ->assertCreated();

        $entrega = InvEntregaSimple::where('producto_id', $producto->id)->firstOrFail();

        // El comprador cambia de opinión: el cajero fuerza la entrega parcial.
        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.completar-simple', $entrega->id), ['forzar_parcial' => true])
            ->assertOk()
            ->assertJsonPath('data.status', InvEntregaSimple::STATUS_PARCIAL);

        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $producto->id,
            'cantidad_disponible' => 0,
        ]);
    }

    /** @test */
    public function kit_con_entrega_completa_no_descarga_ningun_componente_si_falta_uno(): void
    {
        $kit = $this->crearKitConDosComponentes();
        $this->darStock($kit['a'], 5);
        // Componente B sin stock.

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $kit['kit']->id, 'cantidad' => 1, 'entrega_completa' => true],
                ], 100000)
            )
            ->assertCreated();

        // Ningún componente salió de bodega, ni siquiera el que tenía stock.
        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $kit['a']->id,
            'cantidad_disponible' => 5,
        ]);

        $entregaKit = InvEntregaKit::where('kit_producto_id', $kit['kit']->id)->firstOrFail();
        $this->assertEquals(InvEntregaKit::STATUS_PENDIENTE, $entregaKit->status);

        // Las necesidades de compra sí se registran, para poder reponer.
        $this->assertDatabaseHas('inv_necesidades_compra', [
            'producto_id' => $kit['b']->id,
            'status'      => 'pendiente',
        ]);
    }

    /** @test */
    public function kit_con_entrega_completa_rechaza_la_entrega_parcial_dirigida(): void
    {
        $kit = $this->crearKitConDosComponentes();

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $kit['kit']->id, 'cantidad' => 1, 'entrega_completa' => true],
                ], 100000)
            )
            ->assertCreated();

        $entregaKit = InvEntregaKit::where('kit_producto_id', $kit['kit']->id)->firstOrFail();

        // Solo llega stock del componente A.
        $this->darStock($kit['a'], 3);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.entregar-componentes', $entregaKit->id), [
                'componentes' => [['kit_componente_id' => $kit['compA']->id]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', InvEntregaKit::STATUS_PENDIENTE);

        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $kit['a']->id,
            'cantidad_disponible' => 3,
        ]);

        // Con forzar_parcial el cajero sí puede despacharlo.
        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.entregar-componentes', $entregaKit->id), [
                'componentes'    => [['kit_componente_id' => $kit['compA']->id]],
                'forzar_parcial' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', InvEntregaKit::STATUS_PARCIAL);

        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $kit['a']->id,
            'cantidad_disponible' => 2,
        ]);
    }

    /** @test */
    public function verificar_disponibilidad_con_entrega_completa_reporta_cero_entregable(): void
    {
        $producto = $this->crearProducto('simple');
        $this->darStock($producto, 2);

        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.verificar-disponibilidad'), [
                'almacen_id' => $this->almacen->id,
                'items'      => [
                    ['producto_id' => $producto->id, 'cantidad' => 3, 'entrega_completa' => true],
                ],
            ])
            ->assertOk();

        $item = $response->json('data.items.0');

        // El preview debe coincidir con lo que realmente ocurrirá: no sale nada.
        $this->assertTrue($item['entrega_completa']);
        $this->assertFalse($item['entregable_ahora']);
        $this->assertEquals(0, $item['cantidad_entregable']);
        $this->assertEquals(3, $item['faltante']);
    }
    // ─── registro de entrega para ítems no despachados ────────────────────────

    /** @test */
    public function items_excluidos_del_despacho_igual_reciben_su_registro_de_entrega(): void
    {
        $entregado = $this->crearProducto('simple', 40000);
        $diferido  = $this->crearProducto('simple', 30000);
        $kit       = $this->crearKitConDosComponentes();

        $this->darStock($entregado, 5);
        $this->darStock($diferido, 5);
        $this->darStock($kit['a'], 5);
        $this->darStock($kit['b'], 5);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $entregado->id, 'cantidad' => 1],
                    ['producto_id' => $diferido->id,  'cantidad' => 1, 'entregar' => false],
                    ['producto_id' => $kit['kit']->id, 'cantidad' => 1, 'entregar' => false],
                ], 170000)
            )
            ->assertCreated();

        // Los tres ítems tienen registro de entrega, no solo el despachado.
        $this->assertDatabaseHas('inv_entregas_simple', [
            'producto_id' => $entregado->id,
            'status'      => InvEntregaSimple::STATUS_ENTREGADO,
        ]);

        $this->assertDatabaseHas('inv_entregas_simple', [
            'producto_id'        => $diferido->id,
            'status'             => InvEntregaSimple::STATUS_PENDIENTE,
            'cantidad_entregada' => 0,
        ]);

        $entregaKit = InvEntregaKit::where('kit_producto_id', $kit['kit']->id)->first();
        $this->assertNotNull($entregaKit, 'El kit diferido debe tener su entrega creada.');
        $this->assertEquals(InvEntregaKit::STATUS_PENDIENTE, $entregaKit->status);
        $this->assertCount(2, $entregaKit->componentes);

        // Sin descargar inventario de lo diferido.
        $this->assertDatabaseHas('inv_stock', ['producto_id' => $diferido->id, 'cantidad_disponible' => 5]);
        $this->assertDatabaseHas('inv_stock', ['producto_id' => $kit['a']->id, 'cantidad_disponible' => 5]);

        // Y sin generar necesidades de compra falsas: hay stock de sobra.
        $this->assertDatabaseMissing('inv_necesidades_compra', [
            'producto_id' => $diferido->id,
            'status'      => 'pendiente',
        ]);
    }

    /** @test */
    public function entrega_inmediata_false_crea_los_registros_de_todos_los_items(): void
    {
        $producto = $this->crearProducto('simple', 50000);
        $kit      = $this->crearKitConDosComponentes();
        $this->darStock($producto, 5);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $producto->id, 'cantidad' => 1],
                    ['producto_id' => $kit['kit']->id, 'cantidad' => 1],
                ], 150000, ['entrega_inmediata' => false])
            )
            ->assertCreated();

        $pedido = InvPedido::latest('id')->first();
        $this->assertEquals(InvPedido::STATUS_PAGADO, $pedido->status);

        $this->assertDatabaseHas('inv_entregas_simple', [
            'producto_id' => $producto->id,
            'status'      => InvEntregaSimple::STATUS_PENDIENTE,
        ]);

        $this->assertNotNull(InvEntregaKit::where('kit_producto_id', $kit['kit']->id)->first());
        $this->assertDatabaseHas('inv_stock', ['producto_id' => $producto->id, 'cantidad_disponible' => 5]);
    }

    /** @test */
    public function item_diferido_se_puede_entregar_luego_desde_entregas_pendientes(): void
    {
        $producto = $this->crearProducto('simple', 50000);
        $this->darStock($producto, 5);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $producto->id, 'cantidad' => 2, 'entregar' => false],
                ], 100000)
            )
            ->assertCreated();

        // El id del registro llega en la pantalla de pendientes.
        $response = $this->actingAs($this->cajero)
            ->getJson(route('inv-entregas.pendientes'))
            ->assertOk();

        $entregaId = $response->json('data.0.items.0.entrega_simple.id');
        $this->assertNotNull($entregaId, 'El ítem diferido debe exponer su entrega_simple.id.');

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.completar-simple', $entregaId))
            ->assertOk()
            ->assertJsonPath('data.status', InvEntregaSimple::STATUS_ENTREGADO);

        $this->assertEquals(
            InvPedido::STATUS_ENTREGADO,
            InvPedido::latest('id')->first()->status
        );
    }

    // ─── datos de la pantalla de entregas pendientes ──────────────────────────

    /** @test */
    public function pendientes_incluye_nombre_tipo_y_stock_de_cada_componente(): void
    {
        $kit = $this->crearKitConDosComponentes();
        $this->darStock($kit['a'], 7);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $kit['kit']->id, 'cantidad' => 1, 'entregar' => false],
                ], 100000)
            )
            ->assertCreated();

        $response = $this->actingAs($this->cajero)
            ->getJson(route('inv-entregas.pendientes'))
            ->assertOk();

        $componentes = $response->json('data.0.items.0.entrega_kit.componentes');
        $this->assertCount(2, $componentes);

        $compA = collect($componentes)->firstWhere('kit_componente_id', $kit['compA']->id);
        $compB = collect($componentes)->firstWhere('kit_componente_id', $kit['compB']->id);

        $this->assertEquals($kit['a']->nombre, $compA['componente_nombre']);
        $this->assertEquals('simple', $compA['componente_tipo']);
        $this->assertEquals(7, $compA['stock_disponible']);
        $this->assertEquals(1, $compA['cantidad_pendiente']);
        $this->assertSame([], $compA['variantes']);

        $this->assertEquals(0, $compB['stock_disponible']);
    }

    /** @test */
    public function pendientes_lista_las_variantes_de_un_componente_de_tipo_grupo(): void
    {
        $kitProducto = $this->crearProducto('kit', 100000);

        $grupo = InvProducto::factory()->create(['tipo' => 'grupo', 'nombre' => 'Camisa']);
        $tallaM = InvProducto::factory()->create([
            'tipo' => 'simple', 'nombre' => 'Camisa M', 'producto_padre_id' => $grupo->id, 'status' => 1,
        ]);
        $tallaL = InvProducto::factory()->create([
            'tipo' => 'simple', 'nombre' => 'Camisa L', 'producto_padre_id' => $grupo->id, 'status' => 1,
        ]);

        InvKitComponente::create([
            'kit_id' => $kitProducto->id, 'grupo_producto_id' => $grupo->id, 'cantidad' => 1, 'orden' => 1,
        ]);

        $this->darStock($tallaM, 6);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([['producto_id' => $kitProducto->id, 'cantidad' => 1]], 100000)
            )
            ->assertCreated();

        $response = $this->actingAs($this->cajero)
            ->getJson(route('inv-entregas.pendientes'))
            ->assertOk();

        $componente = $response->json('data.0.items.0.entrega_kit.componentes.0');

        $this->assertEquals('Camisa', $componente['componente_nombre']);
        $this->assertEquals('grupo', $componente['componente_tipo']);
        // Sin variante elegida no hay stock concreto que informar.
        $this->assertNull($componente['stock_disponible']);

        $variantes = collect($componente['variantes']);
        $this->assertCount(2, $variantes);
        $this->assertEquals(6, $variantes->firstWhere('id', $tallaM->id)['stock_disponible']);
        $this->assertEquals(0, $variantes->firstWhere('id', $tallaL->id)['stock_disponible']);
    }

    /** @test */
    public function pendientes_incluye_cantidad_pendiente_y_stock_en_items_simples(): void
    {
        $producto = $this->crearProducto('simple', 50000);
        $this->darStock($producto, 4);

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([['producto_id' => $producto->id, 'cantidad' => 3]], 150000)
            )
            ->assertCreated();

        // Se entregaron las 3 y el pedido quedó cerrado; se vende otro pendiente.
        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([
                    ['producto_id' => $producto->id, 'cantidad' => 2, 'entregar' => false],
                ], 100000)
            )
            ->assertCreated();

        $response = $this->actingAs($this->cajero)
            ->getJson(route('inv-entregas.pendientes'))
            ->assertOk();

        $entrega = $response->json('data.0.items.0.entrega_simple');

        $this->assertEquals(2, $entrega['cantidad_pendiente']);
        $this->assertEquals(1, $entrega['stock_disponible']);
    }

    /** @test */
    public function necesidades_incluye_el_pedido_id(): void
    {
        $producto = $this->crearProducto('simple', 50000);
        // Sin stock: la venta genera la necesidad de compra.

        $this->actingAs($this->cajero)
            ->postJson(
                route('inv-ventas.store'),
                $this->payloadVenta([['producto_id' => $producto->id, 'cantidad' => 2]], 100000)
            )
            ->assertCreated();

        $pedidoId = InvPedido::latest('id')->first()->id;

        $this->actingAs($this->cajero)
            ->getJson(route('inv-entregas.necesidades'))
            ->assertOk()
            ->assertJsonPath('data.0.pedido_id', $pedidoId);
    }
}
