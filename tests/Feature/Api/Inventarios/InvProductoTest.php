<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Inventarios\InvCategoria;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvUnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el CRUD del catálogo de productos del inventario.
 */
class InvProductoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private InvCategoria $categoria;
    private InvUnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_productos',          'descripcion' => 'ver productos']);
        Permission::create(['name' => 'inv_productosCrear',     'descripcion' => 'crear productos']);
        Permission::create(['name' => 'inv_productosEditar',    'descripcion' => 'editar productos']);
        Permission::create(['name' => 'inv_productosInactivar', 'descripcion' => 'inactivar productos']);
        Permission::create(['name' => 'inv_productosImportar',  'descripcion' => 'importar productos']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'inv_productos', 'inv_productosCrear',
            'inv_productosEditar', 'inv_productosInactivar',
            'inv_productosImportar',
        ]);

        $this->categoria = InvCategoria::factory()->activa()->create();
        $this->unidad    = InvUnidadMedida::factory()->activa()->create();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_productos(): void
    {
        InvProducto::factory()->count(3)->create([
            'categoria_id'    => $this->categoria->id,
            'unidad_medida_id' => $this->unidad->id,
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-productos.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'codigo', 'nombre', 'tipo', 'status', 'status_text']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /** @test */
    public function index_filtra_por_tipo(): void
    {
        InvProducto::factory()->create(['tipo' => 'simple', 'nombre' => 'Camisa']);
        InvProducto::factory()->create(['tipo' => 'kit', 'nombre' => 'Kit Uniforme']);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-productos.index', ['tipo' => 'simple']))
            ->assertOk();

        $tipos = collect($response->json('data'))->pluck('tipo')->unique()->all();
        $this->assertEquals(['simple'], $tipos);
    }

    /** @test */
    public function index_filtra_por_search_por_nombre_o_codigo(): void
    {
        InvProducto::factory()->create(['codigo' => 'CAM-001', 'nombre' => 'Camisa Azul']);
        InvProducto::factory()->create(['codigo' => 'PAN-001', 'nombre' => 'Pantalon Gris']);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-productos.index', ['search' => 'CAM']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo', 'CAM-001');
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('inv-productos.index'))
            ->assertForbidden();
    }

    // ─── store ────────────────────────────────────────────────────────────────

    /** @test */
    public function store_crea_producto_simple_con_datos_validos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-productos.store'), [
                'codigo'           => 'CAM-001',
                'nombre'           => 'Camisa Escolar',
                'tipo'             => 'simple',
                'categoria_id'     => $this->categoria->id,
                'unidad_medida_id' => $this->unidad->id,
                'status'           => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.codigo', 'CAM-001')
            ->assertJsonPath('data.tipo', 'simple');

        $this->assertDatabaseHas('inv_productos', ['codigo' => 'CAM-001']);
    }

    /** @test */
    public function store_falla_si_codigo_duplicado(): void
    {
        InvProducto::factory()->create(['codigo' => 'CAM-001']);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-productos.store'), [
                'codigo' => 'CAM-001',
                'nombre' => 'Otro Producto',
                'tipo'   => 'simple',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);
    }

    /** @test */
    public function store_falla_si_tipo_invalido(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('inv-productos.store'), [
                'codigo' => 'TEST-001',
                'nombre' => 'Test',
                'tipo'   => 'invalido',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo']);
    }

    /** @test */
    public function store_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('inv_productos');

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-productos.store'), ['codigo' => 'X', 'nombre' => 'X', 'tipo' => 'simple'])
            ->assertForbidden();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function show_retorna_producto_con_relaciones(): void
    {
        $producto = InvProducto::factory()->create([
            'nombre'          => 'Camisa',
            'categoria_id'    => $this->categoria->id,
            'unidad_medida_id' => $this->unidad->id,
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-productos.show', $producto))
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Camisa')
            ->assertJsonStructure(['data' => ['categoria', 'unidad_medida']]);
    }

    /** @test */
    public function show_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-productos.show', 999))
            ->assertNotFound();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    /** @test */
    public function update_modifica_producto_existente(): void
    {
        $producto = InvProducto::factory()->create(['nombre' => 'Viejo', 'tipo' => 'simple']);

        $this->actingAs($this->usuario)
            ->putJson(route('inv-productos.update', $producto), [
                'codigo' => $producto->codigo,
                'nombre' => 'Actualizado',
                'tipo'   => 'simple',
                'status' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Actualizado');
    }

    // ─── destroy / restore / forceDelete ──────────────────────────────────────

    /** @test */
    public function destroy_realiza_soft_delete(): void
    {
        $producto = InvProducto::factory()->create();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-productos.destroy', $producto))
            ->assertOk()
            ->assertJsonPath('message', 'Producto eliminado exitosamente.');

        $this->assertSoftDeleted('inv_productos', ['id' => $producto->id]);
    }

    /** @test */
    public function restore_recupera_producto_eliminado(): void
    {
        $producto = InvProducto::factory()->create();
        $producto->delete();

        $this->actingAs($this->usuario)
            ->postJson(route('inv-productos.restore', $producto->id))
            ->assertOk()
            ->assertJsonPath('message', 'Producto restaurado exitosamente.');
    }

    /** @test */
    public function force_delete_elimina_permanentemente(): void
    {
        $producto = InvProducto::factory()->create();
        $producto->delete();

        $this->actingAs($this->usuario)
            ->deleteJson(route('inv-productos.force-delete', $producto->id))
            ->assertOk()
            ->assertJsonPath('message', 'Producto eliminado permanentemente.');

        $this->assertDatabaseMissing('inv_productos', ['id' => $producto->id]);
    }

    /** @test */
    public function trashed_retorna_solo_eliminados(): void
    {
        $activo    = InvProducto::factory()->create();
        $eliminado = InvProducto::factory()->create();
        $eliminado->delete();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('inv-productos.trashed'))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($eliminado->id, $ids);
        $this->assertNotContains($activo->id, $ids);
    }

    // ─── filters / statistics ─────────────────────────────────────────────────

    /** @test */
    public function filters_retorna_tipos_y_categorias(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-productos.filters'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['status', 'tipos', 'categorias']]);
    }

    /** @test */
    public function statistics_retorna_totales_y_por_tipo(): void
    {
        InvProducto::factory()->create(['tipo' => 'simple']);
        InvProducto::factory()->create(['tipo' => 'kit']);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-productos.statistics'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['totales', 'por_tipo']]);
    }

    // ─── importar / plantilla ─────────────────────────────────────────────────

    /** @test */
    public function plantilla_descarga_csv(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('inv-productos.plantilla'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    /** @test */
    public function importar_inserta_productos_desde_csv_valido(): void
    {
        $csv = "codigo,nombre,tipo,categoria,unidad_medida,descripcion,status\n"
             . "CAM-001,Camisa Escolar,simple,{$this->categoria->nombre},{$this->unidad->nombre},Camisa blanca,1\n"
             . "PAN-001,Pantalon,simple,,,,1";

        $archivo = UploadedFile::fake()->createWithContent('catalogo.csv', $csv);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-productos.importar'), ['archivo' => $archivo])
            ->assertOk()
            ->assertJsonPath('data.insertadas', 2)
            ->assertJsonPath('data.omitidas', 0);

        $this->assertDatabaseHas('inv_productos', ['codigo' => 'CAM-001']);
    }

    /** @test */
    public function importar_omite_codigos_duplicados(): void
    {
        InvProducto::factory()->create(['codigo' => 'CAM-001']);

        $csv = "codigo,nombre,tipo\nCAM-001,Camisa,simple\nPAN-001,Pantalon,simple";
        $archivo = UploadedFile::fake()->createWithContent('catalogo.csv', $csv);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('inv-productos.importar'), ['archivo' => $archivo])
            ->assertOk();

        $this->assertEquals(1, $response->json('data.insertadas'));
        $this->assertEquals(1, $response->json('data.omitidas'));
    }

    /** @test */
    public function importar_falla_con_columnas_invalidas(): void
    {
        $csv = "col_invalida,otra_col\nvalor,valor";
        $archivo = UploadedFile::fake()->createWithContent('catalogo.csv', $csv);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-productos.importar'), ['archivo' => $archivo])
            ->assertUnprocessable();
    }

    /** @test */
    public function importar_deniega_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('inv_productos');

        $csv = "codigo,nombre,tipo\nX-001,Test,simple";
        $archivo = UploadedFile::fake()->createWithContent('catalogo.csv', $csv);

        $this->actingAs($sinPermiso)
            ->postJson(route('inv-productos.importar'), ['archivo' => $archivo])
            ->assertForbidden();
    }
}
