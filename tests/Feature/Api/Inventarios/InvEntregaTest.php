<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Configuracion\Sede;
use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvNecesidadCompra;
use App\Models\Inventarios\InvPedido;
use App\Models\Inventarios\InvPedidoItem;
use App\Models\Inventarios\InvProducto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para la consulta de entregas pendientes y necesidades de compra.
 */
class InvEntregaTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;
    private Sede $sede;
    private InvAlmacen $almacen;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_entregas',          'descripcion' => 'ver entregas']);
        Permission::create(['name' => 'inv_entregasCompletar', 'descripcion' => 'completar entregas']);

        $this->cajero = User::factory()->create();
        $this->cajero->givePermissionTo(['inv_entregas', 'inv_entregasCompletar']);

        $this->sede    = Sede::factory()->create(['codigo_inventario' => 'TST-INV']);
        $this->almacen = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
    }

    /**
     * Crea un pedido con status y su ítem asociado.
     *
     * @param string $status
     * @return InvPedido
     */
    private function crearPedidoConItem(string $status = InvPedido::STATUS_ENTREGANDO): InvPedido
    {
        $estudiante = User::factory()->create();
        $producto   = InvProducto::factory()->create(['tipo' => 'simple']);

        $pedido = InvPedido::create([
            'estudiante_id'   => $estudiante->id,
            'sede_id'         => $this->sede->id,
            'almacen_id'      => $this->almacen->id,
            'cajero_id'       => $this->cajero->id,
            'valor_total'     => 50000,
            'abono_acumulado' => 50000,
            'saldo'           => 0,
            'status'          => $status,
        ]);

        InvPedidoItem::create([
            'pedido_id'       => $pedido->id,
            'producto_id'     => $producto->id,
            'cantidad'        => 1,
            'precio_unitario' => 50000,
            'subtotal'        => 50000,
        ]);

        return $pedido;
    }

    // ─── pendientes ───────────────────────────────────────────────────────────

    /** @test */
    public function pendientes_retorna_pedidos_en_entrega(): void
    {
        $this->crearPedidoConItem(InvPedido::STATUS_ENTREGANDO);
        $this->crearPedidoConItem(InvPedido::STATUS_PAGADO);
        $this->crearPedidoConItem(InvPedido::STATUS_ENTREGADO);

        $response = $this->actingAs($this->cajero)
            ->getJson(route('inv-entregas.pendientes'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'status']],
                'meta' => ['total'],
            ]);

        // Solo los 2 pendientes (entregando + pagado)
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function pendientes_filtra_por_almacen(): void
    {
        $otroAlmacen = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        $this->crearPedidoConItem(InvPedido::STATUS_ENTREGANDO);

        $response = $this->actingAs($this->cajero)
            ->getJson(route('inv-entregas.pendientes') . '?almacen_id=' . $otroAlmacen->id)
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    /** @test */
    public function pendientes_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('inv-entregas.pendientes'))
            ->assertForbidden();
    }

    // ─── necesidades ──────────────────────────────────────────────────────────

    /** @test */
    public function necesidades_retorna_lista_de_necesidades_pendientes(): void
    {
        $estudiante = User::factory()->create();
        $producto   = InvProducto::factory()->create(['tipo' => 'simple']);

        InvNecesidadCompra::create([
            'producto_id'        => $producto->id,
            'cantidad_necesaria' => 2,
            'almacen_id'         => $this->almacen->id,
            'estudiante_id'      => $estudiante->id,
            'entregable_type'    => 'App\\Models\\Inventarios\\InvEntregaSimple',
            'entregable_id'      => 1,
            'status'             => InvNecesidadCompra::STATUS_PENDIENTE,
        ]);

        // Necesidad ya atendida (no debe aparecer)
        InvNecesidadCompra::create([
            'producto_id'        => $producto->id,
            'cantidad_necesaria' => 1,
            'almacen_id'         => $this->almacen->id,
            'estudiante_id'      => $estudiante->id,
            'entregable_type'    => 'App\\Models\\Inventarios\\InvEntregaSimple',
            'entregable_id'      => 2,
            'status'             => InvNecesidadCompra::STATUS_ATENDIDA,
        ]);

        $response = $this->actingAs($this->cajero)
            ->getJson(route('inv-entregas.necesidades'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('pendiente', $response->json('data.0.status'));
    }

    /** @test */
    public function necesidades_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('inv-entregas.necesidades'))
            ->assertForbidden();
    }

    // ─── completarSimple ─── (deniega sin permiso) ────────────────────────────

    /** @test */
    public function completar_simple_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-entregas.completar-simple', 1))
            ->assertForbidden();
    }

    /** @test */
    public function completar_simple_retorna_404_para_entrega_inexistente(): void
    {
        $this->actingAs($this->cajero)
            ->postJson(route('inv-entregas.completar-simple', 9999))
            ->assertNotFound();
    }

    // ─── completarKit ─── (deniega sin permiso) ───────────────────────────────

    /** @test */
    public function completar_kit_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-entregas.completar-kit', 1), [
                'componentes' => [['kit_componente_id' => 1]],
            ])
            ->assertForbidden();
    }
}
