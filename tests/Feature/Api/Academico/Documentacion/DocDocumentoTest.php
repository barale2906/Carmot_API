<?php

namespace Tests\Feature\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocDocumento;
use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use App\Models\User;
use App\Services\Academico\Documentacion\DocPlantillaPublicacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas de la impresión de documentos y de la bitácora de emisiones.
 *
 * Los documentos no se almacenan: se arman en cada impresión con los datos del
 * estudiante y la plantilla que corresponde según el tipo.
 */
class DocDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private DocTipoDocumento $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'aca_documentos'       => 'ver la bitácora de documentos',
            'aca_documentoGenerar' => 'imprimir documentos',
            'aca_documentoAnular'  => 'eliminar registros de la bitácora',
        ];

        foreach ($permisos as $nombre => $descripcion) {
            Permission::create(['name' => $nombre, 'descripcion' => $descripcion]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(array_keys($permisos));

        $this->tipo = DocTipoDocumento::factory()->conformaMatricula()->create([
            'codigo' => 'CONTRATO',
            'nombre' => 'Contrato de matrícula',
        ]);

        foreach (['numero_matricula', 'estudiante.name', 'monto_letras'] as $variable) {
            $this->tipo->variables()->create(['variable_key' => $variable]);
        }
    }

    /**
     * Crea una versión activa del tipo con el contenido indicado.
     *
     * @param string $fechaInicio
     * @param string $contenido
     * @param int    $version
     * @return DocPlantilla
     */
    private function plantillaActiva(string $fechaInicio, string $contenido, int $version = 1): DocPlantilla
    {
        return DocPlantilla::factory()->activa($fechaInicio)->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => $contenido,
            'version'           => $version,
        ]);
    }

    /**
     * Pide el documento en HTML para una matrícula.
     *
     * @param Matricula $matricula
     * @return \Illuminate\Testing\TestResponse
     */
    private function render(Matricula $matricula)
    {
        return $this->actingAs($this->usuario)
            ->getJson(route('documentacion.documentos.render', [
                'tipo_documento_id' => $this->tipo->id,
                'entidad_id'        => $matricula->id,
            ]));
    }

    /** @test */
    public function arma_el_documento_resolviendo_las_variables(): void
    {
        $plantilla = $this->plantillaActiva(
            '2020-01-01',
            '<p>Matrícula {{ numero_matricula }}: {{ estudiante.name }} pagará {{ monto_letras }}.</p>'
        );

        $matricula = Matricula::factory()->create([
            'fecha_matricula' => '2024-06-15',
            'monto'           => 1250000,
        ]);

        $response = $this->render($matricula);

        $response->assertOk()
            ->assertJsonPath('data.plantilla_id', $plantilla->id)
            ->assertJsonPath('data.fecha_referencia', '2024-06-15');

        $contenido = $response->json('data.contenido');

        $this->assertStringContainsString('Matrícula ' . $matricula->id, $contenido);
        $this->assertStringContainsString($matricula->estudiante->name, $contenido);
        $this->assertStringContainsString('UN MILLON DOSCIENTOS CINCUENTA MIL PESOS M/CTE', $contenido);
        $this->assertStringNotContainsString('{{', $contenido);
    }

    /** @test */
    public function no_guarda_el_contenido_del_documento(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>{{ estudiante.name }}</p>');
        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $this->render($matricula)->assertOk();

        // La bitácora guarda el rastro de la impresión, no el documento.
        $emision = DocDocumento::firstOrFail();

        $this->assertSame(DocDocumento::ORIGEN_GENERADO, $emision->origen);
        $this->assertSame($matricula->id, $emision->entidad_id);
        $this->assertSame($this->usuario->id, $emision->generado_por);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('doc_documentos', 'contenido_renderizado'));
    }

    /** @test */
    public function un_documento_de_matricula_usa_la_plantilla_vigente_a_su_fecha(): void
    {
        DocPlantilla::factory()->historica('2020-01-01', '2025-12-31')->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '<p>Condiciones antiguas de {{ estudiante.name }}</p>',
            'version'           => 1,
        ]);
        $this->plantillaActiva('2026-01-01', '<p>Condiciones nuevas de {{ estudiante.name }}</p>', 2);

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $this->assertStringContainsString(
            'Condiciones antiguas',
            $this->render($matricula)->assertOk()->json('data.contenido')
        );
    }

    /** @test */
    public function reimprimir_devuelve_lo_mismo_aunque_se_publique_una_version_nueva(): void
    {
        $original = $this->plantillaActiva('2020-01-01', '<p>Contenido original de {{ estudiante.name }}</p>');
        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $primera = $this->render($matricula)->assertOk()->json('data');

        $nueva = DocPlantilla::factory()->aprobada()->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '<p>Contenido nuevo de {{ estudiante.name }}</p>',
            'version'           => 2,
        ]);
        app(DocPlantillaPublicacionService::class)->activar($nueva, Carbon::today());

        $segunda = $this->render($matricula)->assertOk()->json('data');

        $this->assertSame($original->id, $segunda['plantilla_id']);
        $this->assertSame($primera['contenido'], $segunda['contenido']);
        $this->assertStringContainsString('Contenido original', $segunda['contenido']);
    }

    /** @test */
    public function un_documento_que_no_conforma_matricula_usa_la_plantilla_vigente_hoy(): void
    {
        $certificado = DocTipoDocumento::factory()->sinFecha()->create([
            'codigo' => 'CERTIFICADO',
            'nombre' => 'Certificado de estudio',
        ]);

        DocPlantilla::factory()->historica('2020-01-01', '2025-12-31')->create([
            'tipo_documento_id' => $certificado->id,
            'contenido_html'    => '<p>Formato antiguo</p>',
            'version'           => 1,
        ]);
        DocPlantilla::factory()->activa('2026-01-01')->create([
            'tipo_documento_id' => $certificado->id,
            'contenido_html'    => '<p>Formato vigente</p>',
            'version'           => 2,
        ]);

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('documentacion.documentos.render', [
                'tipo_documento_id' => $certificado->id,
                'entidad_id'        => $matricula->id,
            ]));

        $this->assertStringContainsString('Formato vigente', $response->assertOk()->json('data.contenido'));
    }

    /** @test */
    public function se_puede_imprimir_cuantas_veces_sea_necesario(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>{{ estudiante.name }}</p>');
        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        foreach (range(1, 3) as $vez) {
            $this->render($matricula)->assertOk();
        }

        // Cada impresión deja su rastro, sin bloquear las siguientes.
        $this->assertDatabaseCount('doc_documentos', 3);
    }

    /** @test */
    public function descarga_el_pdf_sin_almacenarlo(): void
    {
        Storage::fake('public');

        $this->plantillaActiva('2020-01-01', '<h1>Contrato</h1><p>{{ estudiante.name }}</p>');
        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $response = $this->actingAs($this->usuario)
            ->get(route('documentacion.documentos.pdf', [
                'tipo_documento_id' => $this->tipo->id,
                'entidad_id'        => $matricula->id,
            ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('CONTRATO-' . $matricula->id . '.pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('doc_documentos', 1);
    }

    /** @test */
    public function rechaza_imprimir_si_no_hay_plantilla_vigente(): void
    {
        DocPlantilla::factory()->create(['tipo_documento_id' => $this->tipo->id]);
        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $this->render($matricula)->assertStatus(422);

        $this->assertDatabaseCount('doc_documentos', 0);
    }

    /** @test */
    public function rechaza_imprimir_sin_el_registro_asociado(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>Contenido</p>');

        $this->actingAs($this->usuario)
            ->getJson(route('documentacion.documentos.render', ['tipo_documento_id' => $this->tipo->id]))
            ->assertJsonValidationErrors(['entidad_id']);
    }

    /** @test */
    public function rechaza_imprimir_con_un_tipo_inactivo(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>Contenido</p>');
        $this->tipo->update(['status' => 0]);

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $this->render($matricula)->assertJsonValidationErrors(['tipo_documento_id']);
    }

    /** @test */
    public function la_bitacora_se_filtra_por_registro(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>{{ estudiante.name }}</p>');

        $matriculaUno = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);
        $matriculaDos = Matricula::factory()->create(['fecha_matricula' => '2024-07-20']);

        $this->render($matriculaUno)->assertOk();
        $this->render($matriculaDos)->assertOk();

        $response = $this->actingAs($this->usuario)
            ->getJson(route('documentacion.documentos.index', [
                'entidad_type' => Matricula::class,
                'entidad_id'   => $matriculaUno->id,
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entidad_id', $matriculaUno->id)
            ->assertJsonPath('data.0.origen_text', 'Impresión generada');
    }

    /** @test */
    public function la_bitacora_no_expone_contenido(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>{{ estudiante.name }}</p>');
        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);
        $this->render($matricula)->assertOk();

        $this->actingAs($this->usuario)
            ->getJson(route('documentacion.documentos.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.contenido')
            ->assertJsonMissingPath('data.0.contenido_renderizado');
    }

    /** @test */
    public function deniega_la_impresion_sin_permiso(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>Contenido</p>');
        $matricula  = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('documentacion.documentos.render', [
                'tipo_documento_id' => $this->tipo->id,
                'entidad_id'        => $matricula->id,
            ]))
            ->assertForbidden();
    }
}
