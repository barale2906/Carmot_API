<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvPedido;
use App\Models\Inventarios\InvPedidoItem;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;
use App\Models\Inventarios\ReciboPagoInvPedido;
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
            ->postJson(route('inv-pedidos.cancelar', $pedido), ['motivo' => 'Solicitud del estudiante'])
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
            ->postJson(route('inv-pedidos.cancelar', $pedido), ['motivo' => 'Solicitud del estudiante'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Solo se pueden cancelar pedidos activos.']);
    }

    /** @test */
    public function cancelar_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $pedido     = $this->crearPedido();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-pedidos.cancelar', $pedido), ['motivo' => 'Solicitud del estudiante'])
            ->assertForbidden();
    }

    // ─── anular ───────────────────────────────────────────────────────────────

    /** @test */
    public function anular_cancela_pedido_activo_sin_reintegro(): void
    {
        $pedido = $this->crearPedido(['status' => InvPedido::STATUS_ACTIVO]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.anular', $pedido), ['motivo' => 'Solicitud del estudiante'])
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
            ->postJson(route('inv-pedidos.anular', $pedido), ['motivo' => 'Solicitud del estudiante'])
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
            ->postJson(route('inv-pedidos.anular', $pedido), ['motivo' => 'Solicitud del estudiante'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'El pedido ya está cancelado.']);
    }

    /** @test */
    public function anular_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $pedido     = $this->crearPedido();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-pedidos.anular', $pedido), ['motivo' => 'Solicitud del estudiante'])
            ->assertForbidden();
    }

    /**
     * Vincula al pedido un recibo de inventario con el status indicado.
     *
     * @param InvPedido $pedido
     * @param int       $status
     * @param float     $monto
     * @return ReciboPago
     */
    private function vincularRecibo(InvPedido $pedido, int $status, float $monto = 40000): ReciboPago
    {
        $recibo = ReciboPago::factory()->create([
            'origen'        => ReciboPago::ORIGEN_INVENTARIOS,
            'sede_id'       => $this->sede->id,
            'estudiante_id' => $pedido->estudiante_id,
            'matricula_id'  => null,
            'valor_total'   => $monto,
            'status'        => $status,
            'cierre'        => $status === ReciboPago::STATUS_CERRADO ? 1 : null,
        ]);

        ReciboPagoInvPedido::create([
            'recibo_pago_id' => $recibo->id,
            'pedido_id'      => $pedido->id,
            'monto_abonado'  => $monto,
        ]);

        return $recibo;
    }

    /** @test */
    public function cancelar_exige_motivo(): void
    {
        $pedido = $this->crearPedido();

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.cancelar', $pedido), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo']);

        $this->assertDatabaseHas('inv_pedidos', ['id' => $pedido->id, 'status' => 'activo']);
    }

    /** @test */
    public function cancelar_anula_los_recibos_y_registra_motivo_usuario_y_fecha(): void
    {
        $pedido    = $this->crearPedido(['abono_acumulado' => 40000, 'saldo' => 60000]);
        $abono     = $this->vincularRecibo($pedido, ReciboPago::STATUS_CREADO);
        $pendiente = $this->vincularRecibo($pedido, ReciboPago::STATUS_PENDIENTE_APROBACION, 20000);
        $previo    = $this->vincularRecibo($pedido, ReciboPago::STATUS_ANULADO, 10000);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.cancelar', $pedido), ['motivo' => 'El estudiante desistió'])
            ->assertOk()
            ->assertJsonPath('data.motivo_cancelacion', 'El estudiante desistió')
            ->assertJsonPath('data.cancelado_por.id', $this->cajero->id)
            ->assertJsonFragment(['message' => 'Pedido cancelado. Se anularon 2 recibo(s): devuelve el dinero al estudiante.']);

        $this->assertDatabaseHas('inv_pedidos', [
            'id'                 => $pedido->id,
            'status'             => 'cancelado',
            'motivo_cancelacion' => 'El estudiante desistió',
            'cancelado_por_id'   => $this->cajero->id,
        ]);
        $this->assertNotNull($pedido->fresh()->fecha_cancelacion);

        foreach ([$abono, $pendiente] as $recibo) {
            $this->assertSame(ReciboPago::STATUS_ANULADO, $recibo->fresh()->status);
            $this->assertStringContainsString("pedido de inventario #{$pedido->id}", $recibo->fresh()->motivo_anulacion);
        }
        // El ya anulado conserva su motivo original
        $this->assertSame($previo->motivo_anulacion, $previo->fresh()->motivo_anulacion);
    }

    /** @test */
    public function anular_se_rechaza_si_algun_recibo_esta_cerrado_en_caja(): void
    {
        $pedido  = $this->crearPedido(['status' => InvPedido::STATUS_PAGADO, 'abono_acumulado' => 100000, 'saldo' => 0]);
        $abierto = $this->vincularRecibo($pedido, ReciboPago::STATUS_CREADO);
        $cerrado = $this->vincularRecibo($pedido, ReciboPago::STATUS_CERRADO, 60000);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.anular', $pedido), ['motivo' => 'Error de digitación'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => "No se puede anular el pedido: los recibos {$cerrado->numero_recibo} ya están cerrados en caja."]);

        $this->assertDatabaseHas('inv_pedidos', ['id' => $pedido->id, 'status' => 'pagado']);
        $this->assertSame(ReciboPago::STATUS_CREADO, $abierto->fresh()->status);
    }

    /** @test */
    public function anular_reintegra_tambien_las_entregas_parciales(): void
    {
        $producto = InvProducto::factory()->create(['tipo' => 'simple']);

        InvStock::create([
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $producto->id,
            'cantidad_total'      => 0,
            'cantidad_reservada'  => 0,
            'cantidad_disponible' => 0,
        ]);

        $pedido = $this->crearPedido(['status' => InvPedido::STATUS_ENTREGANDO, 'abono_acumulado' => 100000, 'saldo' => 0]);
        $item   = InvPedidoItem::create([
            'pedido_id'       => $pedido->id,
            'producto_id'     => $producto->id,
            'cantidad'        => 5,
            'precio_unitario' => 20000,
            'subtotal'        => 100000,
        ]);

        InvEntregaSimple::create([
            'pedido_item_id'     => $item->id,
            'producto_id'        => $producto->id,
            'cantidad_entregada' => 3,
            'status'             => InvEntregaSimple::STATUS_PARCIAL,
            'fecha_entrega'      => now(),
        ]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-pedidos.anular', $pedido), ['motivo' => 'Producto defectuoso'])
            ->assertOk();

        $this->assertDatabaseHas('inv_stock', [
            'producto_id'         => $producto->id,
            'cantidad_disponible' => 3,
        ]);
    }

    /** @test */
    public function las_entregas_de_un_pedido_anulado_no_se_pueden_despachar(): void
    {
        Permission::create(['name' => 'inv_entregasCompletar', 'descripcion' => 'completar entregas']);
        $this->cajero->givePermissionTo('inv_entregasCompletar');

        $producto = InvProducto::factory()->create(['tipo' => 'simple']);
        $pedido   = $this->crearPedido(['status' => InvPedido::STATUS_CANCELADO]);
        $item     = InvPedidoItem::create([
            'pedido_id'       => $pedido->id,
            'producto_id'     => $producto->id,
            'cantidad'        => 1,
            'precio_unitario' => 100000,
            'subtotal'        => 100000,
        ]);
        $entrega = InvEntregaSimple::create([
            'pedido_item_id'     => $item->id,
            'producto_id'        => $producto->id,
            'cantidad_entregada' => 0,
            'status'             => InvEntregaSimple::STATUS_PENDIENTE,
        ]);

        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.completar-simple', $entrega->id))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => "El pedido #{$pedido->id} está cancelado: no admite entregas."]);

        $this->assertDatabaseHas('inv_pedidos', ['id' => $pedido->id, 'status' => 'cancelado']);
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
