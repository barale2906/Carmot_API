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
use App\Models\Inventarios\InvNecesidadCompra;
use App\Models\Inventarios\InvPedido;
use App\Models\Inventarios\InvPrecioProducto;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;
use App\Models\User;
use App\Services\Inventarios\InvNecesidadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature del ciclo de vida de las necesidades de compra: creación al vender,
 * actualización al elegir variante, cierre al entregar, reparto al recibir stock y
 * cancelación al anular el pedido.
 */
class InvNecesidadCompraTest extends TestCase
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
        Permission::create(['name' => 'inv_entregas',          'descripcion' => 'ver entregas']);
        Permission::create(['name' => 'inv_entregasCompletar', 'descripcion' => 'completar entregas']);
        Permission::create(['name' => 'inv_pedidosAnular',     'descripcion' => 'anular pedidos']);
        Permission::create(['name' => 'inv_movimientosRegistrar', 'descripcion' => 'registrar movimientos']);

        $poblacion = Poblacion::factory()->create();

        $this->sede = Sede::factory()->create([
            'codigo_inventario' => 'TST-NEC',
            'poblacion_id'      => $poblacion->id,
        ]);

        $this->almacen    = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        $this->estudiante = User::factory()->create();

        $this->cajero = User::factory()->create();
        $this->cajero->givePermissionTo([
            'inv_ventasCrear', 'inv_entregas', 'inv_entregasCompletar', 'inv_pedidosAnular',
            'inv_movimientosRegistrar',
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
     * Kit de 100.000 con un componente de tipo grupo "Camisas" (2 unidades) y sus tallas M y L.
     *
     * @return array{kit: InvProducto, grupo: InvProducto, tallaM: InvProducto, tallaL: InvProducto, comp: InvKitComponente}
     */
    private function crearKitConGrupo(): array
    {
        $kit = InvProducto::factory()->kit()->create();
        InvPrecioProducto::asignar($this->lista->id, $kit->id, 100000);

        $grupo  = InvProducto::factory()->grupo()->create(['nombre' => 'Camisas']);
        $tallaM = InvProducto::factory()->create(['producto_padre_id' => $grupo->id, 'status' => 1]);
        $tallaL = InvProducto::factory()->create(['producto_padre_id' => $grupo->id, 'status' => 1]);

        $comp = InvKitComponente::create([
            'kit_id' => $kit->id, 'grupo_producto_id' => $grupo->id, 'cantidad' => 2, 'orden' => 1,
        ]);

        return compact('kit', 'grupo', 'tallaM', 'tallaL', 'comp');
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
        InvStock::updateOrCreate(
            ['almacen_id' => $this->almacen->id, 'producto_id' => $producto->id],
            ['cantidad_total' => $cantidad, 'cantidad_reservada' => 0, 'cantidad_disponible' => $cantidad]
        );
    }

    /**
     * Vende de contado un producto, opcionalmente indicando las variantes del kit.
     *
     * @param InvProducto $producto
     * @param float       $total
     * @param array       $variantesKit  Componentes [{kit_componente_id, producto_entregado_id}]
     * @param int         $cantidad
     * @return InvPedido
     */
    private function vender(InvProducto $producto, float $total, array $variantesKit = [], int $cantidad = 1): InvPedido
    {
        $payload = [
            'estudiante_id' => $this->estudiante->id,
            'sede_id'       => $this->sede->id,
            'almacen_id'    => $this->almacen->id,
            'items'         => [['producto_id' => $producto->id, 'cantidad' => $cantidad]],
            'monto_abono'   => $total,
            'medios_pago'   => [['medio_pago' => 'efectivo', 'valor' => $total]],
        ];

        if ($variantesKit) {
            $payload['variantes_kit'] = [['item_index' => 0, 'componentes' => $variantesKit]];
        }

        $this->actingAs($this->cajero)
            ->postJson(route('inv-ventas.store'), $payload)
            ->assertCreated();

        return InvPedido::latest('id')->firstOrFail();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, InvNecesidadCompra>
     */
    private function necesidadesPendientes()
    {
        return InvNecesidadCompra::where('status', InvNecesidadCompra::STATUS_PENDIENTE)->get();
    }

    // ─── creación al vender ───────────────────────────────────────────────────

    /** @test */
    public function venta_de_kit_entregada_completa_no_deja_necesidades_del_grupo(): void
    {
        $k = $this->crearKitConGrupo();
        $this->darStock($k['tallaM'], 10);

        $this->vender($k['kit'], 100000, [
            ['kit_componente_id' => $k['comp']->id, 'producto_entregado_id' => $k['tallaM']->id],
        ]);

        $this->assertEquals(InvEntregaKit::STATUS_COMPLETO, InvEntregaKit::firstOrFail()->status);
        // Con la variante disponible no hay nada que comprar: ni siquiera un registro atendido.
        $this->assertDatabaseCount('inv_necesidades_compra', 0);
    }

    /** @test */
    public function venta_de_kit_sin_stock_genera_una_sola_necesidad_por_componente_a_nombre_de_la_variante(): void
    {
        $k = $this->crearKitConGrupo();

        $this->vender($k['kit'], 100000, [
            ['kit_componente_id' => $k['comp']->id, 'producto_entregado_id' => $k['tallaL']->id],
        ]);

        $pendientes = $this->necesidadesPendientes();

        $this->assertCount(1, $pendientes);
        $this->assertEquals($k['tallaL']->id, $pendientes->first()->producto_id);
        $this->assertEquals(2, $pendientes->first()->cantidad_necesaria);
    }

    /** @test */
    public function necesidad_del_grupo_se_reemplaza_por_la_variante_al_elegirla_despues(): void
    {
        $k = $this->crearKitConGrupo();

        // Venta sin variante elegida: la necesidad queda a nombre del grupo.
        $this->vender($k['kit'], 100000);
        $this->assertEquals($k['grupo']->id, $this->necesidadesPendientes()->sole()->producto_id);

        // El cajero elige la talla desde Entregas Pendientes, pero aún no hay stock.
        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.entregar-componentes', InvEntregaKit::firstOrFail()->id), [
                'componentes' => [[
                    'kit_componente_id'     => $k['comp']->id,
                    'producto_entregado_id' => $k['tallaM']->id,
                ]],
            ])
            ->assertOk();

        $necesidad = $this->necesidadesPendientes()->sole();
        $this->assertEquals($k['tallaM']->id, $necesidad->producto_id);
        $this->assertEquals(2, $necesidad->cantidad_necesaria);
    }

    // ─── cierre al entregar ───────────────────────────────────────────────────

    /** @test */
    public function entregar_el_componente_cierra_su_necesidad(): void
    {
        $k = $this->crearKitConGrupo();
        $this->vender($k['kit'], 100000, [
            ['kit_componente_id' => $k['comp']->id, 'producto_entregado_id' => $k['tallaM']->id],
        ]);
        $this->assertCount(1, $this->necesidadesPendientes());

        $this->darStock($k['tallaM'], 5);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.entregar-componentes', InvEntregaKit::firstOrFail()->id), [
                'componentes' => [['kit_componente_id' => $k['comp']->id]],
            ])
            ->assertOk();

        $this->assertEquals(
            InvEntregaKitComponente::STATUS_ENTREGADO,
            InvEntregaKitComponente::firstOrFail()->status
        );
        $this->assertCount(0, $this->necesidadesPendientes());
    }

    /** @test */
    public function completar_entrega_simple_cierra_su_necesidad(): void
    {
        $producto = InvProducto::factory()->create();
        InvPrecioProducto::asignar($this->lista->id, $producto->id, 50000);

        $this->vender($producto, 100000, [], 2);
        $this->assertEquals(2, $this->necesidadesPendientes()->sole()->cantidad_necesaria);

        $this->darStock($producto, 5);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.completar-simple', InvEntregaSimple::firstOrFail()->id))
            ->assertOk();

        $this->assertCount(0, $this->necesidadesPendientes());
    }

    // ─── reparto al recibir stock ─────────────────────────────────────────────

    /** @test */
    public function stock_recibido_se_reparte_entre_necesidades_por_orden_de_antiguedad(): void
    {
        $producto = InvProducto::factory()->create();

        $crear = fn (int $entregableId, string $fecha) => InvNecesidadCompra::create([
            'producto_id'        => $producto->id,
            'almacen_id'         => $this->almacen->id,
            'estudiante_id'      => $this->estudiante->id,
            'cantidad_necesaria' => 2,
            'entregable_type'    => InvEntregaSimple::class,
            'entregable_id'      => $entregableId,
            'status'             => InvNecesidadCompra::STATUS_PENDIENTE,
            'notificado'         => false,
            'created_at'         => $fecha,
        ]);

        $masReciente = $crear(1, now()->toDateTimeString());
        $masAntigua  = $crear(2, now()->subDay()->toDateTimeString());

        // Llegan 3 unidades: alcanzan para una necesidad de 2, no para ambas.
        $this->darStock($producto, 3);
        InvNecesidadService::verificarPendientesCubiertos($producto->id, $this->almacen->id);

        $this->assertEquals(InvNecesidadCompra::STATUS_ATENDIDA, $masAntigua->fresh()->status);
        $this->assertEquals(InvNecesidadCompra::STATUS_PENDIENTE, $masReciente->fresh()->status);
    }

    /**
     * Vende un producto simple sin stock para dejar una necesidad pendiente de $cantidad.
     *
     * @param int $cantidad
     * @return InvProducto
     */
    private function venderSimpleSinStock(int $cantidad): InvProducto
    {
        $producto = InvProducto::factory()->create();
        InvPrecioProducto::asignar($this->lista->id, $producto->id, 10000);
        $this->vender($producto, 10000 * $cantidad, [], $cantidad);
        $this->assertCount(1, $this->necesidadesPendientes());

        return $producto;
    }

    /** @test */
    public function entrada_manual_de_inventario_atiende_la_necesidad(): void
    {
        $producto = $this->venderSimpleSinStock(3);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-movimientos.store'), [
                'tipo_documento' => 'entrada',
                'almacen_id'     => $this->almacen->id,
                'lineas'         => [['producto_id' => $producto->id, 'cantidad' => 3]],
            ])
            ->assertCreated();

        $this->assertCount(0, $this->necesidadesPendientes());
    }

    /** @test */
    public function traslado_atiende_la_necesidad_del_almacen_destino(): void
    {
        $producto = $this->venderSimpleSinStock(2);

        $bodega = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        InvStock::create([
            'almacen_id' => $bodega->id, 'producto_id' => $producto->id,
            'cantidad_total' => 5, 'cantidad_reservada' => 0, 'cantidad_disponible' => 5,
        ]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-movimientos.store'), [
                'tipo_documento'     => 'traslado',
                'almacen_id'         => $bodega->id,
                'almacen_destino_id' => $this->almacen->id,
                'lineas'             => [['producto_id' => $producto->id, 'cantidad' => 2]],
            ])
            ->assertCreated();

        $this->assertCount(0, $this->necesidadesPendientes());
    }

    /** @test */
    public function entrada_insuficiente_no_atiende_la_necesidad(): void
    {
        $producto = $this->venderSimpleSinStock(3);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-movimientos.store'), [
                'tipo_documento' => 'entrada',
                'almacen_id'     => $this->almacen->id,
                'lineas'         => [['producto_id' => $producto->id, 'cantidad' => 2]],
            ])
            ->assertCreated();

        $this->assertEquals(3, $this->necesidadesPendientes()->sole()->cantidad_necesaria);
    }

    /** @test */
    public function anular_pedido_no_atiende_sus_propias_necesidades_con_el_stock_reintegrado(): void
    {
        // Se entrega 1 de 2 unidades: queda una necesidad de 1.
        $producto = InvProducto::factory()->create();
        InvPrecioProducto::asignar($this->lista->id, $producto->id, 10000);
        $this->darStock($producto, 1);
        $pedido = $this->vender($producto, 20000, [], 2);
        $necesidad = $this->necesidadesPendientes()->sole();
        $this->assertEquals(1, $necesidad->cantidad_necesaria);

        // Al anular se reintegra 1 unidad, que alcanzaría para "atender" la necesidad.
        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.anular', $pedido))
            ->assertOk();

        $this->assertEquals(InvNecesidadCompra::STATUS_CANCELADA, $necesidad->fresh()->status);
    }

    // ─── depuración de datos históricos ───────────────────────────────────────

    /** @test */
    public function migracion_depura_necesidades_duplicadas_y_fantasma(): void
    {
        $k = $this->crearKitConGrupo();

        // Pedido sin stock y pedido entregado, ambos con variante elegida.
        $this->vender($k['kit'], 100000, [
            ['kit_componente_id' => $k['comp']->id, 'producto_entregado_id' => $k['tallaL']->id],
        ]);
        $this->darStock($k['tallaM'], 10);
        $this->vender($k['kit'], 100000, [
            ['kit_componente_id' => $k['comp']->id, 'producto_entregado_id' => $k['tallaM']->id],
        ]);

        [$lineaSinStock, $lineaEntregada] = InvEntregaKitComponente::orderBy('id')->get();

        // Reproduce lo que dejaba el cálculo anterior: necesidad del grupo en ambas
        // líneas, más la de la variante en la línea sin stock.
        $base = [
            'almacen_id'         => $this->almacen->id,
            'estudiante_id'      => $this->estudiante->id,
            'cantidad_necesaria' => 2,
            'entregable_type'    => InvEntregaKitComponente::class,
            'status'             => InvNecesidadCompra::STATUS_PENDIENTE,
            'notificado'         => false,
        ];
        InvNecesidadCompra::query()->delete();
        $grupoDuplicada = InvNecesidadCompra::create($base + ['producto_id' => $k['grupo']->id, 'entregable_id' => $lineaSinStock->id]);
        $grupoFantasma  = InvNecesidadCompra::create($base + ['producto_id' => $k['grupo']->id, 'entregable_id' => $lineaEntregada->id]);
        $real           = InvNecesidadCompra::create($base + ['producto_id' => $k['tallaL']->id, 'entregable_id' => $lineaSinStock->id]);

        (require database_path('migrations/2026_09_29_000001_depura_inv_necesidades_compra_duplicadas.php'))->up();

        $this->assertEquals(InvNecesidadCompra::STATUS_CANCELADA, $grupoDuplicada->fresh()->status);
        $this->assertEquals(InvNecesidadCompra::STATUS_ATENDIDA, $grupoFantasma->fresh()->status);
        $this->assertEquals(InvNecesidadCompra::STATUS_PENDIENTE, $real->fresh()->status);
    }

    // ─── cancelación al anular ────────────────────────────────────────────────

    /** @test */
    public function anular_pedido_cancela_sus_necesidades_pendientes(): void
    {
        $k = $this->crearKitConGrupo();
        $pedido = $this->vender($k['kit'], 100000, [
            ['kit_componente_id' => $k['comp']->id, 'producto_entregado_id' => $k['tallaL']->id],
        ]);
        $this->assertCount(1, $this->necesidadesPendientes());

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.anular', $pedido))
            ->assertOk();

        $this->assertCount(0, $this->necesidadesPendientes());
        $this->assertDatabaseHas('inv_necesidades_compra', ['status' => InvNecesidadCompra::STATUS_CANCELADA]);
    }
}
