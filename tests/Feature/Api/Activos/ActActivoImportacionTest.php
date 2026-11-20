<?php

namespace Tests\Feature\Api\Activos;

use App\Models\Activos\ActActivo;
use App\Models\Activos\ActCategoria;
use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use App\Models\User;
use App\Services\Activos\ActActivoImportacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Carga masiva de activos fijos: plantilla descargable, registro con movimiento de ingreso,
 * restricción por sede y rechazo total ante filas con error.
 */
class ActActivoImportacionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sede $sede;

    private Sede $sedeAjena;

    private Area $taller;

    private ActCategoria $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['act_activos', 'act_activoCrear'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->sede = Sede::factory()->create(['nombre' => 'Sede Centro']);
        $this->sedeAjena = Sede::factory()->create(['nombre' => 'Sede Ajena']);
        $this->taller = Area::factory()->create(['nombre' => 'Taller de motores', 'status' => 1]);
        $this->taller->sedes()->attach($this->sede->id);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['act_activos', 'act_activoCrear']);
        $this->usuario->sedes()->attach($this->sede->id);

        $this->categoria = ActCategoria::factory()->create(['nombre' => 'Herramienta mayor']);
    }

    /**
     * Fila mínima válida indexada por columna.
     *
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function fila(array $override = []): array
    {
        return array_merge([
            'nombre' => 'Elevador de dos columnas',
            'categoria' => 'Herramienta mayor',
            'sede' => 'Sede Centro',
            'fecha_ingreso' => now()->subMonth()->toDateString(),
            'valor' => 18500000,
        ], $override);
    }

    /**
     * Archivo Excel con los encabezados de la plantilla y las filas indicadas.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function archivo(array $filas): UploadedFile
    {
        $columnas = ActActivoImportacionService::columnas();
        $spreadsheet = new Spreadsheet;
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray($columnas, null, 'A1');
        foreach ($filas as $i => $fila) {
            $hoja->fromArray(array_map(fn ($columna) => $fila[$columna] ?? null, $columnas), null, 'A'.($i + 2));
        }

        $ruta = tempnam(sys_get_temp_dir(), 'actimp').'.xlsx';
        (new Xlsx($spreadsheet))->save($ruta);

        return new UploadedFile($ruta, 'activos.xlsx', null, null, true);
    }

    private function importar(UploadedFile $archivo, ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->usuario)
            ->post(route('act-activos.importar'), ['archivo' => $archivo], ['Accept' => 'application/json']);
    }

    /** @test */
    public function descarga_la_plantilla_con_catalogos_e_instrucciones(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->get(route('act-activos.plantilla'))
            ->assertOk()
            ->assertDownload('plantilla-activos.xlsx');

        $ruta = tempnam(sys_get_temp_dir(), 'actpla').'.xlsx';
        file_put_contents($ruta, $respuesta->streamedContent());
        $libro = IOFactory::load($ruta);

        $this->assertSame(['Activos', 'Instrucciones', 'Ejemplo', 'Catalogos'], $libro->getSheetNames());
        $this->assertSame(ActActivoImportacionService::columnas(), array_filter($libro->getSheetByName('Activos')->rangeToArray('A1:M1')[0]));

        $catalogos = json_encode($libro->getSheetByName('Catalogos')->toArray());
        $this->assertStringContainsString('Herramienta mayor', $catalogos);
        $this->assertStringContainsString('Sede Centro', $catalogos);
        $this->assertStringContainsString('Taller de motores', $catalogos);
        $this->assertStringNotContainsString('Sede Ajena', $catalogos);
        $this->assertStringNotContainsString('De baja', $catalogos);
    }

    /** @test */
    public function el_archivo_de_la_plantilla_diligenciado_se_importa(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'actpla').'.xlsx';
        file_put_contents($ruta, $this->actingAs($this->usuario)->get(route('act-activos.plantilla'))->streamedContent());
        $libro = IOFactory::load($ruta);
        $libro->getSheetByName('Activos')->fromArray(['Compresor', null, 'Herramienta mayor', 'Sede Centro', null, null, null, null, null, now()->toDateString(), 100], null, 'A2');
        (new Xlsx($libro))->save($ruta);

        $this->importar(new UploadedFile($ruta, 'activos.xlsx', null, null, true))->assertCreated();

        $this->assertDatabaseHas('act_activos', ['nombre' => 'Compresor']);
    }

    /** @test */
    public function registra_los_activos_con_su_movimiento_de_ingreso(): void
    {
        $responsable = User::factory()->create(['documento' => '1052345678']);
        $responsable->assignRole(Role::create(['name' => 'Coordinador']));

        $this->importar($this->archivo([
            $this->fila([
                'area' => 'taller de motores', 'responsable_documento' => '1052345678', 'marca' => 'Rotary',
                'serial' => 'RT-1', 'estado' => 'En mantenimiento',
            ]),
            $this->fila(['nombre' => 'Proyector', 'fecha_ingreso' => now()->subDay()->format('d/m/Y'), 'valor' => '0']),
        ]))
            ->assertCreated()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.area.nombre', 'Taller de motores');

        $primero = ActActivo::where('nombre', 'Elevador de dos columnas')->firstOrFail();
        $this->assertSame($this->sede->id, $primero->sede_id);
        $this->assertSame($this->taller->id, $primero->area_id);
        $this->assertSame($responsable->id, $primero->responsable_id);
        $this->assertSame('mantenimiento', $primero->estado);

        $segundo = ActActivo::where('nombre', 'Proyector')->firstOrFail();
        $this->assertSame('operativo', $segundo->estado);
        $this->assertNull($segundo->area_id);

        $this->assertDatabaseCount('act_movimientos', 2);
        $this->assertDatabaseHas('act_movimientos', ['activo_id' => $primero->id, 'tipo' => 'ingreso', 'usuario_id' => $this->usuario->id]);
    }

    /** @test */
    public function es_todo_o_nada_y_reporta_el_numero_de_fila(): void
    {
        $this->importar($this->archivo([
            $this->fila(),
            $this->fila(['nombre' => 'Sin categoria', 'categoria' => 'No existe']),
            $this->fila(['nombre' => 'Futuro', 'fecha_ingreso' => now()->addDay()->toDateString()]),
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errores.0.fila', 3)
            ->assertJsonPath('errores.1.fila', 4)
            ->assertJsonCount(2, 'errores');

        $this->assertDatabaseCount('act_activos', 0);
        $this->assertDatabaseCount('act_movimientos', 0);
    }

    /** @test */
    public function rechaza_sede_ajena_area_de_otra_sede_responsable_estudiante_y_estado_baja(): void
    {
        $estudiante = User::factory()->create(['documento' => '999']);
        $estudiante->assignRole(Role::create(['name' => User::ROL_ALUMNO]));

        $respuesta = $this->importar($this->archivo([
            $this->fila(['sede' => 'Sede Ajena']),
            $this->fila(['area' => 'Inexistente']),
            $this->fila(['responsable_documento' => '999']),
            $this->fila(['estado' => 'baja']),
            $this->fila(['nombre' => null, 'valor' => 'abc', 'fecha_ingreso' => 'ayer']),
        ]))->assertUnprocessable();

        $this->assertCount(5, $respuesta->json('errores'));
        $this->assertStringContainsString('no está asignada', $respuesta->json('errores.0.mensajes.0'));
        $this->assertStringContainsString('no pertenece a la sede', $respuesta->json('errores.1.mensajes.0'));
        $this->assertStringContainsString('no es personal', $respuesta->json('errores.2.mensajes.0'));
        $this->assertStringContainsString('no es válido', $respuesta->json('errores.3.mensajes.0'));
        $this->assertGreaterThanOrEqual(3, count($respuesta->json('errores.4.mensajes')));
        $this->assertDatabaseCount('act_activos', 0);
    }

    /** @test */
    public function acepta_csv_con_punto_y_coma(): void
    {
        $fecha = now()->toDateString();
        $csv = "\xEF\xBB\xBFnombre;categoria;sede;fecha_ingreso;valor\nCompresor CSV;Herramienta mayor;Sede Centro;{$fecha};1500\n";

        $this->importar(UploadedFile::fake()->createWithContent('activos.csv', $csv))->assertCreated();

        $this->assertDatabaseHas('act_activos', ['nombre' => 'Compresor CSV']);
    }

    /** @test */
    public function rechaza_archivos_sin_el_formato_de_la_plantilla_o_vacios(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'actimp').'.csv';
        file_put_contents($ruta, "foo,bar\n1,2\n");
        $this->importar(new UploadedFile($ruta, 'x.csv', null, null, true))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');

        $this->importar($this->archivo([]))->assertUnprocessable()->assertJsonValidationErrors('archivo');
        $this->actingAs($this->usuario)->postJson(route('act-activos.importar'), [])->assertUnprocessable()->assertJsonValidationErrors('archivo');
    }

    /** @test */
    public function exige_el_permiso_de_crear_activos(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('act_activos');

        $this->actingAs($sinPermiso)->get(route('act-activos.plantilla'))->assertForbidden();
        $this->importar($this->archivo([$this->fila()]), $sinPermiso)->assertForbidden();
    }
}
