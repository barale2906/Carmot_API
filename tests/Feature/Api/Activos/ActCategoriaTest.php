<?php

namespace Tests\Feature\Api\Activos;

use App\Models\Activos\ActActivo;
use App\Models\Activos\ActCategoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Catálogo de categorías de activos.
 */
class ActCategoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permisos = ['act_categorias', 'act_categoriaCrear', 'act_categoriaEditar', 'act_categoriaInactivar', 'act_activos'];
        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo($permisos);
    }

    /** @test */
    public function crud_basico_de_categorias(): void
    {
        $id = $this->actingAs($this->usuario)->postJson(route('act-categorias.store'), ['nombre' => 'Herramientas'])
            ->assertCreated()
            ->assertJsonPath('data.status', 1)
            ->json('data.id');

        $this->actingAs($this->usuario)->postJson(route('act-categorias.store'), ['nombre' => 'Herramientas'])
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');

        $this->actingAs($this->usuario)->putJson(route('act-categorias.update', $id), ['nombre' => 'Herramientas manuales', 'status' => 0])
            ->assertOk()->assertJsonPath('data.status_text', 'Inactivo');

        $this->actingAs($this->usuario)->getJson(route('act-categorias.index', ['search' => 'manuales']))
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->usuario)->getJson(route('act-categorias.activas'))->assertJsonCount(0, 'data');

        $this->actingAs($this->usuario)->deleteJson(route('act-categorias.destroy', $id))->assertOk();
        $this->actingAs($this->usuario)->postJson(route('act-categorias.restore', $id))->assertOk();
    }

    /** @test */
    public function no_elimina_una_categoria_con_activos(): void
    {
        $activo = ActActivo::factory()->create();

        $this->actingAs($this->usuario)->deleteJson(route('act-categorias.destroy', $activo->categoria_id))
            ->assertUnprocessable();
    }

    /** @test */
    public function las_categorias_activas_se_consultan_con_permiso_de_activos(): void
    {
        ActCategoria::factory()->create();
        $soloActivos = User::factory()->create();
        $soloActivos->givePermissionTo('act_activos');

        $this->actingAs($soloActivos)->getJson(route('act-categorias.activas'))->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($soloActivos)->getJson(route('act-categorias.index'))->assertForbidden();
    }
}
