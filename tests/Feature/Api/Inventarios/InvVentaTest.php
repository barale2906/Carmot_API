<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvPedido;
use App\Models\Inventarios\InvPrecioProducto;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para la creación de pedidos y registro de abonos.
 */
class InvVentaTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;
    private User $estudiante;
    private Sede $sede;
    private InvAlmacen $almacen;
    private InvProducto $producto;
    private LpListaPrecio $lista;
    private float $precio = 50000;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_ventasCrear',  'descripcion' => 'crear ventas']);
        Permission::create(['name' => 'inv_ventasAbonar', 'descripcion' => 'abonar ventas']);

        // Poblacion para vincular lista de precios a la sede
        $poblacion = Poblacion::factory()->create();

        // Sede con código para numerar recibos de inventario
        $this->sede = Sede::factory()->create([
            'codigo_inventario' => 'TST-INV',
            'poblacion_id'      => $poblacion->id,
        ]);

        $this->almacen    = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        $this->estudiante = User::factory()->create();

        $this->cajero = User::factory()->create();
        $this->cajero->givePermissionTo(['inv_ventasCrear', 'inv_ventasAbonar']);

        $this->producto = InvProducto::factory()->create(['tipo' => 'simple']);

        // Configurar precio vigente para el producto en la sede
        $this->configurarPrecioVigente($poblacion->id);
    }

    /**
     * Crea la lista de precios y el precio del producto, vinculados a la población de la sede.
     *
     * @param int $poblacionId
     * @return void
     */
    private function configurarPrecioVigente(int $poblacionId): void
    {
        $this->lista = $lista = LpListaPrecio::factory()->create([
            'origen'      => 0,
            'status'      => LpListaPrecio::STATUS_ACTIVA,
            'fecha_inicio' => today()->subMonth()->toDateString(),
            'fecha_fin'    => today()->addMonths(6)->toDateString(),
        ]);

        $lista->poblaciones()->attach($poblacionId);

        InvPrecioProducto::create([
            'lista_precio_id' => $lista->id,
            'producto_id'     => $this->producto->id,
            'precio'          => $this->precio,
        ]);
    }

    /**
     * Construye el payload base para crear un pedido.
     *
     * @param array $override
     * @return array
     */
    private function payloadPedido(array $override = []): array
    {
        return array_merge([
            'estudiante_id' => $this->estudiante->id,
            'sede_id'       => $this->sede->id,
            'almacen_id'    => $this->almacen->id,
            'items'         => [
                ['producto_id' => $this->producto->id, 'cantidad' => 1],
            ],
            'monto_abono'   => $this->precio,
            'medios_pago'   => [
                ['medio_pago' => 'efectivo', 'valor' => $this->precio],
            ],
        ], $override);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_pedido_con_pago_total(): void
    {
        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido())
            ->assertCreated()
            ->assertJsonStructure([
                'data'   => ['id', 'valor_total', 'saldo', 'status'],
                'recibo' => ['id', 'numero_recibo'],
            ]);

        $this->assertDatabaseHas('inv_pedidos', [
            'estudiante_id' => $this->estudiante->id,
            'sede_id'       => $this->sede->id,
        ]);

        // El recibo debe tener origen=inventarios y el prefijo codigo_inventario de la sede
        $this->assertDatabaseHas('recibos_pago', [
            'origen'  => 0,
            'sede_id' => $this->sede->id,
            'prefijo' => $this->sede->codigo_inventario,
        ]);

        // El numero_recibo debe comenzar con el codigo_inventario de la sede
        $numeroRecibo = $response->json('recibo.numero_recibo');
        $this->assertStringStartsWith($this->sede->codigo_inventario . '-', $numeroRecibo);
    }

    /** @test */
    public function store_numero_recibo_usa_prefijo_codigo_inventario_de_la_sede(): void
    {
        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido())
            ->assertCreated();

        $numeroRecibo = $response->json('recibo.numero_recibo');

        // Formato esperado: {codigo_inventario}-NNNN, ej. "TST-INV-0001"
        $this->assertMatchesRegularExpression(
            '/^' . preg_quote($this->sede->codigo_inventario, '/') . '-\d{4}$/',
            $numeroRecibo,
            "El numero_recibo '{$numeroRecibo}' no comienza con el codigo_inventario de la sede '{$this->sede->codigo_inventario}'"
        );
    }

    /** @test */
    public function store_crea_pedido_activo_con_pago_parcial(): void
    {
        $abonoParcial = $this->precio / 2;

        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'monto_abono' => $abonoParcial,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => $abonoParcial]],
            ]))
            ->assertCreated();

        $this->assertEquals('activo', $response->json('data.status'));
        $this->assertEquals($abonoParcial, (float) $response->json('data.saldo'));
    }

    /** @test */
    public function store_falla_cuando_no_hay_precio_vigente(): void
    {
        $productoSinPrecio = InvProducto::factory()->create(['tipo' => 'simple']);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'items' => [['producto_id' => $productoSinPrecio->id, 'cantidad' => 1]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.producto_id'])
            ->assertJsonMissingValidationErrors(['sede_id']);

        $this->assertDatabaseCount('inv_pedidos', 0);
    }

    /** @test */
    public function store_falla_si_la_lista_de_precios_esta_inactiva(): void
    {
        $this->lista->update(['status' => LpListaPrecio::STATUS_INACTIVA]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sede_id']);

        $this->assertDatabaseCount('inv_pedidos', 0);
        $this->assertDatabaseCount('recibos_pago', 0);
    }

    /** @test */
    public function store_falla_si_la_lista_de_precios_esta_vencida(): void
    {
        $this->lista->update(['fecha_fin' => today()->subDay()->toDateString()]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sede_id']);
    }

    /** @test */
    public function store_falla_si_la_lista_no_cubre_la_poblacion_de_la_sede(): void
    {
        $otraSede = Sede::factory()->create(['poblacion_id' => Poblacion::factory()->create()->id]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido(['sede_id' => $otraSede->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sede_id']);
    }

    /** @test */
    public function store_acepta_la_lista_en_su_ultimo_dia_de_vigencia(): void
    {
        $this->lista->update(['fecha_fin' => today()->toDateString()]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido())
            ->assertCreated();
    }

    /** @test */
    public function store_falla_si_el_abono_supera_el_total_del_pedido(): void
    {
        $exceso = $this->precio + 10000;

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'monto_abono' => $exceso,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => $exceso]],
            ]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => "El abono ({$exceso}) supera el valor total del pedido ({$this->precio})."]);

        $this->assertDatabaseCount('inv_pedidos', 0);
    }

    /** @test */
    public function store_con_abono_parcial_no_entrega_aunque_se_pida_entrega_inmediata(): void
    {
        InvStock::create([
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => 10,
            'cantidad_reservada'  => 0,
            'cantidad_disponible' => 10,
        ]);

        $abono = $this->precio / 2;

        $pedidoId = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'monto_abono'       => $abono,
                'medios_pago'       => [['medio_pago' => 'efectivo', 'valor' => $abono]],
                'entrega_inmediata' => true,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'activo')
            ->json('data.id');

        // Sin pago completo no hay registros de entrega ni descarga de inventario
        $this->assertDatabaseCount('inv_entregas_simple', 0);
        $this->assertDatabaseHas('inv_stock', ['producto_id' => $this->producto->id, 'cantidad_total' => 10]);

        // Al completar el pago se despacha
        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.abonar', $pedidoId), [
                'monto_abono' => $abono,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => $abono]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'entregado');

        $this->assertDatabaseHas('inv_stock', ['producto_id' => $this->producto->id, 'cantidad_total' => 9]);
    }

    // ─── precios vigentes ─────────────────────────────────────────────────────

    /** @test */
    public function precios_vigentes_por_sede_devuelve_lista_y_precio_del_producto(): void
    {
        $this->actingAs($this->cajero)
            ->getJson(route('inv-ventas.precios-vigentes', ['sede_id' => $this->sede->id]))
            ->assertOk()
            ->assertJsonPath('data.disponible', true)
            ->assertJsonPath('data.listas.0.id', $this->lista->id)
            ->assertJsonPath('data.precios.0.producto.id', $this->producto->id)
            ->assertJsonCount(1, 'data.precios');
    }

    /** @test */
    public function precios_vigentes_ignora_listas_inactivas(): void
    {
        $this->lista->update(['status' => LpListaPrecio::STATUS_INACTIVA]);

        $this->actingAs($this->cajero)
            ->getJson(route('inv-ventas.precios-vigentes', ['sede_id' => $this->sede->id]))
            ->assertOk()
            ->assertJsonPath('data.disponible', false)
            ->assertJsonCount(0, 'data.listas')
            ->assertJsonCount(0, 'data.precios');
    }

    /** @test */
    public function precios_vigentes_sin_sede_indica_si_hay_alguna_lista_activa(): void
    {
        $this->actingAs($this->cajero)
            ->getJson(route('inv-ventas.precios-vigentes'))
            ->assertOk()
            ->assertJsonPath('data.disponible', true)
            ->assertJsonCount(0, 'data.precios');

        $this->lista->update(['status' => LpListaPrecio::STATUS_APROBADA]);

        $this->actingAs($this->cajero)
            ->getJson(route('inv-ventas.precios-vigentes'))
            ->assertOk()
            ->assertJsonPath('data.disponible', false);
    }

    /** @test */
    public function precios_vigentes_ignora_listas_academicas(): void
    {
        $this->lista->update(['origen' => 1]);

        $this->actingAs($this->cajero)
            ->getJson(route('inv-ventas.precios-vigentes', ['sede_id' => $this->sede->id]))
            ->assertOk()
            ->assertJsonPath('data.disponible', false);
    }

    /** @test */
    public function precios_vigentes_valida_que_la_sede_exista(): void
    {
        $this->actingAs($this->cajero)
            ->getJson(route('inv-ventas.precios-vigentes', ['sede_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sede_id']);
    }

    /** @test */
    public function precios_vigentes_deniega_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('inv-ventas.precios-vigentes'))
            ->assertForbidden();
    }

    /** @test */
    public function store_falla_si_suma_medios_pago_no_coincide(): void
    {
        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'monto_abono' => $this->precio,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => $this->precio / 2]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['medios_pago']);
    }

    /** @test */
    public function store_falla_sin_items(): void
    {
        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido(['items' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    /** @test */
    public function store_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido())
            ->assertForbidden();
    }

    // ─── abonar ───────────────────────────────────────────────────────────────

    /** @test */
    public function abonar_registra_pago_en_pedido_activo(): void
    {
        // Crear pedido activo directamente
        $pedido = InvPedido::create([
            'estudiante_id'   => $this->estudiante->id,
            'sede_id'         => $this->sede->id,
            'almacen_id'      => $this->almacen->id,
            'cajero_id'       => $this->cajero->id,
            'valor_total'     => 100000,
            'abono_acumulado' => 50000,
            'saldo'           => 50000,
            'status'          => InvPedido::STATUS_ACTIVO,
        ]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.abonar', $pedido), [
                'monto_abono' => 50000,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => 50000]],
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['status', 'saldo'], 'recibo' => ['id']]);

        $this->assertDatabaseHas('inv_pedidos', [
            'id'              => $pedido->id,
            'abono_acumulado' => 100000,
            'saldo'           => 0,
        ]);
    }

    /** @test */
    public function abonar_rechaza_pedido_no_activo(): void
    {
        $pedido = InvPedido::create([
            'estudiante_id'   => $this->estudiante->id,
            'sede_id'         => $this->sede->id,
            'almacen_id'      => $this->almacen->id,
            'cajero_id'       => $this->cajero->id,
            'valor_total'     => 100000,
            'abono_acumulado' => 100000,
            'saldo'           => 0,
            'status'          => InvPedido::STATUS_PAGADO,
        ]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.abonar', $pedido), [
                'monto_abono' => 10000,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => 10000]],
            ])
            ->assertUnprocessable();
    }

    /** @test */
    public function abonar_falla_si_monto_supera_saldo(): void
    {
        $pedido = InvPedido::create([
            'estudiante_id'   => $this->estudiante->id,
            'sede_id'         => $this->sede->id,
            'almacen_id'      => $this->almacen->id,
            'cajero_id'       => $this->cajero->id,
            'valor_total'     => 100000,
            'abono_acumulado' => 50000,
            'saldo'           => 50000,
            'status'          => InvPedido::STATUS_ACTIVO,
        ]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.abonar', $pedido), [
                'monto_abono' => 80000,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => 80000]],
            ])
            ->assertUnprocessable();
    }

    // ─── descuentos ───────────────────────────────────────────────────────────

    /** @test */
    public function store_aplica_descuento_porcentual_al_item(): void
    {
        $descuento = Descuento::factory()->create([
            'tipo_movimiento' => 'descuento',
            'tipo'            => 'porcentual',
            'valor'           => 10,  // 10%
            'status'          => Descuento::STATUS_ACTIVO,
            'fecha_inicio'    => today()->subDay(),
            'fecha_fin'       => today()->addMonth(),
        ]);

        $precioConDescuento = $this->precio * 0.90;
        $abono = $precioConDescuento;

        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'items'       => [['producto_id' => $this->producto->id, 'cantidad' => 1, 'descuento_id' => $descuento->id]],
                'monto_abono' => $abono,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => $abono]],
            ]))
            ->assertCreated();

        // El valor total debe reflejar el precio con descuento
        $this->assertEquals($precioConDescuento, (float) $response->json('data.valor_total'));
    }

    /** @test */
    public function store_descuento_inexistente_ignora_y_aplica_precio_lista(): void
    {
        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'items'       => [['producto_id' => $this->producto->id, 'cantidad' => 1, 'descuento_id' => 99999]],
                'monto_abono' => $this->precio,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => $this->precio]],
            ]))
            ->assertUnprocessable(); // Falla validación exists:descuentos,id
    }

    /** @test */
    public function abonar_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $pedido     = InvPedido::create([
            'estudiante_id'   => $this->estudiante->id,
            'sede_id'         => $this->sede->id,
            'almacen_id'      => $this->almacen->id,
            'cajero_id'       => $this->cajero->id,
            'valor_total'     => 100000,
            'abono_acumulado' => 0,
            'saldo'           => 100000,
            'status'          => InvPedido::STATUS_ACTIVO,
        ]);

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-ventas.abonar', $pedido), [
                'monto_abono' => 50000,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => 50000]],
            ])
            ->assertForbidden();
    }

    // ─── transferencias ───────────────────────────────────────────────────────

    /**
     * Registra stock del producto de pruebas en el almacén.
     *
     * @param int $cantidad
     * @return void
     */
    private function darStock(int $cantidad): void
    {
        InvStock::create([
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => $cantidad,
            'cantidad_reservada'  => 0,
            'cantidad_disponible' => $cantidad,
        ]);
    }

    /**
     * Crea una venta pagada por transferencia y devuelve [pedidoId, reciboId].
     *
     * @param float $monto
     * @return array{0: int, 1: int}
     */
    private function venderPorTransferencia(float $monto): array
    {
        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'monto_abono'       => $monto,
                'medios_pago'       => [['medio_pago' => 'transferencia', 'valor' => $monto]],
                'entrega_inmediata' => true,
            ]))
            ->assertCreated();

        return [$response->json('data.id'), $response->json('recibo.id')];
    }

    /** @test */
    public function store_por_transferencia_no_abona_ni_entrega_hasta_aprobar(): void
    {
        $this->darStock(10);

        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'medios_pago'       => [['medio_pago' => 'transferencia', 'valor' => $this->precio]],
                'entrega_inmediata' => true,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'activo')
            ->assertJsonPath('data.abono_pendiente_aprobacion', fn ($v) => (float) $v === $this->precio)
            ->assertJsonPath('recibo.status', ReciboPago::STATUS_PENDIENTE_APROBACION);

        $this->assertEquals(0, (float) $response->json('data.abono_acumulado'));
        $this->assertEquals($this->precio, (float) $response->json('data.saldo'));
        $this->assertDatabaseCount('inv_entregas_simple', 0);
        $this->assertDatabaseCount('inv_movimientos', 0);
        $this->assertDatabaseHas('inv_stock', ['producto_id' => $this->producto->id, 'cantidad_total' => 10]);
    }

    /** @test */
    public function aprobar_transferencia_salda_pedido_y_deja_la_entrega_pendiente_aunque_haya_stock(): void
    {
        Notification::fake();
        $this->darStock(10);

        [$pedidoId, $reciboId] = $this->venderPorTransferencia($this->precio);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.aprobar-transferencia', $reciboId))
            ->assertOk();

        $this->assertDatabaseHas('inv_pedidos', [
            'id'              => $pedidoId,
            'status'          => InvPedido::STATUS_PAGADO,
            'abono_acumulado' => $this->precio,
            'saldo'           => 0,
        ]);
        $this->assertDatabaseHas('inv_entregas_simple', ['status' => 'pendiente']);
        $this->assertDatabaseCount('inv_movimientos', 0);
        $this->assertDatabaseHas('inv_stock', ['producto_id' => $this->producto->id, 'cantidad_total' => 10]);
    }

    /** @test */
    public function aprobar_transferencia_parcial_suma_el_abono_y_deja_el_pedido_activo(): void
    {
        Notification::fake();
        $abono = $this->precio / 2;

        [$pedidoId, $reciboId] = $this->venderPorTransferencia($abono);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.aprobar-transferencia', $reciboId))
            ->assertOk();

        $this->assertDatabaseHas('inv_pedidos', [
            'id'              => $pedidoId,
            'status'          => InvPedido::STATUS_ACTIVO,
            'abono_acumulado' => $abono,
            'saldo'           => $abono,
        ]);
        $this->assertDatabaseCount('inv_entregas_simple', 0);
    }

    /** @test */
    public function abonar_por_transferencia_no_actualiza_el_pedido_hasta_aprobar(): void
    {
        Notification::fake();
        $this->darStock(10);
        $mitad = $this->precio / 2;

        $pedidoId = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'monto_abono' => $mitad,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => $mitad]],
            ]))
            ->assertCreated()
            ->json('data.id');

        $reciboId = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.abonar', $pedidoId), [
                'monto_abono'       => $mitad,
                'medios_pago'       => [['medio_pago' => 'transferencia', 'valor' => $mitad]],
                'entrega_inmediata' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'activo')
            ->assertJsonPath('data.abono_pendiente_aprobacion', fn ($v) => (float) $v === $mitad)
            ->json('recibo.id');

        $this->assertDatabaseHas('inv_pedidos', ['id' => $pedidoId, 'saldo' => $mitad]);
        $this->assertDatabaseCount('inv_entregas_simple', 0);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.aprobar-transferencia', $reciboId))
            ->assertOk();

        $this->assertDatabaseHas('inv_pedidos', ['id' => $pedidoId, 'status' => 'pagado', 'saldo' => 0]);
        $this->assertDatabaseHas('inv_entregas_simple', ['status' => 'pendiente']);
        $this->assertDatabaseHas('inv_stock', ['producto_id' => $this->producto->id, 'cantidad_total' => 10]);
    }

    /** @test */
    public function abonar_no_permite_cubrir_el_monto_de_una_transferencia_por_aprobar(): void
    {
        [$pedidoId] = $this->venderPorTransferencia($this->precio / 2);

        // Saldo 50.000, de los cuales 25.000 esperan aprobación: solo quedan 25.000 por abonar
        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.abonar', $pedidoId), [
                'monto_abono' => $this->precio,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => $this->precio]],
            ])
            ->assertUnprocessable();

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.abonar', $pedidoId), [
                'monto_abono' => $this->precio / 2,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => $this->precio / 2]],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'activo');
    }

    /** @test */
    public function rechazar_transferencia_no_modifica_el_pedido(): void
    {
        Notification::fake();
        [$pedidoId, $reciboId] = $this->venderPorTransferencia($this->precio);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.rechazar-transferencia', $reciboId), ['motivo_rechazo' => 'Comprobante ilegible'])
            ->assertOk();

        $this->assertDatabaseHas('inv_pedidos', [
            'id'              => $pedidoId,
            'status'          => InvPedido::STATUS_ACTIVO,
            'abono_acumulado' => 0,
            'saldo'           => $this->precio,
        ]);
    }

    /** @test */
    public function aprobar_transferencia_falla_si_el_pedido_fue_cancelado(): void
    {
        Notification::fake();
        [$pedidoId, $reciboId] = $this->venderPorTransferencia($this->precio);

        InvPedido::find($pedidoId)->update(['status' => InvPedido::STATUS_CANCELADO]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.aprobar-transferencia', $reciboId))
            ->assertUnprocessable();

        // La aprobación se revierte completa
        $this->assertDatabaseHas('recibos_pago', [
            'id'     => $reciboId,
            'status' => ReciboPago::STATUS_PENDIENTE_APROBACION,
        ]);
    }

    /** @test */
    public function el_endpoint_academico_no_aprueba_recibos_de_inventario(): void
    {
        Permission::create(['name' => 'fin_reciboPagoAprobar', 'descripcion' => 'aprobar recibos']);
        $this->cajero->givePermissionTo('fin_reciboPagoAprobar');

        [, $reciboId] = $this->venderPorTransferencia($this->precio);

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.aprobar-transferencia', $reciboId))
            ->assertUnprocessable();

        $this->assertDatabaseHas('recibos_pago', [
            'id'     => $reciboId,
            'status' => ReciboPago::STATUS_PENDIENTE_APROBACION,
        ]);
    }

    /** @test */
    public function pendientes_transferencia_filtra_por_origen(): void
    {
        Permission::create(['name' => 'fin_reciboPagoAprobar', 'descripcion' => 'aprobar recibos']);
        $this->cajero->givePermissionTo('fin_reciboPagoAprobar');

        $this->venderPorTransferencia($this->precio);

        $this->actingAs($this->cajero)
            ->getJson(route('recibos-pago.pendientes-transferencia', ['origen' => 0]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->cajero)
            ->getJson(route('recibos-pago.pendientes-transferencia', ['origen' => 1]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ─── anulación de recibos sueltos (módulo financiero) ────────────────────

    /** @test */
    public function anular_un_recibo_de_abono_descuenta_el_abono_del_pedido_activo(): void
    {
        Permission::create(['name' => 'fin_reciboPagoAnular', 'descripcion' => 'anular recibos']);
        $this->cajero->givePermissionTo('fin_reciboPagoAnular');
        $abono = $this->precio / 2;

        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido([
                'monto_abono' => $abono,
                'medios_pago' => [['medio_pago' => 'efectivo', 'valor' => $abono]],
            ]))
            ->assertCreated();

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.anular', $response->json('recibo.id')), ['motivo_anulacion' => 'Billete falso'])
            ->assertOk();

        $this->assertDatabaseHas('inv_pedidos', [
            'id'              => $response->json('data.id'),
            'status'          => 'activo',
            'abono_acumulado' => 0,
            'saldo'           => $this->precio,
        ]);
    }

    /** @test */
    public function anular_un_recibo_de_pedido_pagado_exige_anular_el_pedido(): void
    {
        Permission::create(['name' => 'fin_reciboPagoAnular', 'descripcion' => 'anular recibos']);
        $this->cajero->givePermissionTo('fin_reciboPagoAnular');

        $response = $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $this->payloadPedido(['entrega_inmediata' => false]))
            ->assertCreated();

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.anular', $response->json('recibo.id')), ['motivo_anulacion' => 'Error'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('recibos_pago', ['id' => $response->json('recibo.id'), 'status' => ReciboPago::STATUS_CREADO]);
    }
}
