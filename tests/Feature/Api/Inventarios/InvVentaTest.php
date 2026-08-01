<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvPedido;
use App\Models\Inventarios\InvPrecioProducto;
use App\Models\Inventarios\InvProducto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $lista = LpListaPrecio::factory()->create([
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
        $this->actingAs($this->cajero)
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

        // Recibo de pago generado con número de inventario
        $this->assertDatabaseHas('recibos_pago', [
            'origen'  => 0,
            'sede_id' => $this->sede->id,
        ]);
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
            ->assertUnprocessable();
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
                'medios_pago' => [['medio_pago' => 'transferencia', 'valor' => 50000]],
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
}
