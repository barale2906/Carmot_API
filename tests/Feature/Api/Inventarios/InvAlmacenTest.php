<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Configuracion\Sede;
use App\Models\Inventarios\InvAlmacen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD de almacenes del inventario.
 */
class InvAlmacenTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_almacenes',          'descripcion' => 'ver almacenes']);
        Permission::create(['name' => 'inv_almacenesCrear',     'descripcion' => 'crear almacenes']);
        Permission::create(['name' => 'inv_almacenesEditar',    'descripcion' => 'editar almacenes']);
        Permission::create(['name' => 'inv_almacenesInactivar', 'descripcion' => 'inactivar almacenes']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'inv_almacenes', 'inv_almacenesCrear',
            'inv_almacenesEditar', 'inv_almacenesInactivar',
        ]);

        $this->sede = Sede::factory()->create();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_almacenes(): void
    {
        InvAlmacen::factory()->count(2)->create(['sede_id' => $this->sede->id]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-almacenes.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'nombre', 'sede_id', 'status', 'status_text']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('inv-almacenes.index'))
            ->assertForbidden();
    }

    // ─── activos ──────────────────────────────────────────────────────────────

    /** @test */
    public function activos_retorna_solo_almacenes_activos(): void
    {
        InvAlmacen::factory()->create(['nombre' => 'Activo', 'status' => 1, 'sede_id' => $this->sede->id]);
        InvAlmacen::factory()->create(['nombre' => 'Inactivo', 'status' => 0, 'sede_id' => $this->sede->id]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-almacenes.activos'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Activo', $response->json('data.0.nombre'));
    }

    /** @test */
    public function activos_filtra_por_sede(): void
    {
        $otraSede = Sede::factory()->create();
        InvAlmacen::factory()->create(['sede_id' => $this->sede->id, 'status' => 1]);
        InvAlmacen::factory()->create(['sede_id' => $otraSede->id, 'status' => 1]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-almacenes.activos', ['sede_id' => $this->sede->id]))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_almacen_con_datos_validos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-almacenes.store'), [
                'nombre'  => 'Bodega Principal',
                'sede_id' => $this->sede->id,
                'status'  => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Bodega Principal');

        $this->assertDatabaseHas('inv_almacenes', ['nombre' => 'Bodega Principal']);
    }

    /** @test */
    public function store_falla_si_nombre_vacio(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-almacenes.store'), ['sede_id' => $this->sede->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function store_falla_si_sede_no_existe(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-almacenes.store'), ['nombre' => 'Bodega', 'sede_id' => 9999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sede_id']);
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_almacen_con_cajeros(): void
    {
        $almacen = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-almacenes.show', $almacen))
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'nombre', 'sede_id', 'usuarios']]);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_modifica_almacen_existente(): void
    {
        $almacen = InvAlmacen::factory()->create(['nombre' => 'Bodega Vieja', 'sede_id' => $this->sede->id]);

        $this->actingAs($this->usuario)
            ->putJson(route('inv-almacenes.update', $almacen), [
                'nombre'  => 'Bodega Nueva',
                'sede_id' => $this->sede->id,
                'status'  => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Bodega Nueva');
    }

    // ─── destroy / restore / forceDelete / trashed ────────────────────────────

    /** @test */
    public function destroy_realiza_soft_delete(): void
    {
        $almacen = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-almacenes.destroy', $almacen))
            ->assertOk()
            ->assertJsonPath('message', 'Almacén eliminado exitosamente.');

        $this->assertSoftDeleted('inv_almacenes', ['id' => $almacen->id]);
    }

    /** @test */
    public function restore_recupera_almacen_eliminado(): void
    {
        $almacen = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        $almacen->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('inv-almacenes.restore', $almacen->id))
            ->assertOk()
            ->assertJsonPath('message', 'Almacén restaurado exitosamente.');
    }

    /** @test */
    public function force_delete_elimina_permanentemente(): void
    {
        $almacen = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        $almacen->delete();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-almacenes.force-delete', $almacen->id))
            ->assertOk()
            ->assertJsonPath('message', 'Almacén eliminado permanentemente.');

        $this->assertDatabaseMissing('inv_almacenes', ['id' => $almacen->id]);
    }

    /** @test */
    public function trashed_retorna_solo_eliminados(): void
    {
        $activo    = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        $eliminado = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        $eliminado->delete();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-almacenes.trashed'))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($eliminado->id, $ids);
        $this->assertNotContains($activo->id, $ids);
    }

    // ─── syncUsuarios ─────────────────────────────────────────────────────────

    /** @test */
    public function sync_usuarios_asigna_cajeros_al_almacen(): void
    {
        $almacen = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        $cajero1 = User::factory()->create();
        $cajero2 = User::factory()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('inv-almacenes.sync-usuarios', $almacen), [
                'user_ids' => [$cajero1->id, $cajero2->id],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Cajeros del almacén actualizados exitosamente.');

        $this->assertDatabaseHas('inv_almacen_usuario', ['almacen_id' => $almacen->id, 'user_id' => $cajero1->id]);
        $this->assertDatabaseHas('inv_almacen_usuario', ['almacen_id' => $almacen->id, 'user_id' => $cajero2->id]);
    }

    /** @test */
    public function sync_usuarios_reemplaza_lista_completa(): void
    {
        $almacen = InvAlmacen::factory()->create(['sede_id' => $this->sede->id]);
        $cajero1 = User::factory()->create();
        $cajero2 = User::factory()->create();

        $almacen->usuarios()->sync([$cajero1->id]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-almacenes.sync-usuarios', $almacen), [
                'user_ids' => [$cajero2->id],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('inv_almacen_usuario', ['almacen_id' => $almacen->id, 'user_id' => $cajero1->id]);
        $this->assertDatabaseHas('inv_almacen_usuario', ['almacen_id' => $almacen->id, 'user_id' => $cajero2->id]);
    }

    /** @test */
    public function filters_retorna_opciones_de_estado(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-almacenes.filters'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['status']]);
    }
}
