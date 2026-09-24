<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Configuracion\Sede;
use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvPedido;
use App\Models\Inventarios\InvPedidoItem;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para la consulta y gestión de pedidos de inventario.
 */
class InvPedidoTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;
    private Sede $sede;
    private InvAlmacen $almacen;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_pedidos',         'descripcion' => 'ver pedidos']);
        Permission::create(['name' => 'inv_pedidosCancelar', 'descripcion' => 'cancelar pedidos']);
        Permission::create(['name' => 'inv_pedidosAnular',   'descripcion' => 'anular pedidos']);

        $this->cajero  = User::factory()->create();
        $this->cajero->givePermissionTo(['inv_pedidos', 'inv_pedidosCancelar', 'inv_pedidosAnular']);

        $this->sede    = Sede::factory()->create(['codigo_inventario' => 'TUN-INV']);
        $this->almacen = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
    }

    /**
     * Crea un pedido en DB de forma directa (sin pasar por el servicio).
     *
     * @param array $override
     * @return InvPedido
     */
    private function crearPedido(array $override = []): InvPedido
    {
        $estudiante = User::factory()->create();

        return InvPedido::create(array_merge([
            'estudiante_id'   => $estudiante->id,
            'sede_id'         => $this->sede->id,
            'almacen_id'      => $this->almacen->id,
            'cajero_id'       => $this->cajero->id,
            'valor_total'     => 100000,
            'abono_acumulado' => 0,
            'saldo'           => 100000,
            'status'          => InvPedido::STATUS_ACTIVO,
        ], $override));
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_pedidos(): void
    {
        $this->crearPedido();
        $this->crearPedido(['status' => InvPedido::STATUS_PAGADO, 'abono_acumulado' => 100000, 'saldo' => 0]);

        $this->actingAs($this->cajero)
            ->getJson(route('inv-pedidos.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'valor_total', 'saldo', 'status']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /** @test */
    public function index_filtra_por_status(): void
    {
        $this->crearPedido(['status' => InvPedido::STATUS_ACTIVO]);
        $this->crearPedido(['status' => InvPedido::STATUS_PAGADO, 'abono_acumulado' => 100000, 'saldo' => 0]);

        $response = $this->actingAs($this->cajero)
            ->getJson(route('inv-pedidos.index') . '?status=activo')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('activo', $response->json('data.0.status'));
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('inv-pedidos.index'))
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_detalle_completo_del_pedido(): void
    {
        $pedido = $this->crearPedido();

        $this->actingAs($this->cajero)
            ->getJson(route('inv-pedidos.show', $pedido))
            ->assertOk()
            ->assertJsonFragment(['id' => $pedido->id, 'status' => 'activo']);
    }

    // ─── porEstudiante ────────────────────────────────────────────────────────

    /** @test */
    public function por_estudiante_retorna_pedidos_del_estudiante(): void
    {
        $estudiante = User::factory()->create();
        InvPedido::create([
            'estudiante_id'   => $estudiante->id,
            'sede_id'         => $this->sede->id,
            'almacen_id'      => $this->almacen->id,
            'cajero_id'       => $this->cajero->id,
            'valor_total'     => 50000,
            'abono_acumulado' => 0,
            'saldo'           => 50000,
            'status'          => InvPedido::STATUS_ACTIVO,
        ]);

        // Otro estudiante, no debe aparecer
        $this->crearPedido();

        $response = $this->actingAs($this->cajero)
            ->getJson(route('inv-pedidos.por-estudiante', $estudiante->id))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    // ─── cancelar ─────────────────────────────────────────────────────────────

    /** @test */
    public function cancelar_cambia_status_de_pedido_activo(): void
    {
        $pedido = $this->crearPedido(['status' => InvPedido::STATUS_ACTIVO]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.cancelar', $pedido))
            ->assertOk()
            ->assertJsonFragment(['status' => InvPedido::STATUS_CANCELADO]);

        $this->assertDatabaseHas('inv_pedidos', ['id' => $pedido->id, 'status' => 'cancelado']);
    }

    /** @test */
    public function cancelar_rechaza_pedido_ya_pagado(): void
    {
        $pedido = $this->crearPedido([
            'status'          => InvPedido::STATUS_PAGADO,
            'abono_acumulado' => 100000,
            'saldo'           => 0,
        ]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.cancelar', $pedido))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Solo se pueden cancelar pedidos activos.']);
    }

    /** @test */
    public function cancelar_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $pedido     = $this->crearPedido();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-pedidos.cancelar', $pedido))
            ->assertForbidden();
    }

    // ─── anular ───────────────────────────────────────────────────────────────

    /** @test */
    public function anular_cancela_pedido_activo_sin_reintegro(): void
    {
        $pedido = $this->crearPedido(['status' => InvPedido::STATUS_ACTIVO]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.anular', $pedido))
            ->assertOk()
            ->assertJsonFragment(['status' => InvPedido::STATUS_CANCELADO]);

        $this->assertDatabaseHas('inv_pedidos', ['id' => $pedido->id, 'status' => 'cancelado']);
        // Pedido activo no generó movimientos de stock, no debe haber documento de devolución
        $this->assertDatabaseMissing('inv_documentos_movimiento', ['pedido_id' => $pedido->id, 'tipo_documento' => 'devolucion']);
    }

    /** @test */
    public function anular_reintegra_stock_de_entregas_completadas(): void
    {
        $producto = InvProducto::factory()->create(['tipo' => 'simple']);

        // Pre-cargar stock para que la entrega haya funcionado
        InvStock::create([
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $producto->id,
            'cantidad_total'      => 0,
            'cantidad_reservada'  => 0,
            'cantidad_disponible' => 0,
        ]);

        $pedido = $this->crearPedido(['status' => InvPedido::STATUS_ENTREGANDO]);

        $item = InvPedidoItem::create([
            'pedido_id'       => $pedido->id,
            'producto_id'     => $producto->id,
            'cantidad'        => 2,
            'precio_unitario' => 5000,
            'subtotal'        => 10000,
        ]);

        InvEntregaSimple::create([
            'pedido_item_id'     => $item->id,
            'producto_id'        => $producto->id,
            'cantidad_entregada' => 2,
            'status'             => InvEntregaSimple::STATUS_ENTREGADO,
            'fecha_entrega'      => now(),
        ]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.anular', $pedido))
            ->assertOk()
            ->assertJsonFragment(['status' => InvPedido::STATUS_CANCELADO]);

        // Debe haberse creado un documento de devolución
        $this->assertDatabaseHas('inv_documentos_movimiento', [
            'pedido_id'     => $pedido->id,
            'tipo_documento' => 'devolucion',
        ]);

        // El stock debe haber sido reintegrado
        $this->assertDatabaseHas('inv_stock', [
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $producto->id,
            'cantidad_disponible' => 2,
        ]);
    }

    /** @test */
    public function anular_rechaza_pedido_ya_cancelado(): void
    {
        $pedido = $this->crearPedido(['status' => InvPedido::STATUS_CANCELADO]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.anular', $pedido))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'El pedido ya está cancelado.']);
    }

    /** @test */
    public function anular_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $pedido     = $this->crearPedido();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-pedidos.anular', $pedido))
            ->assertForbidden();
    }

    // ─── ticket-pdf ───────────────────────────────────────────────────────────

    /** @test */
    public function ticket_pdf_retorna_respuesta_pdf(): void
    {
        $pedido = $this->crearPedido(['status' => InvPedido::STATUS_PAGADO]);

        $this->actingAs($this->cajero)
            ->get(route('inv-pedidos.ticket-pdf', $pedido))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function ticket_pdf_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $pedido     = $this->crearPedido();

        $this->actingAs($sinPermiso)
            ->get(route('inv-pedidos.ticket-pdf', $pedido))
            ->assertForbidden();
    }
}
