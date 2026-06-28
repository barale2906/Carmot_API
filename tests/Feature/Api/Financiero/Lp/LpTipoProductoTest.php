<?php

namespace Tests\Feature\Api\Financiero\Lp;

use App\Models\Financiero\Lp\LpTipoProducto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del módulo LpTipoProducto: CRUD y permisos.
 */
class LpTipoProductoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'fin_lp_tipos_producto',         'descripcion' => 'ver tipos de producto']);
        Permission::create(['name' => 'fin_lp_tipoProductoCrear',      'descripcion' => 'crear tipo de producto']);
        Permission::create(['name' => 'fin_lp_tipoProductoEditar',     'descripcion' => 'editar tipo de producto']);
        Permission::create(['name' => 'fin_lp_tipoProductoInactivar',  'descripcion' => 'inactivar tipo de producto']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'fin_lp_tipos_producto',
            'fin_lp_tipoProductoCrear',
            'fin_lp_tipoProductoEditar',
            'fin_lp_tipoProductoInactivar',
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function lista_tipos_de_producto_paginados(): void
    {
        LpTipoProducto::factory()->count(3)->activo()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('tipos-producto.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @test */
    public function rechaza_index_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('tipos-producto.index'))
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function muestra_un_tipo_de_producto(): void
    {
        $tipo = LpTipoProducto::factory()->activo()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('tipos-producto.show', $tipo))
            ->assertOk()
            ->assertJsonPath('data.id', $tipo->id);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function crea_un_tipo_de_producto(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('tipos-producto.store'), [
                'nombre'         => 'Certificación',
                'codigo'         => 'cert-001',
                'es_financiable' => false,
                'status'         => 1,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Certificación');

        $this->assertDatabaseHas('lp_tipos_producto', ['nombre' => 'Certificación', 'codigo' => 'cert-001']);
    }

    /** @test */
    public function validacion_falla_sin_campos_requeridos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('tipos-producto.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'codigo']);
    }

    /** @test */
    public function valida_codigo_unico_entre_tipos(): void
    {
        LpTipoProducto::factory()->activo()->create(['codigo' => 'cod-duplicado']);

        $this->actingAs($this->usuario)
            ->postJson(route('tipos-producto.store'), [
                'nombre' => 'Otro tipo',
                'codigo' => 'cod-duplicado',
                'status' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function actualiza_un_tipo_de_producto(): void
    {
        $tipo = LpTipoProducto::factory()->activo()->create();

        $this->actingAs($this->usuario)
            ->putJson(route('tipos-producto.update', $tipo), [
                'nombre' => 'Tipo actualizado',
                'status' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Tipo actualizado');
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    /** @test */
    public function elimina_un_tipo_de_producto_con_soft_delete(): void
    {
        $tipo = LpTipoProducto::factory()->activo()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('tipos-producto.destroy', $tipo))
            ->assertOk();

        $this->assertSoftDeleted('lp_tipos_producto', ['id' => $tipo->id]);
    }

    /** @test */
    public function rechaza_eliminar_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_lp_tipos_producto');
        $tipo = LpTipoProducto::factory()->activo()->create();

        $this->actingAs($sinPermiso)
            ->deleteJson(route('tipos-producto.destroy', $tipo))
            ->assertForbidden();
    }

    // ─── filtros ──────────────────────────────────────────────────────────────

    /** @test */
    public function filtra_tipos_financiables(): void
    {
        LpTipoProducto::factory()->activo()->financiable()->create();
        LpTipoProducto::factory()->activo()->noFinanciable()->create();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('tipos-producto.index', ['es_financiable' => true]));

        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertTrue((bool) $item['es_financiable']);
        }
    }
}
