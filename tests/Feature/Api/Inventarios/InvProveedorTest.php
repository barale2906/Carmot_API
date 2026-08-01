<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Inventarios\InvProveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD de proveedores del módulo de inventarios.
 */
class InvProveedorTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_proveedores',          'descripcion' => 'ver proveedores']);
        Permission::create(['name' => 'inv_proveedoresCrear',     'descripcion' => 'crear proveedores']);
        Permission::create(['name' => 'inv_proveedoresEditar',    'descripcion' => 'editar proveedores']);
        Permission::create(['name' => 'inv_proveedoresInactivar', 'descripcion' => 'inactivar proveedores']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'inv_proveedores', 'inv_proveedoresCrear',
            'inv_proveedoresEditar', 'inv_proveedoresInactivar',
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_proveedores(): void
    {
        InvProveedor::factory()->count(3)->create();

        $this->actingAs($this->usuario)
            ->getJson(route('inv-proveedores.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'razon_social', 'status', 'status_text']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('inv-proveedores.index'))
            ->assertForbidden();
    }

    // ─── activos ─────────────────────────────────────────────────────────────

    /** @test */
    public function activos_retorna_solo_proveedores_activos(): void
    {
        InvProveedor::factory()->activo()->count(2)->create();
        InvProveedor::factory()->inactivo()->create();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-proveedores.activos'))
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_proveedor_con_datos_validos(): void
    {
        $payload = [
            'razon_social' => 'Distribuciones ABC S.A.S',
            'nit'          => '900123456-1',
            'email'        => 'contacto@abc.com',
            'status'       => 1,
        ];

        $this->actingAs($this->usuario)
            ->postJson(route('inv-proveedores.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.razon_social', 'Distribuciones ABC S.A.S')
            ->assertJsonPath('data.nit', '900123456-1');
    }

    /** @test */
    public function store_falla_si_razon_social_esta_duplicada(): void
    {
        InvProveedor::factory()->create(['razon_social' => 'Proveedor Único']);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-proveedores.store'), [
                'razon_social' => 'Proveedor Único',
                'status'       => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['razon_social']);
    }

    /** @test */
    public function store_falla_si_nit_esta_duplicado(): void
    {
        InvProveedor::factory()->create(['nit' => '900111222-1']);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-proveedores.store'), [
                'razon_social' => 'Otro Proveedor',
                'nit'          => '900111222-1',
                'status'       => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nit']);
    }

    /** @test */
    public function store_falla_sin_razon_social(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-proveedores.store'), ['status' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['razon_social']);
    }

    /** @test */
    public function store_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-proveedores.store'), ['razon_social' => 'Test', 'status' => 1])
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_proveedor_existente(): void
    {
        $proveedor = InvProveedor::factory()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('inv-proveedores.show', $proveedor))
            ->assertOk()
            ->assertJsonPath('data.id', $proveedor->id);
    }

    /** @test */
    public function show_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-proveedores.show', 99999))
            ->assertNotFound();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_modifica_proveedor_exitosamente(): void
    {
        $proveedor = InvProveedor::factory()->create();

        $this->actingAs($this->usuario)
            ->putJson(route('inv-proveedores.update', $proveedor), [
                'razon_social' => 'Nombre Actualizado',
                'status'       => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.razon_social', 'Nombre Actualizado');
    }

    /** @test */
    public function update_permite_mismo_nit_al_editar(): void
    {
        $proveedor = InvProveedor::factory()->create(['nit' => '800-1']);

        $this->actingAs($this->usuario)
            ->putJson(route('inv-proveedores.update', $proveedor), [
                'razon_social' => 'Mismo Proveedor',
                'nit'          => '800-1',
                'status'       => 1,
            ])
            ->assertOk();
    }

    // ─── destroy / restore / forceDelete ─────────────────────────────────────

    /** @test */
    public function destroy_elimina_proveedor_soft_delete(): void
    {
        $proveedor = InvProveedor::factory()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-proveedores.destroy', $proveedor))
            ->assertOk();

        $this->assertSoftDeleted('inv_proveedores', ['id' => $proveedor->id]);
    }

    /** @test */
    public function restore_recupera_proveedor_eliminado(): void
    {
        $proveedor = InvProveedor::factory()->create();
        $proveedor->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('inv-proveedores.restore', $proveedor->id))
            ->assertOk()
            ->assertJsonPath('data.id', $proveedor->id);

        $this->assertDatabaseHas('inv_proveedores', ['id' => $proveedor->id, 'deleted_at' => null]);
    }

    /** @test */
    public function trashed_retorna_solo_eliminados(): void
    {
        InvProveedor::factory()->create();
        $eliminado = InvProveedor::factory()->create();
        $eliminado->delete();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-proveedores.trashed'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($eliminado->id, $response->json('data.0.id'));
    }

    /** @test */
    public function force_delete_elimina_permanentemente(): void
    {
        $proveedor = InvProveedor::factory()->create();
        $proveedor->delete();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-proveedores.force-delete', $proveedor->id))
            ->assertOk();

        $this->assertDatabaseMissing('inv_proveedores', ['id' => $proveedor->id]);
    }
}
