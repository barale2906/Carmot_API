<?php

namespace Tests\Feature\Api\Inventarios;

use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature para el stock de inventario: consulta, importación XLSX y plantilla.
 */
class InvStockTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private InvAlmacen $almacen;
    private InvProducto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'inv_stock',          'descripcion' => 'ver stock']);
        Permission::create(['name' => 'inv_stockImportar',  'descripcion' => 'importar stock']);

        $this->usuario  = User::factory()->create();
        $this->almacen  = InvAlmacen::factory()->activo()->create();
        $this->producto = InvProducto::factory()->activo()->create(['tipo' => 'simple']);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function index_retorna_lista_paginada_de_stock(): void
    {
        $this->usuario->givePermissionTo('inv_stock');

        InvStock::factory()->create([
            'almacen_id' => $this->almacen->id,
            'producto_id' => $this->producto->id,
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('inv-stock.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @test */
    public function index_deniega_sin_permiso(): void
    {
        $this->actingAs($this->usuario)
            ->getJson(route('inv-stock.index'))
            ->assertForbidden();
    }

    // ─── filters ──────────────────────────────────────────────────────────────

    /** @test */
    public function filters_retorna_almacenes_y_productos_simples_activos(): void
    {
        $this->usuario->givePermissionTo('inv_stock');

        $this->actingAs($this->usuario)
            ->getJson(route('inv-stock.filters'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['almacenes', 'productos']]);
    }

    // ─── statistics ───────────────────────────────────────────────────────────

    /** @test */
    public function statistics_retorna_totales_del_stock(): void
    {
        $this->usuario->givePermissionTo('inv_stock');

        $this->actingAs($this->usuario)
            ->getJson(route('inv-stock.statistics'))
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'total_registros', 'total_unidades_fisicas', 'total_unidades_disp',
                'total_unidades_reserv', 'productos_bajo_stock', 'almacenes_con_stock',
            ]]);
    }

    // ─── plantilla ────────────────────────────────────────────────────────────

    /** @test */
    public function plantilla_descarga_archivo_xlsx(): void
    {
        $this->usuario->givePermissionTo('inv_stockImportar');

        $response = $this->actingAs($this->usuario)
            ->get(route('inv-stock.plantilla'));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString(
            'plantilla_stock_inicial.xlsx',
            $response->headers->get('Content-Disposition')
        );
    }

    /** @test */
    public function plantilla_deniega_sin_permiso(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('inv-stock.plantilla'))
            ->assertForbidden();
    }

    // ─── importar ─────────────────────────────────────────────────────────────

    /** @test */
    public function importar_procesa_xlsx_y_crea_stock(): void
    {
        $this->usuario->givePermissionTo('inv_stockImportar');

        $archivo = $this->crearArchivoXlsx([
            ['codigo_producto', 'cantidad'],
            [$this->producto->codigo, 10],
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-stock.importar'), [
                'archivo'    => $archivo,
                'almacen_id' => $this->almacen->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.procesadas', 1)
            ->assertJsonPath('data.omitidas', 0);

        $this->assertDatabaseHas('inv_stock', [
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => 10,
            'cantidad_disponible' => 10,
        ]);
    }

    /** @test */
    public function importar_acumula_stock_si_ya_existe_el_registro(): void
    {
        $this->usuario->givePermissionTo('inv_stockImportar');

        InvStock::factory()->create([
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => 5,
            'cantidad_disponible' => 5,
            'cantidad_reservada'  => 0,
        ]);

        $archivo = $this->crearArchivoXlsx([
            ['codigo_producto', 'cantidad'],
            [$this->producto->codigo, 10],
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-stock.importar'), [
                'archivo'    => $archivo,
                'almacen_id' => $this->almacen->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.procesadas', 1);

        $this->assertDatabaseHas('inv_stock', [
            'almacen_id'          => $this->almacen->id,
            'producto_id'         => $this->producto->id,
            'cantidad_total'      => 15,
            'cantidad_disponible' => 15,
        ]);
    }

    /** @test */
    public function importar_omite_fila_con_codigo_inexistente(): void
    {
        $this->usuario->givePermissionTo('inv_stockImportar');

        $archivo = $this->crearArchivoXlsx([
            ['codigo_producto', 'cantidad'],
            ['INEXISTENTE-999', 5],
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-stock.importar'), [
                'archivo'    => $archivo,
                'almacen_id' => $this->almacen->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.procesadas', 0)
            ->assertJsonPath('data.omitidas', 1);
    }

    /** @test */
    public function importar_omite_fila_con_cantidad_cero_o_negativa(): void
    {
        $this->usuario->givePermissionTo('inv_stockImportar');

        $archivo = $this->crearArchivoXlsx([
            ['codigo_producto', 'cantidad'],
            [$this->producto->codigo, 0],
            [$this->producto->codigo, -5],
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-stock.importar'), [
                'archivo'    => $archivo,
                'almacen_id' => $this->almacen->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.procesadas', 0)
            ->assertJsonPath('data.omitidas', 2);
    }

    /** @test */
    public function importar_rechaza_xlsx_con_columnas_faltantes(): void
    {
        $this->usuario->givePermissionTo('inv_stockImportar');

        $archivo = $this->crearArchivoXlsx([
            ['codigo_producto'],
            [$this->producto->codigo],
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-stock.importar'), [
                'archivo'    => $archivo,
                'almacen_id' => $this->almacen->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'cantidad'));
    }

    /** @test */
    public function importar_falla_sin_almacen_id(): void
    {
        $this->usuario->givePermissionTo('inv_stockImportar');

        $archivo = $this->crearArchivoXlsx([
            ['codigo_producto', 'cantidad'],
            [$this->producto->codigo, 5],
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-stock.importar'), ['archivo' => $archivo])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['almacen_id']);
    }

    /** @test */
    public function importar_falla_sin_archivo(): void
    {
        $this->usuario->givePermissionTo('inv_stockImportar');

        $this->actingAs($this->usuario)
            ->postJson(route('inv-stock.importar'), ['almacen_id' => $this->almacen->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
    }

    /** @test */
    public function importar_rechaza_archivo_que_no_es_xlsx(): void
    {
        $this->usuario->givePermissionTo('inv_stockImportar');

        $archivo = UploadedFile::fake()->create('datos.csv', 10, 'text/csv');

        $this->actingAs($this->usuario)
            ->postJson(route('inv-stock.importar'), [
                'archivo'    => $archivo,
                'almacen_id' => $this->almacen->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
    }

    /** @test */
    public function importar_deniega_sin_permiso(): void
    {
        $archivo = $this->crearArchivoXlsx([['codigo_producto', 'cantidad']]);

        $this->actingAs($this->usuario)
            ->postJson(route('inv-stock.importar'), [
                'archivo'    => $archivo,
                'almacen_id' => $this->almacen->id,
            ])
            ->assertForbidden();
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    /**
     * Crea un UploadedFile XLSX en memoria a partir de filas de datos.
     *
     * @param array<array<mixed>> $filas
     */
    private function crearArchivoXlsx(array $filas): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();

        foreach ($filas as $i => $fila) {
            foreach ($fila as $j => $valor) {
                // setCellValue acepta array [col, row] en PhpSpreadsheet v2+
                $hoja->setCellValue([$j + 1, $i + 1], $valor);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'inv_stock_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        return new UploadedFile(
            $ruta,
            'stock_inicial.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
