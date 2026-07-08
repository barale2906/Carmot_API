<?php

namespace Tests\Feature\Api\Financiero\Lp;

use App\Models\Configuracion\Poblacion;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del módulo LpListaPrecio: CRUD, ciclo de aprobación y clonado.
 */
class LpListaPrecioTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Poblacion $poblacion;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'fin_lp_listas_precios',       'descripcion' => 'ver listas de precios']);
        Permission::create(['name' => 'fin_lp_listaPrecioCrear',     'descripcion' => 'crear lista de precios']);
        Permission::create(['name' => 'fin_lp_listaPrecioEditar',    'descripcion' => 'editar lista de precios']);
        Permission::create(['name' => 'fin_lp_listaPrecioInactivar', 'descripcion' => 'inactivar lista de precios']);
        Permission::create(['name' => 'fin_lp_listaPrecioAprobar',   'descripcion' => 'aprobar lista de precios']);
        Permission::create(['name' => 'fin_lp_listaPrecioClonar',    'descripcion' => 'clonar lista de precios']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'fin_lp_listas_precios',
            'fin_lp_listaPrecioCrear',
            'fin_lp_listaPrecioEditar',
            'fin_lp_listaPrecioInactivar',
            'fin_lp_listaPrecioAprobar',
            'fin_lp_listaPrecioClonar',
        ]);

        $this->poblacion = Poblacion::first() ?? Poblacion::factory()->create();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function datosBase(array $overrides = []): array
    {
        return array_merge([
            'nombre'       => 'Lista Prueba 2026',
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin'    => now()->addYear()->toDateString(),
            'status'       => LpListaPrecio::STATUS_EN_PROCESO,
            'poblaciones'  => [$this->poblacion->id],
        ], $overrides);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function lista_listas_de_precios_paginadas(): void
    {
        LpListaPrecio::factory()->count(2)->enProceso()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('listas-precios.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @test */
    public function rechaza_index_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('listas-precios.index'))
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function muestra_una_lista_de_precios(): void
    {
        $lista = LpListaPrecio::factory()->enProceso()->create();

        $this->actingAs($this->usuario)
            ->getJson(route('listas-precios.show', $lista))
            ->assertOk()
            ->assertJsonPath('data.id', $lista->id);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function crea_una_lista_de_precios(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('listas-precios.store'), $this->datosBase());

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Lista Prueba 2026');

        $this->assertDatabaseHas('lp_listas_precios', ['nombre' => 'Lista Prueba 2026']);
    }

    /** @test */
    public function validacion_falla_sin_campos_requeridos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('listas-precios.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'fecha_inicio', 'fecha_fin', 'poblaciones']);
    }

    /** @test */
    public function valida_que_fecha_fin_sea_despues_de_fecha_inicio(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('listas-precios.store'), $this->datosBase([
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin'    => now()->subDay()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_fin']);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function actualiza_una_lista_de_precios(): void
    {
        $lista = LpListaPrecio::factory()->enProceso()->create();
        $lista->poblaciones()->attach($this->poblacion->id);

        $this->actingAs($this->usuario)
            ->putJson(route('listas-precios.update', $lista), [
                'nombre'       => 'Lista Modificada',
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin'    => now()->addMonths(6)->toDateString(),
                'poblaciones'  => [$this->poblacion->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Lista Modificada');
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    /** @test */
    public function elimina_una_lista_de_precios_con_soft_delete(): void
    {
        $lista = LpListaPrecio::factory()->enProceso()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('listas-precios.destroy', $lista))
            ->assertOk();

        $this->assertSoftDeleted('lp_listas_precios', ['id' => $lista->id]);
    }

    // ─── ciclo de aprobación ──────────────────────────────────────────────────

    /** @test */
    public function aprueba_una_lista_en_proceso(): void
    {
        $lista = LpListaPrecio::factory()->enProceso()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('listas-precios.aprobar', $lista))
            ->assertOk()
            ->assertJsonPath('data.status', LpListaPrecio::STATUS_APROBADA);

        $this->assertDatabaseHas('lp_listas_precios', ['id' => $lista->id, 'status' => LpListaPrecio::STATUS_APROBADA]);
    }

    /** @test */
    public function no_puede_aprobar_lista_ya_aprobada(): void
    {
        $lista = LpListaPrecio::factory()->aprobada()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('listas-precios.aprobar', $lista))
            ->assertUnprocessable();
    }

    /** @test */
    public function activa_una_lista_aprobada(): void
    {
        $lista = LpListaPrecio::factory()->aprobada()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('listas-precios.activar', $lista))
            ->assertOk()
            ->assertJsonPath('data.status', LpListaPrecio::STATUS_ACTIVA);
    }

    /** @test */
    public function no_puede_activar_lista_en_proceso(): void
    {
        $lista = LpListaPrecio::factory()->enProceso()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('listas-precios.activar', $lista))
            ->assertUnprocessable();
    }

    /** @test */
    public function inactiva_una_lista_activa(): void
    {
        $lista = LpListaPrecio::factory()->activa()->create();

        $this->actingAs($this->usuario)
            ->postJson(route('listas-precios.inactivar', $lista))
            ->assertOk()
            ->assertJsonPath('data.status', LpListaPrecio::STATUS_INACTIVA);
    }

    // ─── clonar ───────────────────────────────────────────────────────────────

    /** @test */
    public function clona_una_lista_de_precios_sin_precios(): void
    {
        $listaOrigen = LpListaPrecio::factory()->enProceso()->create();

        $response = $this->actingAs($this->usuario)
            ->postJson(route('listas-precios.clonar', $listaOrigen), [
                'nombre'       => 'Lista Clonada',
                'fecha_inicio' => now()->addYear()->toDateString(),
                'fecha_fin'    => now()->addYears(2)->toDateString(),
                'copiar_precios' => false,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Lista Clonada')
            ->assertJsonPath('precios_copiados', 0);

        $this->assertDatabaseHas('lp_listas_precios', [
            'nombre' => 'Lista Clonada',
            'status' => LpListaPrecio::STATUS_EN_PROCESO,
        ]);
    }

    /** @test */
    public function rechaza_clonar_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_lp_listas_precios');
        $lista = LpListaPrecio::factory()->enProceso()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('listas-precios.clonar', $lista), [
                'nombre'       => 'Clonada sin permiso',
                'fecha_inicio' => now()->addYear()->toDateString(),
                'fecha_fin'    => now()->addYears(2)->toDateString(),
            ])
            ->assertForbidden();
    }
}
