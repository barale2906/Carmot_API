<?php

namespace Tests\Feature\Api\Configuracion;

use App\Models\Configuracion\Banco;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD de Bancos.
 */
class BancoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'co_bancos',          'descripcion' => 'ver bancos']);
        Permission::create(['name' => 'co_bancosCrear',     'descripcion' => 'crear banco']);
        Permission::create(['name' => 'co_bancosEditar',    'descripcion' => 'editar banco']);
        Permission::create(['name' => 'co_bancosInactivar', 'descripcion' => 'inactivar banco']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'co_bancos', 'co_bancosCrear', 'co_bancosEditar', 'co_bancosInactivar',
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_bancos(): void
    {
        Banco::factory()->count(3)->create();

        $this->actingAs($this->usuario)
            ->getJson(route('bancos.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'nombre', 'status', 'status_text']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    /** @test */
    public function index_filtra_por_search(): void
    {
        Banco::factory()->create(['nombre' => 'Bancolombia']);
        Banco::factory()->create(['nombre' => 'Davivienda']);

        $this->actingAs($this->usuario)
            ->getJson(route('bancos.index', ['search' => 'bancolombia']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Bancolombia');
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('bancos.index'))
            ->assertForbidden();
    }

    // ─── activos ─────────────────────────────────────────────────────────────

    /** @test */
    public function activos_retorna_solo_bancos_con_status_activo(): void
    {
        Banco::factory()->create(['nombre' => 'Activo', 'status' => 1]);
        Banco::factory()->create(['nombre' => 'Inactivo', 'status' => 0]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('bancos.activos'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Activo', $response->json('data.0.nombre'));
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_banco_con_datos_validos(): void
    {
        $payload = ['nombre' => 'Banco Nuevo', 'codigo' => '099', 'status' => 1];

        $this->actingAs($this->usuario)
            ->postJson(route('bancos.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Banco Nuevo')
            ->assertJsonPath('data.codigo', '099');

        $this->assertDatabaseHas('bancos', ['nombre' => 'Banco Nuevo']);
    }

    /** @test */
    public function store_rechaza_nombre_duplicado(): void
    {
        Banco::factory()->create(['nombre' => 'Duplicado']);

        $this->actingAs($this->usuario)
            ->postJson(route('bancos.store'), ['nombre' => 'Duplicado'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function store_rechaza_codigo_duplicado(): void
    {
        Banco::factory()->create(['codigo' => '007']);

        $this->actingAs($this->usuario)
            ->postJson(route('bancos.store'), ['nombre' => 'Otro Banco', 'codigo' => '007'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);
    }

    /** @test */
    public function store_deniega_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('bancos.store'), ['nombre' => 'X'])
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_banco_especificado(): void
    {
        $banco = Banco::factory()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('bancos.show', $banco))
            ->assertOk()
            ->assertJsonPath('data.id', $banco->id);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_modifica_banco_con_datos_validos(): void
    {
        $banco = Banco::factory()->create(['nombre' => 'Original']);

        $this->actingAs($this->usuario)
            ->putJson(route('bancos.update', $banco), ['nombre' => 'Modificado'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Modificado');
    }

    /** @test */
    public function update_permite_mismo_nombre_al_editar(): void
    {
        $banco = Banco::factory()->create(['nombre' => 'Mismo']);

        $this->actingAs($this->usuario)
            ->putJson(route('bancos.update', $banco), ['nombre' => 'Mismo', 'codigo' => '123'])
            ->assertOk();
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    /** @test */
    public function destroy_elimina_banco_sin_medios_de_pago(): void
    {
        $banco = Banco::factory()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('bancos.destroy', $banco))
            ->assertOk();

        $this->assertSoftDeleted('bancos', ['id' => $banco->id]);
    }

    // ─── restore ──────────────────────────────────────────────────────────────

    /** @test */
    public function restore_restaura_banco_eliminado(): void
    {
        $banco = Banco::factory()->create();
        $banco->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('bancos.restore', $banco->id))
            ->assertOk();

        $this->assertDatabaseHas('bancos', ['id' => $banco->id, 'deleted_at' => null]);
    }

    // ─── trashed ──────────────────────────────────────────────────────────────

    /** @test */
    public function trashed_lista_solo_bancos_eliminados(): void
    {
        Banco::factory()->create(['nombre' => 'Activo']);
        $eliminado = Banco::factory()->create(['nombre' => 'Eliminado']);
        $eliminado->delete();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('bancos.trashed'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Eliminado', $response->json('data.0.nombre'));
    }

    // ─── filters y statistics ─────────────────────────────────────────────────

    /** @test */
    public function filters_retorna_opciones_de_status(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('bancos.filters'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['status']]);
    }

    /** @test */
    public function statistics_retorna_conteos_del_catalogo(): void
    {
        Banco::factory()->count(2)->create(['status' => 1]);
        Banco::factory()->create(['status' => 0]);

        $this->actingAs($this->usuario)
            ->getJson(route('bancos.statistics'))
            ->assertOk()
            ->assertJsonPath('data.totales.activos', 2)
            ->assertJsonPath('data.totales.inactivos', 1);
    }
}
