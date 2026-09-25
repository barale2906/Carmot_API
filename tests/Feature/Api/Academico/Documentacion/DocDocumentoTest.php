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
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas de la generación, numeración y anulación de documentos.
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
            'aca_documentos'       => 'ver documentos',
            'aca_documentoGenerar' => 'generar documentos',
            'aca_documentoAnular'  => 'anular documentos',
        ];

        foreach ($permisos as $nombre => $descripcion) {
            Permission::create(['name' => $nombre, 'descripcion' => $descripcion]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(array_keys($permisos));

        $this->tipo = DocTipoDocumento::factory()->atadoAMatricula()->create([
            'nombre'         => 'Contrato de matrícula',
            'prefijo_numero' => 'CONT',
        ]);

        foreach (['estudiante.name', 'monto_letras', 'documento.numero'] as $variable) {
            $this->tipo->variables()->create(['variable_key' => $variable]);
        }
    }

    /**
     * Crea una versión de plantilla activa con contenido que usa variables.
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

    /** @test */
    public function genera_un_documento_resolviendo_las_variables(): void
    {
        $plantilla = $this->plantillaActiva(
            '2020-01-01',
            '<p>{{ documento.numero }} — {{ estudiante.name }} pagará {{ monto_letras }}.</p>'
        );

        $matricula = Matricula::factory()->create([
            'fecha_matricula' => '2024-06-15',
            'monto'           => 1250000,
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.generar'), [
                'tipo_documento_id' => $this->tipo->id,
                'entidad_id'        => $matricula->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.plantilla_id', $plantilla->id)
            ->assertJsonPath('data.numero_documento', 'CONT-' . Carbon::today()->year . '-000001')
            ->assertJsonPath('data.fecha_referencia', '2024-06-15')
            ->assertJsonPath('data.entidad_type', Matricula::class)
            ->assertJsonPath('data.status_text', 'Vigente');

        $contenido = $response->json('data.contenido_renderizado');

        $this->assertStringContainsString($matricula->estudiante->name, $contenido);
        $this->assertStringContainsString('UN MILLON DOSCIENTOS CINCUENTA MIL PESOS M/CTE', $contenido);
        $this->assertStringNotContainsString('{{', $contenido);
    }

    /** @test */
    public function usa_la_version_vigente_en_la_fecha_de_matricula(): void
    {
        DocPlantilla::factory()->historica('2020-01-01', '2025-12-31')->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '<p>Condiciones antiguas de {{ estudiante.name }}</p>',
            'version'           => 1,
        ]);
        $this->plantillaActiva('2026-01-01', '<p>Condiciones nuevas de {{ estudiante.name }}</p>', 2);

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.generar'), [
                'tipo_documento_id' => $this->tipo->id,
                'entidad_id'        => $matricula->id,
            ]);

        $response->assertCreated();
        $this->assertStringContainsString('Condiciones antiguas', $response->json('data.contenido_renderizado'));
    }

    /** @test */
    public function el_contenido_generado_no_cambia_al_publicar_una_version_nueva(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>Contenido original de {{ estudiante.name }}</p>');

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $generado = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.generar'), [
                'tipo_documento_id' => $this->tipo->id,
                'entidad_id'        => $matricula->id,
            ])->json('data');

        $nueva = DocPlantilla::factory()->aprobada()->create([
            'tipo_documento_id' => $this->tipo->id,
            'contenido_html'    => '<p>Contenido nuevo de {{ estudiante.name }}</p>',
            'version'           => 2,
        ]);
        app(DocPlantillaPublicacionService::class)->activar($nueva, Carbon::today());

        $response = $this->actingAs($this->usuario)
            ->getJson(route('documentacion.documentos.show', $generado['id']));

        $response->assertOk()
            ->assertJsonPath('data.plantilla_id', $generado['plantilla_id']);

        $this->assertStringContainsString('Contenido original', $response->json('data.contenido_renderizado'));
    }

    /** @test */
    public function numera_los_documentos_consecutivamente(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>{{ estudiante.name }}</p>');

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);
        $anio      = Carbon::today()->year;

        foreach (['000001', '000002', '000003'] as $consecutivo) {
            $this->actingAs($this->usuario)
                ->postJson(route('documentacion.documentos.generar'), [
                    'tipo_documento_id' => $this->tipo->id,
                    'entidad_id'        => $matricula->id,
                ])
                ->assertCreated()
                ->assertJsonPath('data.numero_documento', "CONT-{$anio}-{$consecutivo}");
        }
    }

    /** @test */
    public function cada_tipo_de_documento_lleva_su_propia_numeracion(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>{{ estudiante.name }}</p>');

        $certificado = DocTipoDocumento::factory()->atadoAMatricula()->create([
            'nombre'         => 'Certificado de estudio',
            'prefijo_numero' => 'CERT',
        ]);
        DocPlantilla::factory()->activa('2020-01-01')->create([
            'tipo_documento_id' => $certificado->id,
            'contenido_html'    => '<p>Certificado</p>',
        ]);

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);
        $anio      = Carbon::today()->year;

        foreach ([$this->tipo->id => "CONT-{$anio}-000001", $certificado->id => "CERT-{$anio}-000001"] as $tipoId => $esperado) {
            $this->actingAs($this->usuario)
                ->postJson(route('documentacion.documentos.generar'), [
                    'tipo_documento_id' => $tipoId,
                    'entidad_id'        => $matricula->id,
                ])
                ->assertCreated()
                ->assertJsonPath('data.numero_documento', $esperado);
        }
    }

    /** @test */
    public function rechaza_generar_si_no_hay_version_vigente(): void
    {
        DocPlantilla::factory()->create(['tipo_documento_id' => $this->tipo->id]);

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.generar'), [
                'tipo_documento_id' => $this->tipo->id,
                'entidad_id'        => $matricula->id,
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function rechaza_generar_sin_el_registro_asociado(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>Contenido</p>');

        $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.generar'), [
                'tipo_documento_id' => $this->tipo->id,
            ])
            ->assertJsonValidationErrors(['entidad_id']);
    }

    /** @test */
    public function rechaza_generar_con_un_tipo_inactivo(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>Contenido</p>');
        $this->tipo->update(['status' => 0]);

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.generar'), [
                'tipo_documento_id' => $this->tipo->id,
                'entidad_id'        => $matricula->id,
            ])
            ->assertJsonValidationErrors(['tipo_documento_id']);
    }

    /** @test */
    public function anula_un_documento_con_motivo(): void
    {
        $documento = $this->generarDocumento();

        $response = $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.anular', $documento['id']), [
                'motivo' => 'Error en el valor de la matrícula',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', DocDocumento::STATUS_ANULADO)
            ->assertJsonPath('data.status_text', 'Anulado')
            ->assertJsonPath('data.motivo_anulacion', 'Error en el valor de la matrícula');
    }

    /** @test */
    public function rechaza_anular_un_documento_ya_anulado(): void
    {
        $documento = $this->generarDocumento();

        $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.anular', $documento['id']), ['motivo' => 'Primera anulación'])
            ->assertOk();

        $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.anular', $documento['id']), ['motivo' => 'Segunda anulación'])
            ->assertStatus(422);
    }

    /** @test */
    public function exige_motivo_para_anular(): void
    {
        $documento = $this->generarDocumento();

        $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.anular', $documento['id']), [])
            ->assertJsonValidationErrors(['motivo']);
    }

    /** @test */
    public function filtra_los_documentos_por_entidad(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>{{ estudiante.name }}</p>');

        $matriculaUno = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);
        $matriculaDos = Matricula::factory()->create(['fecha_matricula' => '2024-07-20']);

        foreach ([$matriculaUno, $matriculaDos] as $matricula) {
            $this->actingAs($this->usuario)
                ->postJson(route('documentacion.documentos.generar'), [
                    'tipo_documento_id' => $this->tipo->id,
                    'entidad_id'        => $matricula->id,
                ])->assertCreated();
        }

        $response = $this->actingAs($this->usuario)
            ->getJson(route('documentacion.documentos.index', [
                'entidad_type' => Matricula::class,
                'entidad_id'   => $matriculaUno->id,
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entidad_id', $matriculaUno->id);
    }

    /** @test */
    public function el_listado_omite_el_contenido_renderizado(): void
    {
        $this->generarDocumento();

        $this->actingAs($this->usuario)
            ->getJson(route('documentacion.documentos.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.contenido_renderizado');
    }

    /** @test */
    public function deniega_la_generacion_sin_permiso(): void
    {
        $this->plantillaActiva('2020-01-01', '<p>Contenido</p>');
        $matricula  = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('documentacion.documentos.generar'), [
                'tipo_documento_id' => $this->tipo->id,
                'entidad_id'        => $matricula->id,
            ])
            ->assertForbidden();
    }

    /**
     * Genera un documento de apoyo para las pruebas que parten de uno existente.
     *
     * @return array<string, mixed>
     */
    private function generarDocumento(): array
    {
        $this->plantillaActiva('2020-01-01', '<p>{{ estudiante.name }}</p>');

        $matricula = Matricula::factory()->create(['fecha_matricula' => '2024-06-15']);

        return $this->actingAs($this->usuario)
            ->postJson(route('documentacion.documentos.generar'), [
                'tipo_documento_id' => $this->tipo->id,
                'entidad_id'        => $matricula->id,
            ])->json('data');
    }
}
