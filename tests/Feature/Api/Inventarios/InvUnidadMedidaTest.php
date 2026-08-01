<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Inventarios\InvUnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD de unidades de medida del inventario.
 */
class InvUnidadMedidaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_unidades',          'descripcion' => 'ver unidades']);
        Permission::create(['name' => 'inv_unidadesCrear',     'descripcion' => 'crear unidades']);
        Permission::create(['name' => 'inv_unidadesEditar',    'descripcion' => 'editar unidades']);
        Permission::create(['name' => 'inv_unidadesInactivar', 'descripcion' => 'inactivar unidades']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'inv_unidades', 'inv_unidadesCrear',
            'inv_unidadesEditar', 'inv_unidadesInactivar',
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada(): void
    {
        InvUnidadMedida::factory()->count(3)->create();

        $this->actingAs($this->usuario)
            ->getJson(route('inv-unidades.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'nombre', 'abreviatura', 'status', 'status_text']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('inv-unidades.index'))
            ->assertForbidden();
    }

    // ─── activas ──────────────────────────────────────────────────────────────

    /** @test */
    public function activas_retorna_solo_unidades_activas(): void
    {
        InvUnidadMedida::factory()->create(['nombre' => 'Activa', 'status' => 1]);
        InvUnidadMedida::factory()->create(['nombre' => 'Inactiva', 'status' => 0]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-unidades.activas'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_unidad_con_datos_validos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-unidades.store'), [
                'nombre'      => 'Unidad',
                'abreviatura' => 'UND',
                'status'      => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Unidad')
            ->assertJsonPath('data.abreviatura', 'UND');

        $this->assertDatabaseHas('inv_unidades_medida', ['nombre' => 'Unidad']);
    }

    /** @test */
    public function store_falla_si_nombre_vacio(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-unidades.store'), ['abreviatura' => 'UND'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function store_falla_si_nombre_duplicado(): void
    {
        InvUnidadMedida::factory()->create(['nombre' => 'Unidad']);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-unidades.store'), ['nombre' => 'Unidad'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_unidad_existente(): void
    {
        $unidad = InvUnidadMedida::factory()->create(['nombre' => 'Par']);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-unidades.show', $unidad))
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Par');
    }

    /** @test */
    public function show_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-unidades.show', 999))
            ->assertNotFound();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_modifica_unidad_existente(): void
    {
        $unidad = InvUnidadMedida::factory()->create(['nombre' => 'Par Viejo']);

        $this->actingAs($this->usuario)
            ->putJson(route('inv-unidades.update', $unidad), [
                'nombre' => 'Par Actualizado',
                'status' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Par Actualizado');
    }

    // ─── destroy / restore / forceDelete ──────────────────────────────────────

    /** @test */
    public function destroy_realiza_soft_delete(): void
    {
        $unidad = InvUnidadMedida::factory()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-unidades.destroy', $unidad))
            ->assertOk()
            ->assertJsonPath('message', 'Unidad de medida eliminada exitosamente.');

        $this->assertSoftDeleted('inv_unidades_medida', ['id' => $unidad->id]);
    }

    /** @test */
    public function restore_recupera_unidad_eliminada(): void
    {
        $unidad = InvUnidadMedida::factory()->create();
        $unidad->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('inv-unidades.restore', $unidad->id))
            ->assertOk()
            ->assertJsonPath('message', 'Unidad de medida restaurada exitosamente.');
    }

    /** @test */
    public function force_delete_elimina_permanentemente(): void
    {
        $unidad = InvUnidadMedida::factory()->create();
        $unidad->delete();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-unidades.force-delete', $unidad->id))
            ->assertOk()
            ->assertJsonPath('message', 'Unidad de medida eliminada permanentemente.');

        $this->assertDatabaseMissing('inv_unidades_medida', ['id' => $unidad->id]);
    }

    /** @test */
    public function trashed_retorna_solo_eliminadas(): void
    {
        $activa    = InvUnidadMedida::factory()->create();
        $eliminada = InvUnidadMedida::factory()->create();
        $eliminada->delete();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-unidades.trashed'))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($eliminada->id, $ids);
        $this->assertNotContains($activa->id, $ids);
    }

    /** @test */
    public function filters_retorna_opciones_de_estado(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-unidades.filters'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['status']]);
    }
}
