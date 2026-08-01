<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Inventarios\InvCategoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD de categorías de inventario.
 */
class InvCategoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_categorias',          'descripcion' => 'ver categorías']);
        Permission::create(['name' => 'inv_categoriasCrear',     'descripcion' => 'crear categorías']);
        Permission::create(['name' => 'inv_categoriasEditar',    'descripcion' => 'editar categorías']);
        Permission::create(['name' => 'inv_categoriasInactivar', 'descripcion' => 'inactivar categorías']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'inv_categorias', 'inv_categoriasCrear',
            'inv_categoriasEditar', 'inv_categoriasInactivar',
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_categorias(): void
    {
        InvCategoria::factory()->count(3)->create();

        $this->actingAs($this->usuario)
            ->getJson(route('inv-categorias.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'nombre', 'status', 'status_text']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    /** @test */
    public function index_filtra_por_search(): void
    {
        InvCategoria::factory()->create(['nombre' => 'Uniformes Escolares']);
        InvCategoria::factory()->create(['nombre' => 'Útiles Escolares']);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-categorias.index', ['search' => 'Uniforme']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Uniformes Escolares');
    }

    /** @test */
    public function index_filtra_por_status(): void
    {
        InvCategoria::factory()->create(['nombre' => 'Activa', 'status' => 1]);
        InvCategoria::factory()->create(['nombre' => 'Inactiva', 'status' => 0]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-categorias.index', ['status' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Activa');
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('inv-categorias.index'))
            ->assertForbidden();
    }

    // ─── activas ──────────────────────────────────────────────────────────────

    /** @test */
    public function activas_retorna_solo_categorias_activas(): void
    {
        InvCategoria::factory()->create(['nombre' => 'Activa', 'status' => 1]);
        InvCategoria::factory()->create(['nombre' => 'Inactiva', 'status' => 0]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-categorias.activas'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Activa', $response->json('data.0.nombre'));
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_categoria_con_datos_validos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-categorias.store'), [
                'nombre' => 'Uniformes',
                'status' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Uniformes')
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas('inv_categorias', ['nombre' => 'Uniformes']);
    }

    /** @test */
    public function store_falla_si_nombre_vacio(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-categorias.store'), ['nombre' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function store_falla_si_nombre_duplicado(): void
    {
        InvCategoria::factory()->create(['nombre' => 'Uniformes']);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-categorias.store'), ['nombre' => 'Uniformes'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function store_deniega_sin_permiso_crear(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('inv_categorias');

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-categorias.store'), ['nombre' => 'Test'])
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_categoria_existente(): void
    {
        $cat = InvCategoria::factory()->create(['nombre' => 'Deportivos']);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-categorias.show', $cat))
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Deportivos');
    }

    /** @test */
    public function show_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-categorias.show', 999))
            ->assertNotFound();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_modifica_categoria_existente(): void
    {
        $cat = InvCategoria::factory()->create(['nombre' => 'Vieja', 'status' => 1]);

        $this->actingAs($this->usuario)
            ->putJson(route('inv-categorias.update', $cat), ['nombre' => 'Nueva', 'status' => 0])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nueva')
            ->assertJsonPath('data.status', 0);

        $this->assertDatabaseHas('inv_categorias', ['id' => $cat->id, 'nombre' => 'Nueva']);
    }

    /** @test */
    public function update_falla_si_nombre_duplicado_de_otra_categoria(): void
    {
        InvCategoria::factory()->create(['nombre' => 'Uniformes']);
        $cat = InvCategoria::factory()->create(['nombre' => 'Deportivos']);

        $this->actingAs($this->usuario)
            ->putJson(route('inv-categorias.update', $cat), ['nombre' => 'Uniformes'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    /** @test */
    public function destroy_realiza_soft_delete(): void
    {
        $cat = InvCategoria::factory()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-categorias.destroy', $cat))
            ->assertOk()
            ->assertJsonPath('message', 'Categoría eliminada exitosamente.');

        $this->assertSoftDeleted('inv_categorias', ['id' => $cat->id]);
    }

    // ─── restore ──────────────────────────────────────────────────────────────

    /** @test */
    public function restore_recupera_categoria_eliminada(): void
    {
        $cat = InvCategoria::factory()->create();
        $cat->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('inv-categorias.restore', $cat->id))
            ->assertOk()
            ->assertJsonPath('message', 'Categoría restaurada exitosamente.');

        $this->assertDatabaseHas('inv_categorias', ['id' => $cat->id, 'deleted_at' => null]);
    }

    // ─── forceDelete ──────────────────────────────────────────────────────────

    /** @test */
    public function force_delete_elimina_permanentemente(): void
    {
        $cat = InvCategoria::factory()->create();
        $cat->delete();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-categorias.force-delete', $cat->id))
            ->assertOk()
            ->assertJsonPath('message', 'Categoría eliminada permanentemente.');

        $this->assertDatabaseMissing('inv_categorias', ['id' => $cat->id]);
    }

    // ─── trashed ──────────────────────────────────────────────────────────────

    /** @test */
    public function trashed_retorna_solo_eliminadas(): void
    {
        $activa    = InvCategoria::factory()->create();
        $eliminada = InvCategoria::factory()->create();
        $eliminada->delete();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-categorias.trashed'))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($eliminada->id, $ids);
        $this->assertNotContains($activa->id, $ids);
    }

    // ─── filters ──────────────────────────────────────────────────────────────

    /** @test */
    public function filters_retorna_opciones_de_estado(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-categorias.filters'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['status']]);
    }
}
