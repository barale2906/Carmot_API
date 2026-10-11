<?php

namespace Tests\Feature\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocDocumento;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas de la carga de documentos firmados y escaneados de una matrícula.
 *
 * Se sube un archivo por documento: identificado por su tipo o, sin tipo (la hoja
 * de matrícula u otro soporte), por su descripción. Subir de nuevo el mismo
 * documento manda el anterior a la papelera.
 */
class DocEscaneadoMatriculaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private DocTipoDocumento $contrato;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'aca_documentos'      => 'ver la bitácora de documentos',
            'aca_documentoSubir'  => 'cargar documentos escaneados',
            'aca_documentoAnular' => 'eliminar registros de la bitácora',
        ];

        foreach ($permisos as $nombre => $descripcion) {
            Permission::create(['name' => $nombre, 'descripcion' => $descripcion]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(array_keys($permisos));

        $this->contrato  = DocTipoDocumento::factory()->conformaMatricula()->create(['nombre' => 'Contrato de matrícula']);
        $this->matricula = Matricula::factory()->create(['fecha_matricula' => '2026-10-01']);

        Storage::fake(DocDocumento::DISCO_ARCHIVOS);
    }

    /**
     * Sube un escaneado para la matrícula de la prueba.
     *
     * @param array<string, mixed> $datos
     * @param User|null            $usuario
     * @return \Illuminate\Testing\TestResponse
     */
    private function subir(array $datos, ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->usuario)
            ->post(
                route('documentacion.documentos.matricula.escaneados', $this->matricula),
                $datos,
                ['Accept' => 'application/json']
            );
    }

    /**
     * Archivo PDF de prueba.
     *
     * @param string $nombre
     * @return UploadedFile
     */
    private function pdf(string $nombre = 'contrato-firmado.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 200, 'application/pdf');
    }

    /** @test */
    public function sube_el_escaneado_de_un_tipo_de_documento(): void
    {
        $response = $this->subir([
            'tipo_documento_id' => $this->contrato->id,
            'archivo'           => $this->pdf(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tipo_documento_id', $this->contrato->id)
            ->assertJsonPath('data.nombre_documento', 'Contrato de matrícula')
            ->assertJsonPath('data.origen', DocDocumento::ORIGEN_SUBIDO)
            ->assertJsonPath('data.nombre_original', 'contrato-firmado.pdf')
            ->assertJsonPath('data.tiene_archivo', true)
            ->assertJsonMissingPath('data.archivo_path');

        $documento = DocDocumento::findOrFail($response->json('data.id'));

        $this->assertSame(Matricula::class, $documento->entidad_type);
        $this->assertSame($this->matricula->id, $documento->entidad_id);
        $this->assertSame($this->usuario->id, $documento->generado_por);
        $this->assertMatchesRegularExpression('#^documentos/' . $this->usuario->id . '_\d{8}_\d{6}\.pdf$#', $documento->archivo_path);
        Storage::disk(DocDocumento::DISCO_ARCHIVOS)->assertExists($documento->archivo_path);
    }

    /** @test */
    public function sube_la_hoja_de_matricula_identificada_por_su_descripcion(): void
    {
        $this->subir([
            'descripcion' => DocDocumento::DESCRIPCION_HOJA_MATRICULA,
            'archivo'     => UploadedFile::fake()->image('hoja.jpg'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.tipo_documento_id', null)
            ->assertJsonPath('data.tipo_documento', null)
            ->assertJsonPath('data.descripcion', 'Hoja de matrícula')
            ->assertJsonPath('data.nombre_documento', 'Hoja de matrícula');
    }

    /** @test */
    public function subir_de_nuevo_el_mismo_documento_manda_el_anterior_a_la_papelera(): void
    {
        $primero = $this->subir(['tipo_documento_id' => $this->contrato->id, 'archivo' => $this->pdf('v1.pdf')])->json('data.id');
        $hoja    = $this->subir(['descripcion' => 'Hoja de matrícula', 'archivo' => $this->pdf('hoja.pdf')])->json('data.id');
        $segundo = $this->subir(['tipo_documento_id' => $this->contrato->id, 'archivo' => $this->pdf('v2.pdf')])->json('data.id');

        $this->assertSoftDeleted('doc_documentos', ['id' => $primero]);
        $this->assertNotSoftDeleted('doc_documentos', ['id' => $segundo]);
        // Otro documento de la misma matrícula no se toca.
        $this->assertNotSoftDeleted('doc_documentos', ['id' => $hoja]);

        // El archivo reemplazado se conserva por si se restaura.
        Storage::disk(DocDocumento::DISCO_ARCHIVOS)->assertExists(DocDocumento::withTrashed()->find($primero)->archivo_path);
    }

    /** @test */
    public function no_reemplaza_el_escaneado_de_otra_matricula(): void
    {
        $otra = DocDocumento::create([
            'tipo_documento_id' => $this->contrato->id,
            'entidad_type'      => Matricula::class,
            'entidad_id'        => Matricula::factory()->create()->id,
            'origen'            => DocDocumento::ORIGEN_SUBIDO,
        ]);

        $this->subir(['tipo_documento_id' => $this->contrato->id, 'archivo' => $this->pdf()])->assertCreated();

        $this->assertNotSoftDeleted('doc_documentos', ['id' => $otra->id]);
    }

    /** @test */
    public function valida_el_archivo_y_que_se_indique_el_documento(): void
    {
        $this->subir([])->assertJsonValidationErrors(['archivo', 'descripcion']);

        $this->subir([
            'tipo_documento_id' => $this->contrato->id,
            'archivo'           => UploadedFile::fake()->create('notas.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertJsonValidationErrors(['archivo']);

        $this->subir([
            'tipo_documento_id' => $this->contrato->id,
            'archivo'           => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'),
        ])->assertJsonValidationErrors(['archivo']);

        $this->assertDatabaseCount('doc_documentos', 0);
    }

    /** @test */
    public function rechaza_un_tipo_inactivo_o_de_otra_entidad(): void
    {
        $inactivo = DocTipoDocumento::factory()->conformaMatricula()->inactivo()->create();
        $general  = DocTipoDocumento::factory()->create();

        $this->subir(['tipo_documento_id' => $inactivo->id, 'archivo' => $this->pdf()])
            ->assertJsonValidationErrors(['tipo_documento_id']);

        $this->subir(['tipo_documento_id' => $general->id, 'archivo' => $this->pdf()])
            ->assertJsonValidationErrors(['tipo_documento_id']);
    }

    /** @test */
    public function deniega_la_carga_sin_permiso(): void
    {
        $this->subir(
            ['tipo_documento_id' => $this->contrato->id, 'archivo' => $this->pdf()],
            User::factory()->create()
        )->assertForbidden();

        $this->assertDatabaseCount('doc_documentos', 0);
    }

    /** @test */
    public function responde_404_al_subir_para_una_matricula_inexistente(): void
    {
        $this->actingAs($this->usuario)
            ->post(
                route('documentacion.documentos.matricula.escaneados', 999999),
                ['tipo_documento_id' => $this->contrato->id, 'archivo' => $this->pdf()],
                ['Accept' => 'application/json']
            )
            ->assertNotFound();
    }

    /** @test */
    public function descarga_el_archivo_escaneado_con_su_nombre_original(): void
    {
        $id = $this->subir(['tipo_documento_id' => $this->contrato->id, 'archivo' => $this->pdf()])->json('data.id');

        $this->actingAs($this->usuario)
            ->get(route('documentacion.documentos.archivo', $id))
            ->assertOk()
            ->assertDownload('contrato-firmado.pdf');
    }

    /** @test */
    public function responde_404_si_el_registro_no_tiene_archivo(): void
    {
        $emision = DocDocumento::create([
            'tipo_documento_id' => $this->contrato->id,
            'entidad_type'      => Matricula::class,
            'entidad_id'        => $this->matricula->id,
            'origen'            => DocDocumento::ORIGEN_GENERADO,
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('documentacion.documentos.archivo', $emision))
            ->assertNotFound();
    }

    /** @test */
    public function deniega_la_descarga_sin_permiso(): void
    {
        $id = $this->subir(['tipo_documento_id' => $this->contrato->id, 'archivo' => $this->pdf()])->json('data.id');

        $this->actingAs(User::factory()->create())
            ->getJson(route('documentacion.documentos.archivo', $id))
            ->assertForbidden();
    }

    /** @test */
    public function la_bitacora_lista_los_escaneados_de_la_matricula(): void
    {
        $this->subir(['tipo_documento_id' => $this->contrato->id, 'archivo' => $this->pdf()]);
        $this->subir(['descripcion' => 'Hoja de matrícula', 'archivo' => $this->pdf('hoja.pdf')]);

        $this->actingAs($this->usuario)
            ->getJson(route('documentacion.documentos.index', [
                'entidad_type' => Matricula::class,
                'entidad_id'   => $this->matricula->id,
                'origen'       => DocDocumento::ORIGEN_SUBIDO,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function eliminar_definitivamente_borra_el_archivo(): void
    {
        $id   = $this->subir(['tipo_documento_id' => $this->contrato->id, 'archivo' => $this->pdf()])->json('data.id');
        $ruta = DocDocumento::findOrFail($id)->archivo_path;

        $this->actingAs($this->usuario)->deleteJson(route('documentacion.documentos.destroy', $id))->assertOk();
        Storage::disk(DocDocumento::DISCO_ARCHIVOS)->assertExists($ruta);

        $this->actingAs($this->usuario)->deleteJson(route('documentacion.documentos.force-delete', $id))->assertOk();
        Storage::disk(DocDocumento::DISCO_ARCHIVOS)->assertMissing($ruta);
    }
}
