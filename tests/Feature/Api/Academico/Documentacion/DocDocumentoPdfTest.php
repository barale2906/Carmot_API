<?php

namespace Tests\Feature\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocDocumento;
use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use App\Models\User;
use App\Services\Academico\Documentacion\DocGeneracionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas de la conversión de documentos a PDF.
 */
class DocDocumentoPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private DocTipoDocumento $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_documentos', 'descripcion' => 'ver documentos']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo('aca_documentos');

        $this->tipo = DocTipoDocumento::factory()->atadoAMatricula()->create([
            'nombre'         => 'Contrato de matrícula',
            'prefijo_numero' => 'CONT',
        ]);
        $this->tipo->variables()->create(['variable_key' => 'estudiante.name']);
    }

    /**
     * Genera un documento listo para convertir a PDF.
     *
     * @return DocDocumento
     */
    private function documentoGenerado(): DocDocumento
    {
        $plantilla = DocPlantilla::factory()->activa('2020-01-01')->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '<h1>Contrato</h1><p>Suscrito por {{ estudiante.name }}.</p>',
        ]);

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        return app(DocGeneracionService::class)->generar(
            $this->tipo,
            $plantilla,
            $matricula,
            Carbon::parse('2024-06-15'),
            $this->usuario
        );
    }

    /** @test */
    public function descarga_el_pdf_del_documento(): void
    {
        Storage::fake('public');

        $documento = $this->documentoGenerado();

        $response = $this->actingAs($this->usuario)
            ->get(route('documentacion.documentos.pdf', $documento));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload($documento->numero_documento . '.pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /** @test */
    public function el_pdf_se_arma_al_momento_y_no_se_almacena(): void
    {
        Storage::fake('public');

        $documento = $this->documentoGenerado();

        $this->actingAs($this->usuario)
            ->get(route('documentacion.documentos.pdf', $documento))
            ->assertOk();

        // El archivo no se guarda: cada descarga lo reconstruye desde el contenido congelado.
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    /** @test */
    public function dos_descargas_producen_el_mismo_documento(): void
    {
        $documento = $this->documentoGenerado();

        $primera = $this->actingAs($this->usuario)
            ->get(route('documentacion.documentos.pdf', $documento))
            ->assertOk();

        $segunda = $this->actingAs($this->usuario)
            ->get(route('documentacion.documentos.pdf', $documento))
            ->assertOk();

        // El contenido del documento no cambia entre descargas.
        $this->assertSame(
            $documento->contenido_renderizado,
            $documento->fresh()->contenido_renderizado
        );
        $this->assertStringStartsWith('%PDF', $primera->getContent());
        $this->assertStringStartsWith('%PDF', $segunda->getContent());
    }

    /** @test */
    public function deniega_la_descarga_sin_permiso(): void
    {
        $documento  = $this->documentoGenerado();
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->getJson(route('documentacion.documentos.pdf', $documento))
            ->assertForbidden();
    }
}
