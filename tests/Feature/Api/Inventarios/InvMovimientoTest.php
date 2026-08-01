<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvDocumentoMovimiento;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el registro y gestión de movimientos de inventario.
 */
class InvMovimientoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private InvAlmacen $almacen;
    private InvAlmacen $almacenDestino;
    private InvProducto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_movimientos',          'descripcion' => 'ver movimientos']);
        Permission::create(['name' => 'inv_movimientosRegistrar', 'descripcion' => 'registrar movimientos']);
        Permission::create(['name' => 'inv_movimientosAnular',    'descripcion' => 'anular movimientos']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'inv_movimientos',
            'inv_movimientosRegistrar',
            'inv_movimientosAnular',
        ]);

        $this->almacen        = InvAlmacen::factory()->create();
        $this->almacenDestino = InvAlmacen::factory()->create();
        $this->producto       = InvProducto::factory()->activo()->create(['tipo' => 'simple']);
    }

    // ─── filters ──────────────────────────────────────────────────────────────

    /** @test */
    public function filters_retorna_opciones_de_tipos_estados_y_almacenes(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-movimientos.filters'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['tipos', 'estados', 'almacenes'],
            ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_documentos(): void
    {
        // Se crean con tipos distintos para evitar colisión de numero_documento dentro de la misma transacción
        InvDocumentoMovimiento::factory()->entrada()->create([
            'almacen_id' => $this->almacen->id,
            'user_id'    => $this->usuario->id,
        ]);
        InvDocumentoMovimiento::factory()->create([
            'numero_documento' => 'AJU-2026-000001',
            'tipo_documento'   => 'ajuste',
            'almacen_id'       => $this->almacen->id,
            'user_id'          => $this->usuario->id,
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-movimientos.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'numero_documento', 'tipo_documento', 'status']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('inv-movimientos.index'))
            ->assertForbidden();
    }

    // ─── store — entrada ──────────────────────────────────────────────────────

    /** @test */
    public function store_registra_entrada_y_crea_stock(): void
    {
        $payload = [
            'tipo_documento' => 'entrada',
            'almacen_id'     => $this->almacen->id,
            'lineas'         => [
                ['producto_id' => $this->producto->id, 'cantidad' => 10, 'precio_costo' => 5000],
            ],
        ];

        $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.tipo_documento', 'entrada')
            ->assertJsonPath('data.status', 'confirmado');

        $this->assertDatabaseHas('inv_stock', [
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => 10,
            'cantidad_disponible' => 10,
        ]);
    }

    /** @test */
    public function store_genera_numero_documento_con_formato_correcto(): void
    {
        $payload = [
            'tipo_documento' => 'entrada',
            'almacen_id'     => $this->almacen->id,
            'lineas'         => [
                ['producto_id' => $this->producto->id, 'cantidad' => 5],
            ],
        ];

        $response = $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.store'), $payload)
            ->assertCreated();

        $numero = $response->json('data.numero_documento');
        $this->assertMatchesRegularExpression('/^ENT-\d{4}-\d{6}$/', $numero);
    }

    /** @test */
    public function store_registra_ajuste_positivo_e_incrementa_stock(): void
    {
        $stock = InvStock::create([
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => 5,
            'cantidad_reservada'  => 0,
            'cantidad_disponible' => 5,
        ]);

        $payload = [
            'tipo_documento' => 'ajuste',
            'almacen_id'     => $this->almacen->id,
            'lineas'         => [
                [
                    'producto_id'  => $this->producto->id,
                    'cantidad'     => 3,
                    'tipo_ajuste'  => 'ajuste_positivo',
                ],
            ],
        ];

        $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.store'), $payload)
            ->assertCreated();

        $this->assertDatabaseHas('inv_stock', [
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => 8,
            'cantidad_disponible' => 8,
        ]);
    }

    /** @test */
    public function store_registra_traslado_y_actualiza_ambos_almacenes(): void
    {
        InvStock::create([
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => 20,
            'cantidad_reservada'  => 0,
            'cantidad_disponible' => 20,
        ]);

        $payload = [
            'tipo_documento'     => 'traslado',
            'almacen_id'         => $this->almacen->id,
            'almacen_destino_id' => $this->almacenDestino->id,
            'lineas'             => [
                ['producto_id' => $this->producto->id, 'cantidad' => 8],
            ],
        ];

        $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.store'), $payload)
            ->assertCreated();

        $this->assertDatabaseHas('inv_stock', [
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_disponible' => 12,
        ]);
        $this->assertDatabaseHas('inv_stock', [
            'almacen_id'          => $this->almacenDestino->id,
            'producto_id'         => $this->producto->id,
            'cantidad_disponible' => 8,
        ]);
    }

    /** @test */
    public function store_falla_si_stock_insuficiente_para_salida(): void
    {
        InvStock::create([
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => 2,
            'cantidad_reservada'  => 0,
            'cantidad_disponible' => 2,
        ]);

        $payload = [
            'tipo_documento' => 'salida',
            'almacen_id'     => $this->almacen->id,
            'lineas'         => [
                ['producto_id' => $this->producto->id, 'cantidad' => 10],
            ],
        ];

        $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.store'), $payload)
            ->assertUnprocessable();
    }

    /** @test */
    public function store_falla_si_traslado_sin_almacen_destino(): void
    {
        $payload = [
            'tipo_documento' => 'traslado',
            'almacen_id'     => $this->almacen->id,
            'lineas'         => [
                ['producto_id' => $this->producto->id, 'cantidad' => 5],
            ],
        ];

        $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['almacen_destino_id']);
    }

    /** @test */
    public function store_falla_si_almacen_destino_igual_al_origen(): void
    {
        $payload = [
            'tipo_documento'     => 'traslado',
            'almacen_id'         => $this->almacen->id,
            'almacen_destino_id' => $this->almacen->id,
            'lineas'             => [
                ['producto_id' => $this->producto->id, 'cantidad' => 5],
            ],
        ];

        $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['almacen_destino_id']);
    }

    /** @test */
    public function store_falla_sin_lineas(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.store'), [
                'tipo_documento' => 'entrada',
                'almacen_id'     => $this->almacen->id,
                'lineas'         => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lineas']);
    }

    /** @test */
    public function store_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-movimientos.store'), [
                'tipo_documento' => 'entrada',
                'almacen_id'     => $this->almacen->id,
                'lineas'         => [['producto_id' => $this->producto->id, 'cantidad' => 1]],
            ])
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_documento_con_movimientos(): void
    {
        $payload = [
            'tipo_documento' => 'entrada',
            'almacen_id'     => $this->almacen->id,
            'lineas'         => [
                ['producto_id' => $this->producto->id, 'cantidad' => 5],
            ],
        ];

        $response = $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.store'), $payload)
            ->assertCreated();

        $id = $response->json('data.id');

        $this->actingAs($this->usuario)
            ->getJson(route('inv-movimientos.show', $id))
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonStructure([
                'data' => ['id', 'numero_documento', 'status', 'movimientos'],
            ]);
    }

    // ─── anular ───────────────────────────────────────────────────────────────

    /** @test */
    public function anular_revierte_stock_y_marca_documento_anulado(): void
    {
        // Crear stock y registrar una entrada
        $payload = [
            'tipo_documento' => 'entrada',
            'almacen_id'     => $this->almacen->id,
            'lineas'         => [
                ['producto_id' => $this->producto->id, 'cantidad' => 15],
            ],
        ];

        $response = $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.store'), $payload)
            ->assertCreated();

        $documentoId = $response->json('data.id');

        // Anular
        $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.anular', $documentoId), [
                'motivo_anulacion' => 'Entrada registrada por error en el sistema.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'anulado');

        // El stock debe haber vuelto a 0
        $this->assertDatabaseHas('inv_stock', [
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => 0,
            'cantidad_disponible' => 0,
        ]);
    }

    /** @test */
    public function anular_falla_si_documento_ya_esta_anulado(): void
    {
        $documento = InvDocumentoMovimiento::factory()->anulado()->create([
            'almacen_id' => $this->almacen->id,
            'user_id'    => $this->usuario->id,
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.anular', $documento), [
                'motivo_anulacion' => 'Intento de doble anulación del documento.',
            ])
            ->assertUnprocessable();
    }

    /** @test */
    public function anular_falla_sin_motivo(): void
    {
        $documento = InvDocumentoMovimiento::factory()->create([
            'almacen_id' => $this->almacen->id,
            'user_id'    => $this->usuario->id,
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-movimientos.anular', $documento), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo_anulacion']);
    }

    /** @test */
    public function anular_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('inv_movimientos');

        $documento = InvDocumentoMovimiento::factory()->create([
            'almacen_id' => $this->almacen->id,
            'user_id'    => $this->usuario->id,
        ]);

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-movimientos.anular', $documento), [
                'motivo_anulacion' => 'Prueba de permiso denegado.',
            ])
            ->assertForbidden();
    }
}
