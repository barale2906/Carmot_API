<?php

namespace Tests\Feature\Api\Configuracion;

use App\Models\Configuracion\Eps;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD de EPS.
 */
class EpsTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'co_eps',          'descripcion' => 'ver EPS']);
        Permission::create(['name' => 'co_epsCrear',     'descripcion' => 'crear EPS']);
        Permission::create(['name' => 'co_epsEditar',    'descripcion' => 'editar EPS']);
        Permission::create(['name' => 'co_epsInactivar', 'descripcion' => 'inactivar EPS']);
        Permission::create(['name' => 'co_epsImportar',  'descripcion' => 'carga masiva EPS']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'co_eps', 'co_epsCrear', 'co_epsEditar', 'co_epsInactivar', 'co_epsImportar',
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_eps(): void
    {
        Eps::factory()->count(3)->create();

        $this->actingAs($this->usuario)
            ->getJson(route('eps.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'nombre', 'status', 'status_text']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            ]);
    }

    /** @test */
    public function index_filtra_por_search(): void
    {
        Eps::factory()->create(['nombre' => 'Sanitas']);
        Eps::factory()->create(['nombre' => 'Sura']);

        $this->actingAs($this->usuario)
            ->getJson(route('eps.index', ['search' => 'sanitas']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Sanitas');
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('eps.index'))
            ->assertForbidden();
    }

    // ─── activas ──────────────────────────────────────────────────────────────

    /** @test */
    public function activas_retorna_solo_eps_con_status_activo(): void
    {
        Eps::factory()->create(['nombre' => 'Activa', 'status' => 1]);
        Eps::factory()->create(['nombre' => 'Inactiva', 'status' => 0]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('eps.activas'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Activa', $response->json('data.0.nombre'));
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_eps_con_datos_validos(): void
    {
        $payload = [
            'nombre'    => 'Nueva EPS',
            'direccion' => 'Calle 1 # 2-3',
            'status'    => 1,
        ];

        $this->actingAs($this->usuario)
            ->postJson(route('eps.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Nueva EPS')
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas('eps', ['nombre' => 'Nueva EPS']);
    }

    /** @test */
    public function store_falla_si_nombre_esta_vacio(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('eps.store'), ['direccion' => 'Calle 1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function store_falla_si_nombre_duplicado(): void
    {
        Eps::factory()->create(['nombre' => 'Sura']);

        $this->actingAs($this->usuario)
            ->postJson(route('eps.store'), ['nombre' => 'Sura'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function store_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('co_eps');

        $this->actingAs($sinPermiso)
            ->postJson(route('eps.store'), ['nombre' => 'EPS Test'])
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_eps_existente(): void
    {
        $eps = Eps::factory()->create(['nombre' => 'Compensar']);

        $this->actingAs($this->usuario)
            ->getJson(route('eps.show', $eps))
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Compensar');
    }

    /** @test */
    public function show_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('eps.show', 999))
            ->assertNotFound();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_modifica_eps_existente(): void
    {
        $eps = Eps::factory()->create(['nombre' => 'Vieja EPS', 'status' => 1]);

        $this->actingAs($this->usuario)
            ->putJson(route('eps.update', $eps), ['nombre' => 'Nueva EPS', 'status' => 0])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nueva EPS')
            ->assertJsonPath('data.status', 0);

        $this->assertDatabaseHas('eps', ['id' => $eps->id, 'nombre' => 'Nueva EPS', 'status' => 0]);
    }

    /** @test */
    public function update_falla_si_nombre_duplicado_de_otra_eps(): void
    {
        Eps::factory()->create(['nombre' => 'Sanitas']);
        $eps = Eps::factory()->create(['nombre' => 'Sura']);

        $this->actingAs($this->usuario)
            ->putJson(route('eps.update', $eps), ['nombre' => 'Sanitas'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    /** @test */
    public function destroy_realiza_soft_delete(): void
    {
        $eps = Eps::factory()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('eps.destroy', $eps))
            ->assertOk()
            ->assertJsonPath('message', 'EPS eliminada exitosamente.');

        $this->assertSoftDeleted('eps', ['id' => $eps->id]);
    }

    // ─── restore ──────────────────────────────────────────────────────────────

    /** @test */
    public function restore_recupera_eps_eliminada(): void
    {
        $eps = Eps::factory()->create();
        $eps->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('eps.restore', $eps->id))
            ->assertOk()
            ->assertJsonPath('message', 'EPS restaurada exitosamente.');

        $this->assertDatabaseHas('eps', ['id' => $eps->id, 'deleted_at' => null]);
    }

    // ─── forceDelete ──────────────────────────────────────────────────────────

    /** @test */
    public function force_delete_elimina_permanentemente(): void
    {
        $eps = Eps::factory()->create();
        $eps->delete();

        $this->actingAs($this->usuario)
            ->deleteJson(route('eps.force-delete', $eps->id))
            ->assertOk()
            ->assertJsonPath('message', 'EPS eliminada permanentemente.');

        $this->assertDatabaseMissing('eps', ['id' => $eps->id]);
    }

    // ─── trashed ──────────────────────────────────────────────────────────────

    /** @test */
    public function trashed_retorna_solo_eliminadas(): void
    {
        $activa    = Eps::factory()->create();
        $eliminada = Eps::factory()->create();
        $eliminada->delete();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('eps.trashed'))
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
            ->getJson(route('eps.filters'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['status']]);
    }

    // ─── statistics ───────────────────────────────────────────────────────────

    /** @test */
    public function statistics_retorna_estructura_esperada(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('eps.statistics'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['totales', 'con_matriculas', 'top_eps']]);
    }

    // ─── importar ─────────────────────────────────────────────────────────────

    /** @test */
    public function importar_inserta_eps_desde_csv_valido(): void
    {
        $csv = "nombre,direccion,status\nSanitas,Calle 1 # 1-1,1\nSura,Carrera 2 # 2-2,1";
        $archivo = UploadedFile::fake()->createWithContent('eps.csv', $csv);

        $this->actingAs($this->usuario)
            ->postJson(route('eps.importar'), ['archivo' => $archivo])
            ->assertOk()
            ->assertJsonPath('data.insertadas', 2)
            ->assertJsonPath('data.omitidas', 0);

        $this->assertDatabaseHas('eps', ['nombre' => 'Sanitas']);
        $this->assertDatabaseHas('eps', ['nombre' => 'Sura']);
    }

    /** @test */
    public function importar_omite_nombres_duplicados(): void
    {
        Eps::factory()->create(['nombre' => 'Sanitas']);

        $csv = "nombre,direccion,status\nSanitas,Calle 1,1\nSura,Carrera 2,1";
        $archivo = UploadedFile::fake()->createWithContent('eps.csv', $csv);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('eps.importar'), ['archivo' => $archivo])
            ->assertOk();

        $this->assertEquals(1, $response->json('data.insertadas'));
        $this->assertEquals(1, $response->json('data.omitidas'));
    }

    /** @test */
    public function importar_falla_con_formato_incorrecto(): void
    {
        $csv = "columna_invalida,otra_columna\nvalor,valor";
        $archivo = UploadedFile::fake()->createWithContent('eps.csv', $csv);

        $this->actingAs($this->usuario)
            ->postJson(route('eps.importar'), ['archivo' => $archivo])
            ->assertUnprocessable();
    }

    /** @test */
    public function importar_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('co_eps');

        $csv = "nombre,direccion,status\nSanitas,Calle 1,1";
        $archivo = UploadedFile::fake()->createWithContent('eps.csv', $csv);

        $this->actingAs($sinPermiso)
            ->postJson(route('eps.importar'), ['archivo' => $archivo])
            ->assertForbidden();
    }

    // ─── plantilla ────────────────────────────────────────────────────────────

    /** @test */
    public function plantilla_descarga_csv(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('eps.plantilla'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
