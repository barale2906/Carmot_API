<?php

namespace Tests\Feature\Api\Financiero\Lp;

use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\Lp\LpTipoProducto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del módulo LpProducto: CRUD y permisos.
 */
class LpProductoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private LpTipoProducto $tipoProducto;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'fin_lp_productos',         'descripcion' => 'ver productos']);
        Permission::create(['name' => 'fin_lp_productoCrear',     'descripcion' => 'crear producto']);
        Permission::create(['name' => 'fin_lp_productoEditar',    'descripcion' => 'editar producto']);
        Permission::create(['name' => 'fin_lp_productoInactivar', 'descripcion' => 'inactivar producto']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'fin_lp_productos',
            'fin_lp_productoCrear',
            'fin_lp_productoEditar',
            'fin_lp_productoInactivar',
        ]);

        $this->tipoProducto = LpTipoProducto::factory()->activo()->financiable()->create();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function lista_productos_paginados(): void
    {
        LpProducto::factory()->count(3)->activo()->create(['tipo_producto_id' => $this->tipoProducto->id]);

        $this->actingAs($this->usuario)
            ->getJson(route('productos.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @test */
    public function rechaza_index_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('productos.index'))
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function muestra_un_producto(): void
    {
        $producto = LpProducto::factory()->activo()->create(['tipo_producto_id' => $this->tipoProducto->id]);

        $this->actingAs($this->usuario)
            ->getJson(route('productos.show', $producto))
            ->assertOk()
            ->assertJsonPath('data.id', $producto->id);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function crea_un_producto(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('productos.store'), [
                'tipo_producto_id' => $this->tipoProducto->id,
                'nombre'           => 'Curso de Laravel',
                'status'           => 1,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Curso de Laravel');

        $this->assertDatabaseHas('lp_productos', ['nombre' => 'Curso de Laravel']);
    }

    /** @test */
    public function validacion_falla_sin_tipo_producto_id(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('productos.store'), ['nombre' => 'Sin tipo', 'status' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_producto_id']);
    }

    /** @test */
    public function rechaza_tipo_producto_inexistente(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('productos.store'), [
                'tipo_producto_id' => 99999,
                'nombre'           => 'Producto',
                'status'           => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_producto_id']);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function actualiza_un_producto(): void
    {
        $producto = LpProducto::factory()->activo()->create(['tipo_producto_id' => $this->tipoProducto->id]);

        $this->actingAs($this->usuario)
            ->putJson(route('productos.update', $producto), [
                'nombre' => 'Nombre actualizado',
                'status' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre actualizado');
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    /** @test */
    public function elimina_un_producto_con_soft_delete(): void
    {
        $producto = LpProducto::factory()->activo()->create(['tipo_producto_id' => $this->tipoProducto->id]);

        $this->actingAs($this->usuario)
            ->deleteJson(route('productos.destroy', $producto))
            ->assertOk();

        $this->assertSoftDeleted('lp_productos', ['id' => $producto->id]);
    }

    /** @test */
    public function rechaza_eliminar_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_lp_productos');
        $producto = LpProducto::factory()->activo()->create(['tipo_producto_id' => $this->tipoProducto->id]);

        $this->actingAs($sinPermiso)
            ->deleteJson(route('productos.destroy', $producto))
            ->assertForbidden();
    }

    // ─── filtros ──────────────────────────────────────────────────────────────

    /** @test */
    public function filtra_productos_por_tipo_producto_id(): void
    {
        $otroTipo = LpTipoProducto::factory()->activo()->create();
        LpProducto::factory()->activo()->create(['tipo_producto_id' => $this->tipoProducto->id]);
        LpProducto::factory()->activo()->create(['tipo_producto_id' => $otroTipo->id]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('productos.index', ['tipo_producto_id' => $this->tipoProducto->id]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->tipoProducto->id, $data[0]['tipo_producto_id']);
    }
}
